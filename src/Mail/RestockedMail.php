<?php

namespace NineteenNinetyFour\BackInStock\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;
use NineteenNinetyFour\BackInStock\Cargo\Product;
use NineteenNinetyFour\BackInStock\Models\Alert;
use NineteenNinetyFour\BackInStock\Settings;

/**
 * "It's back in stock": sent once per request, with a one-click
 * unsubscribe (RFC 8058) that mail apps show next to the sender.
 */
class RestockedMail extends Mailable
{
    use Presents, Queueable;

    public function __construct(public Alert $alert, public Product $product) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: Settings::text('restocked_subject', $this->replacements($this->alert, $this->product), $this->locale),
        );
    }

    public function headers(): Headers
    {
        return new Headers(text: [
            'List-Unsubscribe' => '<'.$this->alert->unsubscribeUrl().'>',
            'List-Unsubscribe-Post' => 'List-Unsubscribe=One-Click',
        ]);
    }

    public function content(): Content
    {
        $replace = $this->replacements($this->alert, $this->product);

        return new Content(
            html: config('back-in-stock.views.restocked', 'back-in-stock::emails.restocked'),
            text: config('back-in-stock.views.restocked_text', 'back-in-stock::emails.restocked-text'),
            with: $this->shared($this->alert, $this->product) + [
                'heading' => Settings::text('restocked_heading', $replace, $this->locale),
                'intro' => Settings::text('restocked_intro', $replace, $this->locale),
                'button' => Settings::text('restocked_button', $replace, $this->locale),
                'footer' => Settings::text('restocked_footer', $replace, $this->locale),
                'price' => $this->product->formattedPrice($this->alert->siteObject()),
                'productUrl' => $this->product->url() ?? $this->alert->siteObject()->absoluteUrl(),
                'unsubscribeUrl' => $this->alert->unsubscribeUrl(),
            ],
        );
    }
}
