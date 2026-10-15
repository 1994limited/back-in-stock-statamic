# Changelog

## 1.0.0 (unreleased)

The first release.

### Added
- **The "Tell me when it's back" form** (`{{ back_in_stock }}`) for sold-out Cargo products and variants. Shows only while sold out. Works without JavaScript and on statically cached pages. A consent tick box, with the wording kept on each request. The signed-in customer's email filled in.
- **Variants**: a variant given in the template, a choice of the sold-out ones, or (with `follow="variant"` and `{{ back_in_stock:scripts }}`) the variant chosen in the page's basket form.
- **Confirmed by email**, always: the link opens a page whose button confirms, so link checkers can't. Links last 72 hours.
- **Restocks noticed** on every save of a product (the Control Panel, Cargo putting stock back for a cancelled order, imports) and every five minutes by `back-in-stock:check` (stock changed without saving). Only for live products.
- **Sending**: oldest request first, once each, claimed so two sends can't both email it. Everyone waiting, or as many as units came back (times a multiplier). A wait before sending (with a queue), stock checked again when the emails go, and a per-minute limit. Failed emails tried again.
- **The back-in-stock email** with the product, variant, price and link, in the language of the site asked on, and one-click unsubscribe (`List-Unsubscribe`, RFC 8058).
- **Bought after the email**: orders for the product from the same address within 14 days.
- **A customer's alerts** (`{{ back_in_stock:alerts }}`, `{{ back_in_stock:cancel }}`), `{{ back_in_stock:sold_out }}`, `{{ back_in_stock:count }}`, `{{ back_in_stock:subscribed }}`, `{{ back_in_stock:success }}` and `{{ back_in_stock:error }}`.
- **Control Panel**: Store → Back in Stock, most wanted first, with each product's requests and restocks, "Email them now", cancelling and deleting, CSV, and everything about one address to download or delete. A "Wanted while sold out" dashboard widget. Permissions.
- **Settings** in the Control Panel: who to email, the wait, emails a minute, the consent wording, and the emails' wording, logo and colour.
- **Privacy**: requests deleted after set times (`back-in-stock:prune`, daily), `back-in-stock:forget` to export or delete an address, unsubscribed addresses kept only as a hash.
- **Spam protection**: a honeypot field, limits per visitor, confirmation emails per address, and open requests per address.
- **Events**, a **PHP API** (the `BackInStock` facade) and a **JSON API**.
