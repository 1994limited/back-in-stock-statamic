<?php

namespace NineteenNinetyFour\BackInStock\Tests\Feature;

use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;
use NineteenNinetyFour\BackInStock\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

/**
 * The Blade examples in the docs, rendered for real. Each file in
 * tests/__fixtures__/views/docs is in the docs word for word.
 */
class BladeDocsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        view()->addNamespace('docs', __DIR__.'/../__fixtures__/views/docs');
    }

    protected function render(string $view, array $data = []): string
    {
        return view("docs::{$view}", $data)->render();
    }

    public static function examples(): array
    {
        return collect(glob(__DIR__.'/../__fixtures__/views/docs/*.blade.php'))
            ->mapWithKeys(fn ($path) => [basename($path) => [$path]])
            ->all();
    }

    #[Test]
    #[DataProvider('examples')]
    public function each_example_is_in_the_docs(string $path): void
    {
        $docs = collect([...glob(__DIR__.'/../../docs/*.md'), __DIR__.'/../../README.md'])->map(fn ($file) => file_get_contents($file))->implode("\n");

        $this->assertStringContainsString(trim(file_get_contents($path)), $docs, basename($path).' is not in the docs word for word.');
    }

    #[Test]
    public function the_readme_example(): void
    {
        $this->book(stock: 0);

        $html = $this->render('readme', ['id' => 'lantern-ledger']);

        $this->assertStringContainsString('name="product" value="lantern-ledger"', $html);
        $this->assertStringContainsString('Email me when it\'s back', $html);
    }

    #[Test]
    public function the_form(): void
    {
        $this->book(stock: 0, data: ['title' => "O'Brien's Ledger"]);

        $html = $this->render('form', ['id' => 'lantern-ledger']);

        $this->assertStringContainsString('action="http://localhost/!/back-in-stock/subscribe"', $html);
        $this->assertStringContainsString('class="notify"', $html);
        $this->assertStringContainsString('name="product" value="lantern-ledger"', $html);
        $this->assertStringContainsString('Email me once when it’s back in stock. Nothing else.', $html);
        $this->assertStringContainsString('name="website"', $html);
    }

    #[Test]
    public function the_form_with_errors_and_after_sending(): void
    {
        $this->book(stock: 0);
        session()->put('back_in_stock_item', 'lantern-ledger|');
        session()->put('errors', (new ViewErrorBag)->put('back_in_stock', new MessageBag(['email' => 'Enter a valid email address.'])));
        session()->flashInput(['email' => "o'brien@"]);

        $html = $this->render('form', ['id' => 'lantern-ledger']);
        $this->assertStringContainsString('<p class="error">Enter a valid email address.</p>', $html);
        $this->assertStringContainsString('value="o&#039;brien@"', $html);

        session()->forget('errors');
        session()->put('back_in_stock_success', 'Nearly there');
        $this->assertStringContainsString('<p>Nearly there</p>', $this->render('form', ['id' => 'lantern-ledger']));
    }

    #[Test]
    public function the_variant_form(): void
    {
        $this->editions(hardback: 0, paperback: 3);

        $html = $this->render('variants', ['id' => 'coral-arithmetic']);

        $this->assertStringContainsString('data-back-in-stock-follow="variant"', $html);
        $this->assertStringContainsString('<option value="Hardback" >Hardback</option>', str_replace('  ', ' ', $html));
        $this->assertStringNotContainsString('Paperback', $html);
        $this->assertStringContainsString('back-in-stock.js?v=', $html);
    }

    #[Test]
    public function sold_out_and_count(): void
    {
        $this->book('out', stock: 0);
        $this->book('in', stock: 2);
        $this->waiting('a@example.com', 'out');

        $this->assertStringContainsString('1 waiting', $this->render('sold-out', ['id' => 'out']));
        $this->assertSame('', trim($this->render('sold-out', ['id' => 'in'])));
    }

    #[Test]
    public function a_customers_alerts(): void
    {
        $this->book(stock: 0);
        $user = $this->user('member@example.com');
        $alert = $this->asked('member@example.com');
        $this->actingAs($user);

        $html = $this->render('alerts');

        $this->assertStringContainsString('>The Lantern Ledger</a> (Unconfirmed)', $html);
        $this->assertStringContainsString('Check your inbox to confirm.', $html);
        $this->assertStringContainsString('name="alert" value="'.$alert->id.'"', $html);
        $this->assertStringContainsString('href="http://localhost/books/lantern-ledger"', $html);
    }

    #[Test]
    public function messages(): void
    {
        session()->put('back_in_stock_success', "You're on the list");

        $this->assertStringContainsString('<p class="flash">You&#039;re on the list</p>', $this->render('messages'));
    }
}
