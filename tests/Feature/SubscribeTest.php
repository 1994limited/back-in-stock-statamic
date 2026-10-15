<?php

namespace NineteenNinetyFour\BackInStock\Tests\Feature;

use Illuminate\Support\Facades\Mail;
use NineteenNinetyFour\BackInStock\AlertStatus;
use NineteenNinetyFour\BackInStock\Mail\ConfirmMail;
use NineteenNinetyFour\BackInStock\Models\Alert;
use NineteenNinetyFour\BackInStock\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;
use Statamic\Facades\Collection;

class SubscribeTest extends TestCase
{
    #[Test]
    public function asking_makes_an_unconfirmed_request_and_emails_the_link(): void
    {
        Mail::fake();
        $this->book(stock: 0);

        $this->from('/books/lantern-ledger')
            ->post('/!/back-in-stock/subscribe', ['product' => 'lantern-ledger', 'email' => ' Reader@Example.com ', 'consent' => '1'])
            ->assertRedirect('/books/lantern-ledger')
            ->assertSessionHas('back_in_stock_success');

        $alert = Alert::sole();
        $this->assertSame('reader@example.com', $alert->email);
        $this->assertSame(AlertStatus::Unconfirmed, $alert->status);
        $this->assertSame('default', $alert->site);
        $this->assertSame('Email me once when it’s back in stock. Nothing else.', $alert->consent);

        Mail::assertQueued(ConfirmMail::class, fn (ConfirmMail $mail) => $mail->hasTo('reader@example.com'));
    }

    #[Test]
    public function the_link_opens_a_page_whose_button_confirms_it(): void
    {
        $this->book(stock: 0);
        $alert = $this->asked();

        // Opening the link (as a mail app's link checker does) changes nothing.
        $this->get($alert->confirmUrl())
            ->assertOk()
            ->assertSee('Email reader@example.com once when The Lantern Ledger is back in stock?')
            ->assertSee('Yes, email me');
        $this->assertSame(AlertStatus::Unconfirmed, $this->statusOf($alert));

        // The button does, without a CSRF token: the signature stands in for it.
        $this->withMiddleware()->post($alert->confirmUrl())->assertOk()->assertSee('You’re on the list');

        $this->assertSame(AlertStatus::Waiting, $this->statusOf($alert));
        $this->assertNotNull($alert->confirmed_at);
        $this->get($alert->confirmUrl())->assertSee('That’s already confirmed');
    }

    #[Test]
    public function an_expired_or_changed_link_does_not_confirm(): void
    {
        $this->book(stock: 0);
        $alert = $this->asked();

        $this->post($alert->confirmUrl().'x')->assertForbidden();
        $this->post('/!/back-in-stock/confirm/'.$alert->id)->assertForbidden();
        $this->assertSame(AlertStatus::Unconfirmed, $this->statusOf($alert));

        $other = $this->asked('other@example.com');
        $url = $other->confirmUrl();
        $this->travel(73)->hours();
        $this->get($url)->assertForbidden()->assertSee('Link expired');
        $this->post($url)->assertForbidden();
        $this->assertSame(AlertStatus::Unconfirmed, $this->statusOf($other));
    }

    #[Test]
    public function a_site_page_can_be_shown_after_confirming(): void
    {
        config(['back-in-stock.pages.confirmed' => '/alerts/confirmed']);
        $this->book(stock: 0);
        $alert = $this->asked();

        $this->post($alert->confirmUrl())->assertRedirect('/alerts/confirmed')->assertSessionHas('back_in_stock_success');
    }

    #[Test]
    public function asking_twice_makes_one_request_and_does_not_say_so(): void
    {
        $this->book(stock: 0);
        $this->waiting();
        Mail::fake();

        $this->post('/!/back-in-stock/subscribe', ['product' => 'lantern-ledger', 'email' => 'reader@example.com', 'consent' => '1'])
            ->assertSessionHas('back_in_stock_success', __('back-in-stock::messages.subscribed'));

        $this->assertSame(1, Alert::count());
        Mail::assertNothingQueued();
    }

    #[Test]
    public function an_unconfirmed_request_gets_its_email_again_but_not_straight_away(): void
    {
        Mail::fake();
        $this->book(stock: 0);
        $post = fn () => $this->post('/!/back-in-stock/subscribe', ['product' => 'lantern-ledger', 'email' => 'reader@example.com', 'consent' => '1']);

        $post();
        $post();
        Mail::assertQueuedCount(1);

        $this->travel(6)->minutes();
        $post();
        Mail::assertQueuedCount(2);
        $this->assertSame(1, Alert::count());
    }

    #[Test]
    public function it_needs_an_email_and_consent(): void
    {
        $this->book(stock: 0);

        $this->post('/!/back-in-stock/subscribe', ['product' => 'lantern-ledger', 'email' => 'not an email'])
            ->assertSessionHasErrors(['email', 'consent'], null, 'back_in_stock');

        $this->postJson('/!/back-in-stock/subscribe', ['product' => 'lantern-ledger', 'email' => ['a@b.com'], 'consent' => '1'])
            ->assertStatus(422)->assertJsonPath('success', false)->assertJsonStructure(['errors' => ['email']]);

        $this->assertSame(0, Alert::count());
    }

