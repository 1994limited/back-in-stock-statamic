<?php

namespace NineteenNinetyFour\BackInStock\Tests\Feature;

use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;
use NineteenNinetyFour\BackInStock\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class TagsTest extends TestCase
{
    protected function withErrors(array $errors, string $item): void
    {
        session()->put('errors', (new ViewErrorBag)->put('back_in_stock', new MessageBag($errors)));
        session()->put('back_in_stock_item', $item);
    }

    #[Test]
    public function the_form_posts_the_product_with_a_csrf_token_and_a_honeypot(): void
    {
        $this->book(stock: 0);

        $html = $this->antlers('{{ back_in_stock product="lantern-ledger" redirect="/thanks" }}<input name="email" value="{{ email }}"><label><input type="checkbox" name="consent" value="1"> {{ consent_text }}</label>{{ /back_in_stock }}');

        $this->assertStringContainsString('action="http://localhost/!/back-in-stock/subscribe"', $html);
        $this->assertStringContainsString('name="_token"', $html);
        $this->assertStringContainsString('<input type="hidden" name="product" value="lantern-ledger">', $html);
        $this->assertStringContainsString('<input type="hidden" name="_redirect" value="/thanks">', $html);
        $this->assertStringContainsString('name="website"', $html);
        $this->assertStringContainsString('Email me once when it’s back in stock. Nothing else.', $html);
    }

    #[Test]
    public function the_form_uses_the_entry_in_context(): void
    {
        $this->book(stock: 0);

        $html = $this->antlers('{{ back_in_stock }}{{ title }}{{ /back_in_stock }}', ['id' => 'lantern-ledger']);

        $this->assertStringContainsString('The Lantern Ledger', $html);
    }

    #[Test]
    public function the_form_shows_nothing_when_its_in_stock_or_not_counted(): void
    {
        $this->book('in', stock: 2);
        $this->book('uncounted', stock: null);

        $this->assertSame('', trim($this->antlers('{{ back_in_stock product="in" }}form{{ /back_in_stock }}')));
        $this->assertSame('', trim($this->antlers('{{ back_in_stock product="uncounted" }}form{{ /back_in_stock }}')));
        $this->assertSame('', trim($this->antlers('{{ back_in_stock product="missing" }}form{{ /back_in_stock }}')));
    }

    #[Test]
    public function a_variant_can_be_given(): void
    {
        $this->editions(hardback: 0, paperback: 7);

        $html = $this->antlers('{{ back_in_stock product="coral-arithmetic" variant="Hardback" }}[{{ variant_name }}]{{ /back_in_stock }}');
        $this->assertStringContainsString('<input type="hidden" name="variant" value="Hardback">', $html);
        $this->assertStringContainsString('[Hardback]', $html);

        $this->assertSame('', trim($this->antlers('{{ back_in_stock product="coral-arithmetic" variant="Paperback" }}form{{ /back_in_stock }}')));
    }

    #[Test]
    public function without_a_variant_the_sold_out_ones_are_listed_to_choose_from(): void
    {
        $this->editions(hardback: 0, paperback: 7);

        $html = $this->antlers('{{ back_in_stock product="coral-arithmetic" }}{{ if has_variants }}<select name="variant">{{ variants }}<option value="{{ key }}">{{ name }}</option>{{ /variants }}</select>{{ /if }}{{ /back_in_stock }}');

        $this->assertStringContainsString('<option value="Hardback">Hardback</option>', $html);
        $this->assertStringNotContainsString('Paperback', $html);
        $this->assertStringNotContainsString('name="variant" value=', $html);
    }

    #[Test]
    public function errors_and_old_input_show_on_the_form_that_was_posted(): void
    {
        $this->book('a', stock: 0);
        $this->book('b', stock: 0);
        $this->withErrors(['email' => 'Enter a valid email address.'], 'a|');
        session()->put('_old_input', ['email' => '<b>bad</b>']);

        $template = '{{ back_in_stock product="%s" }}[{{ errors:email }}|{{ email }}]{{ /back_in_stock }}';

        $this->assertStringContainsString('[Enter a valid email address.|&lt;b&gt;bad&lt;/b&gt;]', $this->antlers(sprintf($template, 'a')));
        $this->assertStringContainsString('[|]', $this->antlers(sprintf($template, 'b')));
    }

    #[Test]
    public function the_success_message_shows_on_the_form_that_was_posted(): void
    {
        $this->book('a', stock: 0);
        $this->book('b', stock: 0);
        session()->put('back_in_stock_success', 'Nearly there');
        session()->put('back_in_stock_item', 'a|');

        $template = '{{ back_in_stock product="%s" }}{{ if success }}{{ success }}{{ else }}form{{ /if }}{{ /back_in_stock }}';

        $this->assertStringContainsString('Nearly there', $this->antlers(sprintf($template, 'a')));
        $this->assertStringContainsString('form', $this->antlers(sprintf($template, 'b')));
        $this->assertSame('Nearly there', $this->antlers('{{ back_in_stock:success }}'));
    }

    #[Test]
    public function a_signed_in_customers_email_is_filled_in(): void
    {
        $this->book(stock: 0);
        $this->actingAs($this->user('member@example.com'));

        $html = $this->antlers('{{ back_in_stock product="lantern-ledger" }}[{{ email }}|{{ signed_in }}]{{ /back_in_stock }}');

        $this->assertStringContainsString('[member@example.com|1]', $html);
    }

    #[Test]
    public function on_a_statically_cached_site_nothing_personal_is_shown_unless_asked(): void
    {
        config(['statamic.static_caching.strategy' => 'half']);
        $this->book(stock: 0);
        $this->actingAs($this->user('member@example.com'));

        $this->assertStringContainsString('[|]', $this->antlers('{{ back_in_stock product="lantern-ledger" }}[{{ email }}|{{ signed_in }}]{{ /back_in_stock }}'));
        $this->assertStringContainsString('[member@example.com|1]', $this->antlers('{{ back_in_stock product="lantern-ledger" personal="true" }}[{{ email }}|{{ signed_in }}]{{ /back_in_stock }}'));
        $this->assertStringNotContainsString('personal=', $this->antlers('{{ back_in_stock product="lantern-ledger" personal="true" }}x{{ /back_in_stock }}'));
    }

    #[Test]
    public function sold_out_says_whether_its_sold_out(): void
    {
        $this->book('out', stock: 0);
        $this->book('in', stock: 3);
        $this->editions(hardback: 0, paperback: 7);

        $this->assertSame('yes', $this->antlers('{{ if {back_in_stock:sold_out product="out"} }}yes{{ else }}no{{ /if }}'));
        $this->assertSame('no', $this->antlers('{{ if {back_in_stock:sold_out product="in"} }}yes{{ else }}no{{ /if }}'));
        $this->assertSame('yes', $this->antlers('{{ if {back_in_stock:sold_out product="coral-arithmetic"} }}yes{{ else }}no{{ /if }}'));
        $this->assertSame('no', $this->antlers('{{ if {back_in_stock:sold_out product="coral-arithmetic" variant="Paperback"} }}yes{{ else }}no{{ /if }}'));
    }

    #[Test]
    public function count_is_how_many_are_waiting(): void
    {
        $this->book(stock: 0);
        $this->waiting('a@example.com');
        $this->waiting('b@example.com');
        $this->asked('c@example.com');

        $this->assertSame('2', $this->antlers('{{ back_in_stock:count product="lantern-ledger" }}'));
    }

    #[Test]
    public function a_customer_sees_and_cancels_their_own_requests(): void
    {
        $this->book(stock: 0);
        $this->editions(hardback: 0);
        $user = $this->user('member@example.com');
        $mine = $this->waiting('member@example.com');
        $this->asked('member@example.com', 'coral-arithmetic', 'Hardback');
        $this->waiting('other@example.com');
        $this->actingAs($user);

        $html = $this->antlers('{{ back_in_stock:alerts }}<li>{{ name }} ({{ status_label }}){{ back_in_stock:cancel :alert="id" }}<button>Cancel</button>{{ /back_in_stock:cancel }}</li>{{ /back_in_stock:alerts }}');

        $this->assertStringContainsString('The Lantern Ledger (Waiting)', $html);
        $this->assertStringContainsString('Coral Arithmetic – Hardback (Unconfirmed)', $html);
        $this->assertSame(2, substr_count($html, '<li>'));
        $this->assertStringContainsString('<input type="hidden" name="alert" value="'.$mine->id.'">', $html);
        $this->assertSame('yes', $this->antlers('{{ if {back_in_stock:subscribed product="lantern-ledger"} }}yes{{ /if }}'));

        $this->post('/!/back-in-stock/cancel', ['alert' => $mine->id])->assertSessionHas('back_in_stock_success');
        $this->assertSame('cancelled', $this->statusOf($mine)->value);
    }

    #[Test]
    public function nobody_can_cancel_someone_elses_request(): void
    {
        $this->book(stock: 0);
        $theirs = $this->waiting('other@example.com');

        $this->post('/!/back-in-stock/cancel', ['alert' => $theirs->id])->assertSessionHasErrors('alert', null, 'back_in_stock');
        $this->actingAs($this->user('member@example.com'))
            ->post('/!/back-in-stock/cancel', ['alert' => $theirs->id])->assertSessionHasErrors('alert', null, 'back_in_stock');

        $this->assertSame('waiting', $this->statusOf($theirs)->value);
    }

    #[Test]
    public function signed_out_visitors_have_no_requests(): void
    {
        $this->assertSame('', trim($this->antlers('{{ back_in_stock:alerts }}x{{ /back_in_stock:alerts }}')));
    }
}
