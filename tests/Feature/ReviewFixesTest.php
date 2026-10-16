<?php

namespace NineteenNinetyFour\BackInStock\Tests\Feature;

use DuncanMcClean\Cargo\Facades\Product;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use NineteenNinetyFour\BackInStock\AlertStatus;
use NineteenNinetyFour\BackInStock\BackInStock;
use NineteenNinetyFour\BackInStock\Events\AlertNotified;
use NineteenNinetyFour\BackInStock\Mail\RestockedMail;
use NineteenNinetyFour\BackInStock\Models\Alert;
use NineteenNinetyFour\BackInStock\Models\Item;
use NineteenNinetyFour\BackInStock\Models\Restock;
use NineteenNinetyFour\BackInStock\Sender;
use NineteenNinetyFour\BackInStock\Tests\TestCase;
use NineteenNinetyFour\BackInStock\Watcher;
use PHPUnit\Framework\Attributes\Test;
use Statamic\Facades\Entry;

/**
 * Cases found in review: sales during the wait, work lined up mid-send,
 * stale stock, who's emailed on confirming, duplicates and history.
 */
class ReviewFixesTest extends TestCase
{
    protected function useQueue(): void
    {
        config(['queue.default' => 'database', 'queue.connections.database' => ['driver' => 'database', 'table' => 'jobs', 'queue' => 'default']]);
        Queue::fake();
    }

    protected function waitingFor(int $count, string $product = 'lantern-ledger'): void
    {
        foreach (range(1, $count) as $i) {
            $this->waiting("r{$i}@example.com", $product);
            $this->travel(1)->minutes();
        }
    }

    #[Test]
    public function units_sold_during_the_wait_come_off_who_is_emailed(): void
    {
        config(['back-in-stock.limit' => 'stock']);
        $this->useQueue();
        $this->book(stock: 0);
        $this->waitingFor(5);
        Mail::fake();

        $this->restock('lantern-ledger', 5);
        $this->restock('lantern-ledger', 2); // three sold before the emails went

        $this->travel(6)->minutes();
        app(Sender::class)->send(Item::sole());

        Mail::assertSent(RestockedMail::class, 2);
    }

    #[Test]
    public function the_allowance_never_grows_past_the_stock(): void
    {
        config(['back-in-stock.limit' => 'stock']);
        $this->useQueue();
        $this->book(stock: 0);
        $this->waitingFor(9);
        Mail::fake();

        $this->restock('lantern-ledger', 5);
        $this->restock('lantern-ledger', 2);
        $this->restock('lantern-ledger', 5); // three back: 5 in stock, not 8 to email

        $this->travel(6)->minutes();
        app(Sender::class)->send(Item::sole());

        Mail::assertSent(RestockedMail::class, 5);
    }

    #[Test]
    public function emails_lined_up_while_a_send_runs_are_not_lost(): void
    {
        config(['back-in-stock.limit' => 'stock']);
        $this->book(stock: 0);
        $this->waitingFor(3);
        Mail::fake();

        // As if the product were restocked again mid-send.
        $once = false;
        Event::listen(AlertNotified::class, function () use (&$once) {
            if (! $once) {
                $once = true;
                Entry::find('lantern-ledger')->set('stock', 3)->saveQuietly();
                app(Watcher::class)->check(Item::sole(), 'save');
            }
        });

        $this->restock('lantern-ledger', 1);

        Mail::assertSent(RestockedMail::class, 3);
        $this->assertSame(0, Alert::where('status', 'waiting')->count());

        // Without a queue, the scheduled check finishes what was lined up.
        $this->artisan('back-in-stock:check');
        Mail::assertSent(RestockedMail::class, 3);
        $this->assertFalse(Item::sole()->pending);
    }

    #[Test]
    public function a_product_asked_about_again_starts_from_its_stock_now(): void
    {
        config(['back-in-stock.limit' => 'stock']);
        $this->book(stock: 0);
        $first = $this->waiting('first@example.com');
        Mail::fake();
        $this->restock('lantern-ledger', 10);
        $this->assertSame(AlertStatus::Notified, $this->statusOf($first));

        // Sells out without saving the entry, while nobody's waiting.
        Entry::find('lantern-ledger')->set('stock', 0)->saveQuietly();
        $second = $this->waiting('second@example.com');
        Entry::find('lantern-ledger')->set('stock', 5)->saveQuietly();

        $this->artisan('back-in-stock:check');

        $this->assertSame(AlertStatus::Notified, $this->statusOf($second));
    }

