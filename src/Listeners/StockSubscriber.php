<?php

namespace NineteenNinetyFour\BackInStock\Listeners;

use DuncanMcClean\Cargo\Contracts\Orders\Order;
use DuncanMcClean\Cargo\Events\OrderCreated;
use Illuminate\Events\Dispatcher;
use NineteenNinetyFour\BackInStock\AlertStatus;
use NineteenNinetyFour\BackInStock\Cargo\Stock;
use NineteenNinetyFour\BackInStock\Events\AlertConverted;
use NineteenNinetyFour\BackInStock\Models\Alert;
use NineteenNinetyFour\BackInStock\Watcher;
use Statamic\Events\EntrySaved;
use Throwable;

/**
 * Watching Cargo: a product saved (in the Control Panel, by Cargo when an
 * order is cancelled and stock put back, or by an import) is checked for
 * a restock, and orders mark emailed requests as bought.
 */
class StockSubscriber
{
    public function __construct(protected Stock $stock, protected Watcher $watcher) {}

    public function subscribe(Dispatcher $events): array
    {
        return [
            EntrySaved::class => 'entrySaved',
            OrderCreated::class => 'orderCreated',
        ];
    }

    public function entrySaved(EntrySaved $event): void
    {
        if (! $this->stock->isProductEntry($event->entry)) {
            return;
        }

        try {
            $this->watcher->checkProducts($this->stock->relatedIds($event->entry), 'save');
        } catch (Throwable $e) {
            // Never stop a product saving; back-in-stock:check tries again.
            report($e);
        }
    }

    /**
     * Someone emailed about a product bought it, within the days set in
     * config('back-in-stock.conversion_days').
     */
    public function orderCreated(OrderCreated $event): void
    {
        $days = config('back-in-stock.conversion_days', 14);

        if ($days === null || $days === false) {
            return;
        }

        try {
            $this->markConverted($event->order, (int) $days);
        } catch (Throwable $e) {
            report($e);
        }
    }

    protected function markConverted(Order $order, int $days): void
    {
        $email = $order->customer()?->email();

        if (! is_string($email) || $email === '') {
            return;
        }

        foreach ($order->lineItems() as $lineItem) {
            $ids = [(string) $lineItem->product()?->id()];

            try {
                $ids = $this->stock->relatedIds($lineItem->product());
            } catch (Throwable) {
                // Keep the product's own id.
            }

            $alerts = Alert::query()
                ->forEmail($email)
                ->whereIn('product_id', $ids)
                ->where('variant', (string) ($lineItem->variant()?->key() ?? ''))
                ->where('status', AlertStatus::Notified->value)
                ->where('notified_at', '>=', now()->subDays($days))
                ->get();

            foreach ($alerts as $alert) {
                $alert->forceFill(['status' => AlertStatus::Converted, 'converted_at' => now(), 'order_id' => (string) $order->id()])->save();

                AlertConverted::dispatch($alert);
            }
        }
    }
}
