# Troubleshooting

**The form doesn't show.** It only shows while the product (or variant) is sold out and live. Check that the blueprint has a `stock` field (or a `stock` option field on `product_variants`), that the stock is 0 (empty means it isn't counted), and that the entry is published. `product="…"` must be the product's id.

**Nobody was emailed when I put it back in stock.**

- Did anyone confirm? Unconfirmed requests aren't emailed. Store → Back in Stock shows them.
- With a queue: is a worker running (`php artisan queue:work`)? Emails wait for the delay (5 minutes) first.
- Did the stock go up? Saving without a rise does nothing. Store → Back in Stock → the product shows each restock.
- Was the stock changed without saving the entry? `back-in-stock:check` picks it up: is the scheduler running? Run `php please back-in-stock:check` to see.
- With "As many as came back", only that many are emailed.
- Check `storage/logs/laravel.log` for mail errors. Requests whose email failed stay waiting and are tried again.

**Too many emailed.** "Everyone waiting" emails everyone. Choose "As many as came back" in the settings.

**419 Page Expired after sending the form.** The page's CSRF token was out of date. On a statically cached site, Statamic swaps in a fresh token with JavaScript on the full measure: check its script is on the page (see [Templates](templates.md#static-caching)).

**The message doesn't show after sending.** The page came from the static cache. See [Templates](templates.md#static-caching).

**The confirmation link says it's expired.** Links work for 72 hours (`confirm_hours`). Asking again sends a new one.

**Saving a product in the Control Panel says the variant name is required.** Cargo's Variants field always has a row. Give products with variants their own blueprint. See [Installation](installation.md#stock).

**Emails go out in the wrong language.** They use the language of the site the request was made on, from `lang/`. Wording typed in the settings is used for every site: leave it empty to use translations. See [Emails](emails.md#languages).

Still stuck? Email hello@1994.co.uk.
