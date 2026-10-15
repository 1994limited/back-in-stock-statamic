<?php

namespace NineteenNinetyFour\BackInStock\Tests\Feature;

use DuncanMcClean\Cargo\Facades\Order;
use DuncanMcClean\Cargo\Facades\Product;
use DuncanMcClean\Cargo\Orders\OrderStatus;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use NineteenNinetyFour\BackInStock\AlertStatus;
use NineteenNinetyFour\BackInStock\BackInStock;
use NineteenNinetyFour\BackInStock\Events\ProductRestocked;
use NineteenNinetyFour\BackInStock\Jobs\SendAlerts;
use NineteenNinetyFour\BackInStock\Mail\RestockedMail;
use NineteenNinetyFour\BackInStock\Models\Item;
use NineteenNinetyFour\BackInStock\Models\Restock;
use NineteenNinetyFour\BackInStock\Sender;
use NineteenNinetyFour\BackInStock\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;
use Statamic\Facades\Entry;

class RestockTest extends TestCase
{
    #[Test]
    public function stock_saved_in_the_control_panel_emails_everyone_waiting(): void
    {
        $this->book(stock: 0);
        $first = $this->waiting('first@example.com');
        $second = $this->waiting('second@example.com');
        $unconfirmed = $this->asked('unconfirmed@example.com');
        Mail::fake();

        $this->restock('lantern-ledger', 3);

        Mail::assertSent(RestockedMail::class, 2);
        Mail::assertSent(RestockedMail::class, fn ($mail) => $mail->hasTo('first@example.com'));
        $this->assertSame(AlertStatus::Notified, $this->statusOf($first));
        $this->assertSame(AlertStatus::Notified, $this->statusOf($second));
        $this->assertSame(AlertStatus::Unconfirmed, $this->statusOf($unconfirmed));
        $this->assertNotNull($first->notified_at);
    }

    #[Test]
    public function a_restock_is_recorded_with_how_many_were_emailed(): void
    {
        Event::fake([ProductRestocked::class]);
        $this->book(stock: 0);
        $this->waiting('a@example.com');
        $this->waiting('b@example.com');
        Mail::fake();

        $this->restock('lantern-ledger', 5);

        $restock = Restock::sole();
        $this->assertSame(0, $restock->stock_before);
        $this->assertSame(5, $restock->stock_after);
        $this->assertSame(2, $restock->sent);
        $this->assertSame('save', $restock->trigger);
        Event::assertDispatched(ProductRestocked::class, fn ($e) => $e->restock->is($restock));
    }

    #[Test]
    public function saving_without_a_rise_emails_nobody(): void
    {
        $this->book(stock: 0);
        $this->waiting();
        Mail::fake();

        Product::find('lantern-ledger')->set('title', 'The Lantern Ledger (2nd ed.)')->save();
        $this->restock('lantern-ledger', 0);

        Mail::assertNothingSent();
        $this->assertSame(0, Restock::count());
    }

    #[Test]
    public function only_the_variant_that_came_back_is_emailed(): void
    {
        $this->editions(hardback: 0, paperback: 0);
        $hardback = $this->waiting('h@example.com', 'coral-arithmetic', 'Hardback');
        $paperback = $this->waiting('p@example.com', 'coral-arithmetic', 'Paperback');
        Mail::fake();

        $this->restock('coral-arithmetic', 2, 'Paperback');

        Mail::assertSent(RestockedMail::class, 1);
        Mail::assertSent(RestockedMail::class, fn ($mail) => $mail->hasTo('p@example.com'));
        $this->assertSame(AlertStatus::Waiting, $this->statusOf($hardback));
        $this->assertSame(AlertStatus::Notified, $this->statusOf($paperback));
    }

