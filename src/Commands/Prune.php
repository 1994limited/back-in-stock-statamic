<?php

namespace NineteenNinetyFour\BackInStock\Commands;

use Illuminate\Console\Command;
use NineteenNinetyFour\BackInStock\AlertStatus;
use NineteenNinetyFour\BackInStock\Models\Alert;
use NineteenNinetyFour\BackInStock\Models\Item;
use Statamic\Console\RunsInPlease;

/**
 * Deletes requests older than config('back-in-stock.retention'), and
 * products nobody is waiting for any more. Runs daily with the scheduler.
 */
class Prune extends Command
{
    use RunsInPlease;

    protected $signature = 'back-in-stock:prune';

    protected $description = 'Delete old back-in-stock requests';

    public function handle(): int
    {
        $deleted = 0;

        foreach ([
            'unconfirmed' => [AlertStatus::Unconfirmed->value],
            'finished' => AlertStatus::finished(),
            'waiting' => [AlertStatus::Waiting->value],
        ] as $key => $statuses) {
            $days = config("back-in-stock.retention.{$key}");

            if ($days === null || $days === false) {
                continue;
            }

            $deleted += Alert::query()
                ->whereIn('status', $statuses)
                ->where('updated_at', '<', now()->subDays(max(1, (int) $days)))
                ->delete();
        }

        // Products nobody is waiting for any more, unless they came back in
        // stock in the last year: their restocks are kept for the Control
        // Panel (they hold no personal data).
        $items = Item::query()
            ->doesntHave('alerts')
            ->whereDoesntHave('restocks', fn ($query) => $query->where('created_at', '>=', now()->subYear()))
            ->where('updated_at', '<', now()->subDay())
            ->delete();

        $this->components->info("{$deleted} old requests deleted, {$items} products no longer watched.");

        return self::SUCCESS;
    }
}