    #[Test]
    public function confirming_while_in_stock_emails_the_next_in_line_if_theres_stock(): void
    {
        config(['back-in-stock.limit' => 'stock']);
        $this->book(stock: 0);
        $first = $this->waiting('first@example.com');
        $this->travel(1)->minutes();
        $second = $this->waiting('second@example.com');
        $newcomer = $this->asked('new@example.com');
        Mail::fake();

        // One unit back: the person who asked first is emailed.
        $this->restock('lantern-ledger', 1);
        $this->assertSame(AlertStatus::Notified, $this->statusOf($first));
        $this->assertSame(AlertStatus::Waiting, $this->statusOf($second));

        // Someone confirms while it's in stock: one more email, to whoever
        // has waited longest. Not a second one for the same unit.
        app(BackInStock::class)->confirm($newcomer);

        $this->assertSame(AlertStatus::Notified, $this->statusOf($second));
        $this->assertSame(AlertStatus::Waiting, $this->statusOf($newcomer));
        Mail::assertSent(RestockedMail::class, 2);
        $this->assertSame(0, (int) Item::sole()->pending_limit);
    }

    #[Test]
    public function confirming_while_in_stock_emails_them_when_everyone_is_emailed(): void
    {
        $this->book(stock: 0);
        $newcomer = $this->asked('new@example.com');
        $this->restock('lantern-ledger', 3);
        Mail::fake();

        app(BackInStock::class)->confirm($newcomer);

        $this->assertSame(AlertStatus::Notified, $this->statusOf($newcomer));
    }

    #[Test]
    public function a_newer_duplicate_stands_down_while_the_older_is_being_sent(): void
    {
        $this->book(stock: 0);
        $older = $this->waiting();
        Entry::find('lantern-ledger')->set('stock', 2)->saveQuietly();
        $older->refresh()->forceFill(['status' => AlertStatus::Sending])->save();

        $this->travel(1)->seconds();
        $newer = $older->replicate();
        $newer->id = $newer->newUniqueId();
        $newer->status = AlertStatus::Waiting;
        $newer->save();
        Mail::fake();

        // The newer one sees the older one sending, and stands down.
        $this->assertFalse(app(Sender::class)->sendOne($newer, $newer->item->product()));
        $this->assertSame(AlertStatus::Cancelled, $this->statusOf($newer));
        Mail::assertNothingSent();
    }

    #[Test]
    public function two_requests_from_one_address_get_one_email(): void
    {
        $this->book(stock: 0);
        $alert = $this->waiting();

        // As if two forms were sent at once.
        $copy = $alert->replicate();
        $copy->id = $copy->newUniqueId();
        $copy->save();
        Mail::fake();

        $this->restock('lantern-ledger', 3);

        Mail::assertSent(RestockedMail::class, 1);
        $this->assertSame(['cancelled', 'notified'], Alert::orderBy('status')->pluck('status')->map->value->all());
    }

    #[Test]
    public function pruning_keeps_a_years_restocks(): void
    {
        $this->book(stock: 0);
        $alert = $this->waiting();
        Mail::fake();
        $this->restock('lantern-ledger', 3);
        $alert->delete();

        $this->travel(2)->days();
        $this->artisan('back-in-stock:prune');
        $this->assertSame(1, Restock::count());

        $this->travel(1)->years();
        $this->artisan('back-in-stock:prune');
        $this->assertSame(0, Item::count());
    }

    #[Test]
    public function without_a_queue_a_long_list_is_sent_over_scheduled_checks_not_all_at_once(): void
    {
        $this->book(stock: 0);
        $this->waitingFor(3);
        Mail::fake();

        // A pass of two, to show the rest waits for the next check.
        $this->app->singleton(Sender::class, fn () => new class extends Sender
        {
            public const BATCH = 2;
        });

        $this->restock('lantern-ledger', 5);
        Mail::assertSent(RestockedMail::class, 2);
        $this->assertTrue(Item::sole()->pending);

        $this->artisan('back-in-stock:check');
        Mail::assertSent(RestockedMail::class, 3);
        $this->assertFalse(Item::sole()->pending);
    }
}
