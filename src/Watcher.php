<?php

namespace NineteenNinetyFour\BackInStock;

use Illuminate\Support\Facades\Cache;
use NineteenNinetyFour\BackInStock\Events\ProductRestocked;
use NineteenNinetyFour\BackInStock\Jobs\SendAlerts;
use NineteenNinetyFour\BackInStock\Models\Alert;
use NineteenNinetyFour\BackInStock\Models\Item;
use NineteenNinetyFour\BackInStock\Models\Restock;

/**
 * Noticing stock coming back. Each product or variant someone is waiting
 * for remembers the stock it was last seen with; when it's checked (on
 * every save of the product, and every few minutes by back-in-stock:check)
 * a rise means a restock, and emails are lined up.
 *
 * This works however the stock changed: in the Control Panel, from a
 * cancelled order (Cargo puts the stock back), an import or a deploy.
 */
class Watcher
{
    /**
     * Check the products and variants with these product ids.
     *
     * @param  array<int, string>  $productIds
     */
    public function checkProducts(array $productIds, string $trigger): int
    {
        return Item::query()
            ->whereIn('product_id', $productIds)
            ->get()
            ->filter(fn (Item $item) => $this->check($item, $trigger) !== null)
            ->count();
    }

    /**
     * Check every product and variant someone is waiting for.
     */
    public function checkAll(string $trigger = 'schedule'): int
    {
        $restocked = 0;

        Item::query()
            ->whereHas('alerts', fn ($query) => $query->whereIn('status', [AlertStatus::Waiting->value, AlertStatus::Unconfirmed->value]))
            ->chunkById(200, function ($items) use (&$restocked, $trigger) {
                foreach ($items as $item) {
                    $restocked += $this->check($item, $trigger) ? 1 : 0;
                }
            });

        return $restocked;
    }

    /**
     * Check one product or variant against the stock last seen. Returns
     * the restock, if it came back.
     */
    public function check(Item $item, string $trigger): ?Restock
    {
        return Cache::lock('back-in-stock:watch:'.$item->id, 30)->block(10, function () use ($item, $trigger) {
            $item->refresh();
            $product = $item->product();

            // Gone, or not live: nothing to say yet. The stock isn't
            // remembered either, so publishing it later still counts.
            if (! $product || ! $product->isAvailable()) {
                return null;
            }

            $before = $item->stock;
            $wasTracked = $item->stock_tracked;
            $wasOut = $wasTracked && (int) $before <= 0;

            $item->stock = $product->stock();
            $item->stock_tracked = $product->tracksStock();

            if ($product->isSoldOut()) {
                $item->save();

                return null;
            }

            // How many people this rise can be told about: everyone, or as
            // many as units came back (times the multiplier).
            if (Settings::limit() === 'stock' && $item->stock_tracked) {
                $added = (int) $item->stock - max(0, (int) ($wasTracked ? $before : 0));
                $allowance = $added > 0 ? $added * Settings::multiplier() : 0;
            } else {
                $allowance = $wasOut || $item->waiting()->exists() ? null : 0;
            }

            $restocked = $wasOut || ($allowance !== null && $allowance > 0);

            $item->save();

            if ($allowance === 0 || ! $item->waiting()->exists()) {
                return null;
            }

            $restock = $restocked ? $item->restocks()->create([
                'stock_before' => $wasTracked ? $before : null,
                'stock_after' => $item->stock,
                'trigger' => $trigger,
            ]) : null;

            $this->queue($item, $allowance);

            if ($restock) {
                ProductRestocked::dispatch($item, $restock);
            }

            return $restock;
        });
    }

    /**
     * Line up emails for a product or variant: to this many more people,
     * or everyone waiting (null). Sent after the delay.
     */
    public function queue(Item $item, ?int $allowance, ?int $delayMinutes = null): void
    {
        $alreadyAll = $item->pending && $item->pending_limit === null;

        $item->pending_limit = $alreadyAll || $allowance === null
            ? null
            : ($item->pending ? (int) $item->pending_limit : 0) + $allowance;

        if (! $item->pending || $item->send_after === null) {
            $item->send_after = now()->addMinutes(SendAlerts::canWait() ? ($delayMinutes ?? Settings::delay()) : 0);
        }

        $item->pending = true;
        $item->restocked_at = now();
        $item->save();

        SendAlerts::dispatchFor($item);
    }

    /**
     * When someone confirms while it's already in stock (the page they
     * asked on was out of date, say), email them straight away.
     */
    public function confirmedWhileInStock(Alert $alert): void
    {
        $item = $alert->item;
        $product = $item?->product();

        if (! $item || ! $product || ! $product->isAvailable() || $product->isSoldOut()) {
            return;
        }

        $this->queue($item, 1, 0);
    }
}
