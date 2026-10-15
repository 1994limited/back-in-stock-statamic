<?php

namespace NineteenNinetyFour\BackInStock\Tests\Feature;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use NineteenNinetyFour\BackInStock\AlertStatus;
use NineteenNinetyFour\BackInStock\Events\AlertConverted;
use NineteenNinetyFour\BackInStock\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class ConversionTest extends TestCase
{
    #[Test]
    public function buying_after_the_email_marks_the_request_bought(): void
    {
        Event::fake([AlertConverted::class]);
        $this->editions(hardback: 0);
        $alert = $this->waiting('reader@example.com', 'coral-arithmetic', 'Hardback');
        Mail::fake();
        $this->restock('coral-arithmetic', 3, 'Hardback');

        $order = $this->checkout($this->cart([['product' => 'coral-arithmetic', 'variant' => 'Hardback']], 'Reader@Example.com'));

        $alert->refresh();
        $this->assertSame(AlertStatus::Converted, $alert->status);
        $this->assertSame((string) $order->id(), $alert->order_id);
        Event::assertDispatched(AlertConverted::class);
    }

    #[Test]
    public function another_variant_or_address_does_not_count(): void
    {
        $this->editions(hardback: 0);
        $alert = $this->waiting('reader@example.com', 'coral-arithmetic', 'Hardback');
        Mail::fake();
        $this->restock('coral-arithmetic', 3, 'Hardback');

        $this->checkout($this->cart([['product' => 'coral-arithmetic', 'variant' => 'Paperback']], 'reader@example.com'));
        $this->checkout($this->cart([['product' => 'coral-arithmetic', 'variant' => 'Hardback']], 'someone@example.com'));

        $this->assertSame(AlertStatus::Notified, $this->statusOf($alert));
    }

    #[Test]
    public function only_within_the_days_set(): void
    {
        config(['back-in-stock.conversion_days' => 7]);
        $this->book(stock: 0);
        $alert = $this->waiting();
        Mail::fake();
        $this->restock('lantern-ledger', 3);

        $this->travel(8)->days();
        $this->checkout($this->cart([['product' => 'lantern-ledger']]));

        $this->assertSame(AlertStatus::Notified, $this->statusOf($alert));
    }

    #[Test]
    public function it_can_be_turned_off(): void
    {
        config(['back-in-stock.conversion_days' => null]);
        $this->book(stock: 0);
        $alert = $this->waiting();
        Mail::fake();
        $this->restock('lantern-ledger', 3);

        $this->checkout($this->cart([['product' => 'lantern-ledger']]));

        $this->assertSame(AlertStatus::Notified, $this->statusOf($alert));
    }
}
