<?php

namespace NineteenNinetyFour\BackInStock\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use NineteenNinetyFour\BackInStock\Models\Alert;
use NineteenNinetyFour\BackInStock\Settings;

/**
 * "Confirm your back-in-stock alert": the link that makes a request count.
 * The wording comes from the settings; the views can be published.
 */
class ConfirmMail extends Mailable
{
    use Presents, Queueable, SerializesModels;

    public function __construct(public Alert $alert) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: Settings::text('confirm_subject', $this->replacements($this->alert, $this->alert->item?->product()), $this->locale),
        );
    }

    public function content(): Content
    {
        $product = $this->alert->item?->product();
        $replace = $this->replacements($this->alert, $product);

        return new Content(
            html: config('back-in-stock.views.confirm', 'back-in-stock::emails.confirm'),
            text: config('back-in-stock.views.confirm_text', 'back-in-stock::emails.confirm-text'),
            with: $this->shared($this->alert, $product) + [
                'heading' => Settings::text('confirm_heading', $replace, $this->locale),
                'intro' => Settings::text('confirm_intro', $replace, $this->locale),
                'button' => Settings::text('confirm_button', $replace, $this->locale),
                'ignore' => Settings::text('confirm_ignore', $replace, $this->locale),
                'confirmUrl' => $this->alert->confirmUrl(),
                'hours' => max(1, (int) config('back-in-stock.confirm_hours', 72)),
            ],
        );
    }
}
