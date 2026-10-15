<?php

namespace NineteenNinetyFour\BackInStock\Tests\Feature;

use Illuminate\Support\Facades\Mail;
use NineteenNinetyFour\BackInStock\Facades\BackInStock;
use NineteenNinetyFour\BackInStock\Mail\ConfirmMail;
use NineteenNinetyFour\BackInStock\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * The Antlers and PHP examples in the docs, run as they're written.
 */
class AntlersDocsTest extends TestCase
{
    /**
     * @return array<int, string>
     */
    protected function blocks(string $doc, string $language): array
    {
        preg_match_all('/```'.$language.'\n(.*?)\n```/s', file_get_contents(__DIR__.'/../../docs/'.$doc), $matches);

        return $matches[1];
    }

    #[Test]
    public function every_antlers_example_in_templates_renders(): void
    {
        $this->book(stock: 0);
        $this->editions(hardback: 0, paperback: 3);
        $this->waiting('a@example.com');
        $user = $this->user('member@example.com');
        $this->asked('member@example.com');
        $this->actingAs($user);
        session()->put('back_in_stock_success', 'Nearly there');

        $blocks = $this->blocks('templates.md', 'antlers');
        $this->assertCount(6, $blocks);

        [$form, $variants, $soldOut, $messages, $alerts, $nocache] = $blocks;

        $html = $this->antlers($form, ['id' => 'lantern-ledger']);
        $this->assertStringContainsString('action="http://localhost/!/back-in-stock/subscribe"', $html);
        $this->assertStringContainsString('value="member@example.com"', $html);

        $html = $this->antlers($variants, ['id' => 'coral-arithmetic']);
        $this->assertStringContainsString('data-back-in-stock-follow="variant"', $html);
        $this->assertStringContainsString('<option value="Hardback" >Hardback</option>', $html);
        $this->assertStringContainsString('back-in-stock.js', $html);

        $this->assertStringContainsString('1 waiting', $this->antlers($soldOut, ['id' => 'lantern-ledger']));
        $this->assertStringContainsString('<p class="flash">Nearly there</p>', $this->antlers($messages));

        $html = $this->antlers($alerts);
        $this->assertStringContainsString('The Lantern Ledger</a> (Unconfirmed)', $html);
        $this->assertStringContainsString('name="alert"', $html);

        $this->assertStringContainsString('{{ nocache }}', $nocache);
    }

    #[Test]
    public function the_readme_example_renders(): void
    {
        $this->book(stock: 0);

        preg_match('/```antlers\n(.*?)\n```/s', file_get_contents(__DIR__.'/../../README.md'), $match);

        $this->assertStringContainsString('name="product" value="lantern-ledger"', $this->antlers($match[1], ['id' => 'lantern-ledger']));
    }

    #[Test]
    public function the_php_api_example_runs(): void
    {
        Mail::fake();
        $this->editions(hardback: 0, paperback: 3);

        $code = $this->blocks('php-api.md', 'php')[0];

        eval($code);

        Mail::assertQueued(ConfirmMail::class);
        $this->assertSame(0, BackInStock::waitingCount('coral-arithmetic', 'Hardback'));
    }
}
