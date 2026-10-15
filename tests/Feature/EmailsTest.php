<?php

namespace NineteenNinetyFour\BackInStock\Tests\Feature;

use Illuminate\Support\Facades\Mail;
use NineteenNinetyFour\BackInStock\BackInStock;
use NineteenNinetyFour\BackInStock\Mail\ConfirmMail;
use NineteenNinetyFour\BackInStock\Mail\RestockedMail;
use NineteenNinetyFour\BackInStock\Models\Alert;
use NineteenNinetyFour\BackInStock\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;
use Statamic\Facades\Addon;
use Statamic\Facades\Site;

class EmailsTest extends TestCase
{
    #[Test]
    public function the_confirmation_email_has_the_link_and_names_the_product(): void
    {
        $this->editions();
        $alert = $this->asked('reader@example.com', 'coral-arithmetic', 'Hardback');

        $mail = new ConfirmMail($alert);
        $mail->assertHasSubject('Confirm your back-in-stock alert for Coral Arithmetic – Hardback');
        $mail->assertSeeInHtml('You asked Dog-ear Books to let you know when Coral Arithmetic – Hardback is back in stock.', false);
        $mail->assertSeeInHtml(e($alert->confirmUrl()), false);
        $mail->assertSeeInText('Yes, email me: http://localhost/!/back-in-stock/confirm/'.$alert->id);
        $mail->assertSeeInText('The link works for 72 hours.');
    }

    #[Test]
    public function the_back_in_stock_email_links_to_the_product_with_its_price_and_unsubscribe(): void
    {
        $this->editions();
        $alert = $this->waiting('reader@example.com', 'coral-arithmetic', 'Hardback');
        $product = $alert->item->product();

        $mail = new RestockedMail($alert, $product);
        $mail->assertHasSubject('Coral Arithmetic – Hardback is back in stock');
        $mail->assertSeeInHtml('http://localhost/books/coral-arithmetic', false);
        $mail->assertSeeInHtml('£18.00');
        $mail->assertSeeInHtml('Hardback');
        $mail->assertSeeInHtml(e($alert->unsubscribeUrl()), false);
        $mail->assertSeeInText('Take a look: http://localhost/books/coral-arithmetic');

        $headers = $mail->headers()->text;
        $this->assertSame('<'.$alert->unsubscribeUrl().'>', $headers['List-Unsubscribe']);
        $this->assertSame('List-Unsubscribe=One-Click', $headers['List-Unsubscribe-Post']);
    }

    #[Test]
    public function the_wording_comes_from_the_settings(): void
    {
        Addon::get('1994/back-in-stock-statamic')->settings()->set([
            'restocked_subject' => 'Back on the shelf: :product',
            'restocked_intro' => '<b>:product</b> is back at :site.',
            'accent_colour' => '#7f1d1d',
        ])->save();

        $this->book(stock: 0);
        $alert = $this->waiting();

        $mail = new RestockedMail($alert, $alert->item->product());
        $mail->assertHasSubject('Back on the shelf: The Lantern Ledger');
        // Escaped: settings are text, not HTML.
        $mail->assertSeeInHtml('&lt;b&gt;The Lantern Ledger&lt;/b&gt; is back at Dog-ear Books.', false);
        $mail->assertSeeInHtml('background:#7f1d1d', false);
    }

    #[Test]
    public function product_titles_are_escaped(): void
    {
        $this->book('odd', stock: 0, data: ['title' => '<script>alert(1)</script>']);
        $alert = $this->waiting('a@example.com', 'odd');

        $html = (new RestockedMail($alert, $alert->item->product()))->render();

        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }

    #[Test]
    public function emails_go_in_the_language_of_the_site_asked_on(): void
    {
        Site::setSites([
            'default' => ['name' => 'Dog-ear Books', 'url' => 'http://localhost/', 'locale' => 'en_GB', 'attributes' => ['currency' => 'GBP']],
            'fr' => ['name' => 'Livres Corne', 'url' => 'http://localhost/fr/', 'locale' => 'fr_FR', 'attributes' => ['currency' => 'EUR']],
        ])->save();

        app('translator')->addLines(['defaults.restocked_subject' => ':product est de retour'], 'fr', 'back-in-stock');
        app('translator')->addLines(['messages.defaults.restocked_subject' => ':product est de retour'], 'fr', 'back-in-stock');

        $book = $this->book(stock: 0);
        $book->makeLocalization('fr')->id('lantern-ledger-fr')->set('title', 'Le Grand Livre')->save();

        $backInStock = app(BackInStock::class);
        $alert = $backInStock->subscribe($backInStock->product('lantern-ledger-fr'), 'lecteur@example.com', Site::get('fr'));
        $backInStock->confirm($alert);
        $this->assertSame('fr', $alert->refresh()->locale);

        Mail::fake();
        $this->restock('lantern-ledger', 2);

        Mail::assertSent(RestockedMail::class, function (RestockedMail $mail) {
            $mail->assertHasSubject('Le Grand Livre est de retour');

            return $mail->locale === 'fr' && $mail->hasTo('lecteur@example.com');
        });
    }

    #[Test]
    public function a_copy_can_go_to_the_shop(): void
    {
        config(['back-in-stock.email.bcc' => 'shop@example.com']);
        $this->book(stock: 0);
        $this->waiting();
        Mail::fake();

        $this->restock('lantern-ledger', 1);

        Mail::assertSent(RestockedMail::class, fn ($mail) => $mail->hasBcc('shop@example.com'));
    }

    #[Test]
    public function the_confirmation_is_sent_in_the_site_language(): void
    {
        Mail::fake();
        $this->book(stock: 0);
        $this->asked();

        Mail::assertQueued(ConfirmMail::class, fn (ConfirmMail $mail) => $mail->locale === 'en');
        $this->assertSame('en', Alert::sole()->locale);
    }
}