    #[Test]
    public function stock_put_back_by_a_cancelled_order_emails_people(): void
    {
        $this->book(stock: 1);
        $order = $this->checkout($this->cart([['product' => 'lantern-ledger']], 'buyer@example.com'));
        $this->assertSame(0, Product::find('lantern-ledger')->stock());

        $alert = $this->waiting('reader@example.com');
        Mail::fake();

        $order = Order::find($order->id());
        $order->status(OrderStatus::Cancelled)->save();

        $this->assertSame(1, Product::find('lantern-ledger')->stock());
        Mail::assertSent(RestockedMail::class, fn ($mail) => $mail->hasTo('reader@example.com'));
        $this->assertSame(AlertStatus::Notified, $this->statusOf($alert));
    }

    #[Test]
    public function stock_that_stops_being_counted_counts_as_back(): void
    {
        $this->book(stock: 0);
        $this->waiting();
        Mail::fake();

        Product::find('lantern-ledger')->remove('stock')->save();

        Mail::assertSent(RestockedMail::class, 1);
    }

    #[Test]
    public function people_can_be_emailed_only_as_many_as_came_back_oldest_first(): void
    {
        config(['back-in-stock.limit' => 'stock']);
        $this->book(stock: 0);
        $alerts = collect(['a', 'b', 'c', 'd'])->map(function ($name) {
            $alert = $this->waiting("{$name}@example.com");
            $this->travel(1)->minutes();

            return $alert;
        });
        Mail::fake();

        $this->restock('lantern-ledger', 2);

        Mail::assertSent(RestockedMail::class, 2);
        $this->assertSame(['notified', 'notified', 'waiting', 'waiting'], $alerts->map(fn ($a) => $this->statusOf($a)->value)->all());

        // One sold: no rise, nobody more.
        $this->restock('lantern-ledger', 1);
        Mail::assertSent(RestockedMail::class, 2);

        // One more unit back: the next person.
        $this->restock('lantern-ledger', 2);
        Mail::assertSent(RestockedMail::class, 3);
        $this->assertSame(AlertStatus::Notified, $this->statusOf($alerts[2]));
        $this->assertSame(AlertStatus::Waiting, $this->statusOf($alerts[3]));
    }

    #[Test]
    public function a_multiplier_emails_more_people_per_unit(): void
    {
        config(['back-in-stock.limit' => 'stock', 'back-in-stock.multiplier' => 2]);
        $this->book(stock: 0);
        foreach (range(1, 5) as $i) {
            $this->waiting("r{$i}@example.com");
        }
        Mail::fake();

        $this->restock('lantern-ledger', 2);

        Mail::assertSent(RestockedMail::class, 4);
    }

    #[Test]
    public function with_a_queue_the_emails_wait_for_the_delay(): void
    {
        config(['queue.default' => 'database', 'queue.connections.database' => ['driver' => 'database', 'table' => 'jobs', 'queue' => 'default']]);
        Queue::fake();
        $this->book(stock: 0);
        $this->waiting();

        $this->restock('lantern-ledger', 3);

        Queue::assertPushed(SendAlerts::class, fn (SendAlerts $job) => $job->delay !== null && now()->addMinutes(4)->lt($job->delay));
        $item = Item::sole();
        $this->assertTrue($item->pending);

        // Run early: nothing goes yet.
        Mail::fake();
        $this->assertSame(0, app(Sender::class)->send($item));

        $this->travel(6)->minutes();
        $this->assertSame(1, app(Sender::class)->send($item));
        Mail::assertSent(RestockedMail::class, 1);
    }

    #[Test]
    public function a_mistyped_stock_level_put_right_before_the_delay_emails_nobody(): void
    {
        config(['queue.default' => 'database', 'queue.connections.database' => ['driver' => 'database', 'table' => 'jobs', 'queue' => 'default']]);
        Queue::fake();
        $this->book(stock: 0);
        $alert = $this->waiting();
        Mail::fake();

        $this->restock('lantern-ledger', 50);
        $this->restock('lantern-ledger', 0);

        $this->travel(6)->minutes();
        $this->assertSame(0, app(Sender::class)->send(Item::sole()));

        Mail::assertNothingSent();
        $this->assertSame(AlertStatus::Waiting, $this->statusOf($alert));
        $this->assertFalse(Item::sole()->pending);
    }