    #[Test]
    public function only_sold_out_live_products_can_be_asked_about(): void
    {
        $this->book('in-stock', stock: 4);
        $this->book('not-counted', stock: null);
        $this->book('draft', stock: 0, data: ['published' => false]);
        $this->editions();

        foreach (['in-stock' => 'product', 'not-counted' => 'product', 'missing' => 'product', 'draft' => 'product'] as $id => $field) {
            $this->postJson('/!/back-in-stock/subscribe', ['product' => $id, 'email' => 'a@example.com', 'consent' => '1'])
                ->assertStatus($id === 'draft' ? 404 : 422)->assertJsonStructure(['errors' => [$field]]);
        }

        // A variant product needs a variant; the paperback is in stock.
        $this->postJson('/!/back-in-stock/subscribe', ['product' => 'coral-arithmetic', 'email' => 'a@example.com', 'consent' => '1'])
            ->assertStatus(422)->assertJsonStructure(['errors' => ['variant']]);
        $this->postJson('/!/back-in-stock/subscribe', ['product' => 'coral-arithmetic', 'variant' => 'Paperback', 'email' => 'a@example.com', 'consent' => '1'])
            ->assertStatus(422);
        $this->postJson('/!/back-in-stock/subscribe', ['product' => 'coral-arithmetic', 'variant' => 'Hardback', 'email' => 'a@example.com', 'consent' => '1'])
            ->assertOk()->assertJsonPath('success', true);

        $this->assertSame(['Hardback'], Alert::pluck('variant')->all());
    }

    #[Test]
    public function scheduled_and_expired_products_count_as_not_live(): void
    {
        Collection::find('products')->dated(true)->futureDateBehavior('private')->pastDateBehavior('public')->save();
        $this->book('scheduled', stock: 0, data: [])->date(now()->addWeek())->save();

        $this->postJson('/!/back-in-stock/subscribe', ['product' => 'scheduled', 'email' => 'a@example.com', 'consent' => '1'])
            ->assertStatus(404);
    }

    #[Test]
    public function the_honeypot_quietly_drops_bots(): void
    {
        Mail::fake();
        $this->book(stock: 0);

        $this->post('/!/back-in-stock/subscribe', ['product' => 'lantern-ledger', 'email' => 'bot@example.com', 'consent' => '1', 'website' => 'http://spam.example'])
            ->assertSessionHas('back_in_stock_success');

        $this->assertSame(0, Alert::count());
        Mail::assertNothingQueued();
    }

    #[Test]
    public function the_form_is_limited_per_visitor(): void
    {
        config(['back-in-stock.throttle.subscribe' => 2]);
        $this->book(stock: 0);

        foreach (range(1, 2) as $i) {
            $this->post('/!/back-in-stock/subscribe', ['product' => 'lantern-ledger', 'email' => "r{$i}@example.com", 'consent' => '1'])->assertRedirect();
        }

        $this->post('/!/back-in-stock/subscribe', ['product' => 'lantern-ledger', 'email' => 'r3@example.com', 'consent' => '1'])->assertStatus(429);
    }

    #[Test]
    public function one_address_gets_a_few_confirmation_emails_an_hour_at_most(): void
    {
        Mail::fake();
        config(['back-in-stock.throttle.per_email' => 2]);
        foreach (['a', 'b', 'c'] as $id) {
            $this->book($id, stock: 0);
        }

        foreach (['a', 'b'] as $id) {
            $this->postJson('/!/back-in-stock/subscribe', ['product' => $id, 'email' => 'victim@example.com', 'consent' => '1'])->assertOk();
        }

        $this->postJson('/!/back-in-stock/subscribe', ['product' => 'c', 'email' => 'victim@example.com', 'consent' => '1'])
            ->assertStatus(429)->assertJsonPath('errors.email', __('back-in-stock::messages.errors.too_fast'));

        Mail::assertQueuedCount(2);
        $this->assertSame(2, Alert::count());
    }

    #[Test]
    public function one_address_can_only_wait_for_so_many(): void
    {
        config(['back-in-stock.throttle.per_address' => 1]);
        $this->book('a', stock: 0);
        $this->book('b', stock: 0);
        $this->asked('a@example.com', 'a');

        $this->postJson('/!/back-in-stock/subscribe', ['product' => 'b', 'email' => 'a@example.com', 'consent' => '1'])
            ->assertStatus(422)->assertJsonPath('errors.email', __('back-in-stock::messages.errors.too_many'));
    }

    #[Test]
    public function a_signed_in_customer_can_leave_the_email_out_but_still_confirms(): void
    {
        Mail::fake();
        $this->book(stock: 0);
        $user = $this->user('member@example.com');

        $this->actingAs($user)
            ->post('/!/back-in-stock/subscribe', ['product' => 'lantern-ledger', 'consent' => '1'])
            ->assertSessionHas('back_in_stock_success');

        $alert = Alert::sole();
        $this->assertSame('member@example.com', $alert->email);
        $this->assertSame($user->id(), $alert->user_id);
        $this->assertSame(AlertStatus::Unconfirmed, $alert->status);
        Mail::assertQueued(ConfirmMail::class);
    }

    #[Test]
    public function a_signed_in_customer_asking_for_someone_else_is_not_linked_to_it(): void
    {
        $this->book(stock: 0);

        $this->actingAs($this->user('member@example.com'))
            ->post('/!/back-in-stock/subscribe', ['product' => 'lantern-ledger', 'email' => 'friend@example.com', 'consent' => '1']);

        $this->assertNull(Alert::sole()->user_id);
    }

    #[Test]
    public function redirects_stay_on_the_site(): void
    {
        $this->book(stock: 0);

        $this->from('/books/lantern-ledger')
            ->post('/!/back-in-stock/subscribe', ['product' => 'lantern-ledger', 'email' => 'a@example.com', 'consent' => '1', '_redirect' => 'https://evil.example/'])
            ->assertRedirect('/books/lantern-ledger');

        $this->post('/!/back-in-stock/subscribe', ['product' => 'lantern-ledger', 'email' => 'b@example.com', 'consent' => '1', '_redirect' => '/thanks'])
            ->assertRedirect('/thanks');
    }
}
