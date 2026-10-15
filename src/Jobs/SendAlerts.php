<?php

namespace NineteenNinetyFour\BackInStock\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use NineteenNinetyFour\BackInStock\Models\Item;
use NineteenNinetyFour\BackInStock\Sender;

/**
 * Emailing the people waiting for one product or variant.
 */
class SendAlerts implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 3;

    public function __construct(public int $itemId) {}

    public function handle(Sender $sender): void
    {
        if ($item = Item::find($this->itemId)) {
            $sender->send($item);
        }
    }

    /**
     * Whether jobs can be delayed: a real queue, not "sync".
     */
    public static function canWait(): bool
    {
        return config('queue.connections.'.config('queue.default').'.driver') !== 'sync';
    }

    /**
     * Queue the emails for when they're due. Without a queue (the "sync"
     * connection) there's no waiting: they're sent once the page has been
     * sent, or straight away from the command line.
     */
    public static function dispatchFor(Item $item, ?\DateTimeInterface $at = null): void
    {
        $at ??= $item->send_after;
        $queue = config('back-in-stock.email.queue');

        if (! static::canWait()) {
            app()->runningInConsole()
                ? static::dispatchSync($item->id)
                : static::dispatchAfterResponse($item->id);

            return;
        }

        $job = static::dispatch($item->id)->onQueue(is_string($queue) ? $queue : null);

        if ($at && $at > now()) {
            $job->delay($at);
        }
    }
}