    #[Test]
    public function draft_products_are_not_emailed_about_until_published(): void
    {
        $this->book(stock: 0);
        $alert = $this->waiting();
        Mail::fake();

        $entry = Entry::find('lantern-ledger');
        $entry->published(false)->set('stock', 3)->save();
        Mail::assertNothingSent();

        Entry::find('lantern-ledger')->published(true)->save();
        Mail::assertSent(RestockedMail::class, 1);
        $this->assertSame(AlertStatus::Notified, $this->statusOf($alert));
    }

    #[Test]
    public function the_scheduled_check_notices_stock_changed_without_events(): void
    {
        $this->book(stock: 0);
        $alert = $this->waiting();
        Mail::fake();

        Entry::find('lantern-ledger')->set('stock', 4)->saveQuietly();
        Mail::assertNothingSent();

        $this->artisan('back-in-stock:check')->assertSuccessful();

        Mail::assertSent(RestockedMail::class, 1);
        $this->assertSame('schedule', Restock::sole()->trigger);
        $this->assertSame(AlertStatus::Notified, $this->statusOf($alert));
    }

    #[Test]
    public function no_more_than_the_limit_are_sent_a_minute(): void
    {
        config(['back-in-stock.per_minute' => 2]);
        $this->book(stock: 0);
        foreach (range(1, 5) as $i) {
            $this->waiting("r{$i}@example.com");
        }
        Mail::fake();

        $this->restock('lantern-ledger', 10);
        Mail::assertSent(RestockedMail::class, 2);
        $this->assertTrue(Item::sole()->pending);

        $this->travel(61)->seconds();
        $this->artisan('back-in-stock:check');
        Mail::assertSent(RestockedMail::class, 4);

        $this->travel(61)->seconds();
        $this->artisan('back-in-stock:check');
        Mail::assertSent(RestockedMail::class, 5);
        $this->assertFalse(Item::sole()->pending);
    }

    #[Test]
    public function nobody_is_emailed_twice(): void
    {
        $this->book(stock: 0);
        $this->waiting();
        Mail::fake();

        $this->restock('lantern-ledger', 3);
        $this->restock('lantern-ledger', 0);
        $this->restock('lantern-ledger', 3);
        $this->artisan('back-in-stock:check');
        app(BackInStock::class)->sendNow(Item::sole());

        Mail::assertSent(RestockedMail::class, 1);
    }

    #[Test]
    public function confirming_while_in_stock_emails_straight_away(): void
    {
        $this->book(stock: 0);
        $alert = $this->asked();
        $this->restock('lantern-ledger', 2);
        Mail::fake();

        app(BackInStock::class)->confirm($alert);

        Mail::assertSent(RestockedMail::class, 1);
        $this->assertSame(AlertStatus::Notified, $this->statusOf($alert));
    }

    #[Test]
    public function a_mail_problem_leaves_the_request_waiting_for_another_go(): void
    {
        $this->book(stock: 0);
        $alert = $this->waiting();
        Mail::shouldReceive('to')->andThrow(new \RuntimeException('SMTP is down'));

        $this->restock('lantern-ledger', 3);

        $this->assertSame(AlertStatus::Waiting, $this->statusOf($alert));
        $this->assertTrue(Item::sole()->pending);
    }

    #[Test]
    public function sends_that_never_finished_go_back_to_waiting_after_an_hour(): void
    {
        $this->book(stock: 0);
        $alert = $this->waiting();
        $alert->forceFill(['status' => AlertStatus::Sending, 'claimed_at' => now()])->save();

        $this->assertSame(0, app(Sender::class)->releaseStuck());
        $this->travel(61)->minutes();
        $this->assertSame(1, app(Sender::class)->releaseStuck());
        $this->assertSame(AlertStatus::Waiting, $this->statusOf($alert));
    }

    #[Test]
    public function a_deleted_product_emails_nobody(): void
    {
        $this->book(stock: 0);
        $alert = $this->waiting();
        Mail::fake();

        Entry::find('lantern-ledger')->delete();
        $this->artisan('back-in-stock:check');

        Mail::assertNothingSent();
        $this->assertSame(AlertStatus::Waiting, $this->statusOf($alert));
    }
}
