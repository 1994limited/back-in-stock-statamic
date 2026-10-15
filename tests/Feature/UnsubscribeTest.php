<?php

namespace NineteenNinetyFour\BackInStock\Tests\Feature;

use Illuminate\Support\Facades\Mail;
use NineteenNinetyFour\BackInStock\AlertStatus;
use NineteenNinetyFour\BackInStock\Mail\RestockedMail;
use NineteenNinetyFour\BackInStock\Models\Suppression;
use NineteenNinetyFour\BackInStock\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class UnsubscribeTest extends TestCase
{
    #[Test]
    public function the_link_shows_a_page_and_changes_nothing(): void
    {
        $this->book(stock: 0);
        $alert = $this->waiting();

        $this->get($alert->unsubscribeUrl())
            ->assertOk()
            ->assertSee('Stop all back-in-stock emails to reader@example.com?')
            ->assertHeader('X-Robots-Tag', 'noindex');

        $this->assertSame(AlertStatus::Waiting, $this->statusOf($alert));
    }

    #[Test]
    public function the_button_cancels_every_request_for_the_address(): void
    {
        $this->book('a', stock: 0);
        $this->book('b', stock: 0);
        $first = $this->waiting('reader@example.com', 'a');
        $second = $this->asked('reader@example.com', 'b');
        $someoneElse = $this->waiting('other@example.com', 'a');

        $this->post($first->unsubscribeUrl())->assertOk()->assertSee('You’re unsubscribed');

        $this->assertSame(AlertStatus::Cancelled, $this->statusOf($first));
        $this->assertSame(AlertStatus::Cancelled, $this->statusOf($second));
        $this->assertSame(AlertStatus::Waiting, $this->statusOf($someoneElse));
    }

    #[Test]
    public function mail_apps_can_unsubscribe_in_one_click_without_a_csrf_token(): void
    {
        $this->book(stock: 0);
        $alert = $this->waiting();

        // A real POST, through the CSRF middleware.
        $this->withMiddleware()
            ->call('POST', $alert->unsubscribeUrl(), ['List-Unsubscribe' => 'One-Click'])
            ->assertOk();

        $this->assertSame(AlertStatus::Cancelled, $this->statusOf($alert));
    }

    #[Test]
    public function an_unsigned_or_changed_link_does_nothing(): void
    {
        $this->book(stock: 0);
        $alert = $this->waiting();

        $this->post('/!/back-in-stock/unsubscribe/'.$alert->id)->assertForbidden();
        $this->post($alert->unsubscribeUrl().'1')->assertForbidden();

        $this->assertSame(AlertStatus::Waiting, $this->statusOf($alert));
    }

    #[Test]
    public function an_address_that_unsubscribed_is_not_emailed_until_it_asks_again(): void
    {
        $this->book('a', stock: 0);
        $this->book('b', stock: 0);
        $alert = $this->waiting('reader@example.com', 'a');
        $this->post($alert->unsubscribeUrl());
        $this->assertTrue(Suppression::query()->exists());

        // Asking again (and confirming) means it.
        $again = $this->waiting('reader@example.com', 'b');
        $this->assertFalse(Suppression::query()->exists());

        Mail::fake();
        $this->restock('a', 1);
        $this->restock('b', 1);

        Mail::assertSent(RestockedMail::class, 1);
        $this->assertSame(AlertStatus::Notified, $this->statusOf($again));
    }

    #[Test]
    public function a_request_confirmed_before_unsubscribing_is_never_sent(): void
    {
        $this->book(stock: 0);
        $alert = $this->waiting();

        // As if a send had already picked it up when the address unsubscribed.
        Suppression::add('reader@example.com');
        $this->travel(1)->seconds();
        Mail::fake();

        $this->restock('lantern-ledger', 1);

        Mail::assertNothingSent();
        $this->assertSame(AlertStatus::Cancelled, $this->statusOf($alert));
    }

    #[Test]
    public function only_a_hash_of_the_address_is_kept(): void
    {
        $this->book(stock: 0);
        $this->post($this->waiting()->unsubscribeUrl());

        $this->assertSame(hash('sha256', 'reader@example.com'), Suppression::sole()->email_hash);
    }
}
