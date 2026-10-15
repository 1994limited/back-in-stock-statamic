# Emails

Two emails, both from your site's mailer:

1. **The confirmation email**, as soon as someone asks. Its link opens a page with a "Yes, email me" button: only the button confirms, so mail apps and virus checkers that open links can't. The link works for 72 hours (`confirm_hours`). "Didn't ask? Ignore this email."
2. **The back-in-stock email**, once, when it's back: the product, the variant, the price, a button to the product's page, and an unsubscribe link.

Both are HTML with a plain-text version, laid out with tables and inline styles so they look right in every mail app. The confirmation email is queued if you have a queue; the back-in-stock emails are always sent from a queued job (see [Restocks](restocks.md)).

## Wording and look

Change the wording, logo and brand colour in Tools → Addons → Back in Stock → Settings (see [Configuration](configuration.md)). Placeholders: `:product` and `:site`. What you type is text, not HTML: it's escaped.

## Languages

Each email is sent in the language of the site the request was made on, with the product's title and link from that site. The wording comes from `lang/en/messages.php`. To translate it, publish the translations and add a folder for each language:

```bash
php artisan vendor:publish --tag=back-in-stock-translations
```

Then copy `lang/vendor/back-in-stock/en/messages.php` to `lang/vendor/back-in-stock/fr/messages.php` (say), and translate it. Wording typed in the settings is used for every site: leave it empty to use the translations.

## Changing the emails

Publish the views and edit them:

```bash
php artisan vendor:publish --tag=back-in-stock-views
```

| View | |
| --- | --- |
| `emails/layout.blade.php` | The frame both emails share: logo, brand colour, footer. |
| `emails/confirm.blade.php`, `emails/confirm-text.blade.php` | The confirmation email. |
| `emails/restocked.blade.php`, `emails/restocked-text.blade.php` | The back-in-stock email. |
| `page.blade.php` | The page after the links in the emails: confirm, confirmed, unsubscribe. |

In the back-in-stock email, `$product->entry` is Cargo's product entry, so its other fields (an image, say) are `$product->entry->value('image')`. Or point `views.*` in the config at views of your own.

To land on a page of your own after confirming or unsubscribing, set `pages.confirmed` and `pages.unsubscribed` in the config. The message is in `{{ back_in_stock:success }}`.

## Unsubscribing

Every back-in-stock email has an unsubscribe link, and the headers mail apps use for their own unsubscribe button (`List-Unsubscribe`, with one-click unsubscribing as in RFC 8058, which Gmail and Yahoo ask bulk senders for). Unsubscribing cancels all that address's open requests. The address is remembered (as a one-way hash, not the address itself) so it isn't emailed again, until it asks again and confirms.

## Testing

Use a local mail catcher such as [HELO](https://usehelo.com), Mailpit or Mailtrap, and set `MAIL_MAILER=smtp` to it. Then:

1. Set a product's stock to 0, and ask about it on its page.
2. Open the confirmation email and confirm.
3. Set its stock to 1 or more and save. With a queue, run `php artisan queue:work`; the email goes after the delay (set **Wait** to 0 while testing).

Or with `MAIL_MAILER=log`, the emails are written to `storage/logs/laravel.log`.
