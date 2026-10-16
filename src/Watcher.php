<?php

namespace NineteenNinetyFour\BackInStock;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
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
        $queued = false;

        $restock = Cache::lock('back-in-stock:watch:'.$item->id, 30)->block(10, function () use ($item, $trigger, &$queued) {
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

            // Anything sold since emails were lined up comes off what's left to send.
            $this->capToStock($item);
            $item->save();

            if ($allowance === 0 || ! $item->waiting()->exists()) {
                return null;
            }

            $restock = $restocked ? $item->restocks()->create([
                'stock_before' => $wasTracked ? $before : null,
                'stock_after' => $item->stock,
                'trigger' => $trigger,
            ]) : null;

            $this->queue($item, $allowance, dispatch: false);
            $queued = true;

            return $restock;
        });

        // Outside the lock: without a queue, sending starts straight away,
        // and saves made meanwhile need the lock.
        if ($restock) {
            ProductRestocked::dispatch($item, $restock);
        }

        if ($queued) {
            SendAlerts::dispatchFor($item->refresh());
        }

        return $restock;
    }

    /**
     * Line up emails for a product or variant: to this many more people,
     * or everyone waiting (null). Sent after the delay.
     */
    public function queue(Item $item, ?int $allowance, ?int $delayMinutes = null, bool $dispatch = true): void
    {
        DB::connection($item->getConnectionName())->transaction(function () use ($item, $allowance, $delayMinutes) {
            $fresh = Item::query()->lockForUpdate()->find($item->id) ?? $item;

            $alreadyAll = $fresh->pending && $fresh->pending_limit === null;

            $fresh->pending_limit = $alreadyAll || $allowance === null
                ? null
                : ($fresh->pending ? max(0, (int) $fresh->pending_limit) : 0) + $allowance;

            if (! $fresh->pending || $fresh->send_after === null) {
                $fresh->send_after = now()->addMinutes(SendAlerts::canWait() ? ($delayMinutes ?? Settings::delay()) : 0);
            }

            $fresh->pending = true;
            $fresh->pending_version = (int) $fresh->pending_version + 1;
            $fresh->restocked_at = now();

            if ($allowance !== null) {
                $this->capToStock($fresh);
            }

            $fresh->save();
            $item->setRawAttributes($fresh->getAttributes(), true);
        });

        if ($dispatch) {
            SendAlerts::dispatchFor($item);
        }
    }

    /**
     * With "as many as came back", never line up more emails than there's
     * stock for (times the multiplier): units sold since a restock come off.
     */
    public function capToStock(Item $item): void
    {
        if (! $item->pending || $item->pending_limit === null || ! $item->stock_tracked || Settings::limit() !== 'stock') {
            return;
        }

        $item->pending_limit = min((int) $item->pending_limit, max(0, (int) $item->stock) * Settings::multiplier());
    }

    /**
     * When someone confirms while it's already in stock (the page they
     * asked on was out of date, say), the next email goes straight away:
     * with "everyone waiting", to everyone, them included; with "as many as
     * came back", to whoever has waited longest, if there's stock for one
     * more.
     */
    public function confirmedWhileInStock(Alert $alert): void
    {
        $item = $alert->item;

        if (! $item) {
            return;
        }

        $queued = Cache::lock('back-in-stock:watch:'.$item->id, 30)->block(10, function () use ($item) {
            $item->refresh();
            $product = $item->product();

            if (! $product || ! $product->isAvailable() || $product->isSoldOut()) {
                return false;
            }

            $item->forceFill(['stock' => $product->stock(), 'stock_tracked' => $product->tracksStock()])->save();

            $this->queue($item, Settings::limit() === 'stock' && $product->tracksStock() ? 1 : null, 0, dispatch: false);

            return true;
        });

        if ($queued) {
            SendAlerts::dispatchFor($item->refresh());
        }
    }
}
