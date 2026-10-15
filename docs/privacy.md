# Privacy

Back in Stock keeps as little as it needs to, and gives you what you need to answer people. This isn't legal advice: check what your own privacy notice says.

## Consent

The form has a tick box, which is required, next to wording you set (Tools → Addons → Back in Stock → Settings → Form). Each request keeps the wording the person agreed to, and when. Every request is confirmed from an email, so you have a record that the address's owner asked (double opt-in).

## What's kept

For each request: the email address, the product (and variant), the site, the signed-in customer's id (when they're signed in and it's their own email), the consent wording, and when it was asked, confirmed, emailed, bought (with the order's id) or cancelled. No IP addresses, names or anything else.

Addresses that unsubscribe are kept as a one-way hash (SHA-256), so they aren't emailed again.

## How long

`back-in-stock:prune` runs daily, and deletes:

| Requests | After |
| --- | --- |
| Never confirmed | 7 days |
| Emailed, bought or cancelled | 90 days |
| Still waiting | 365 days |

Change these with `retention` in the [config](configuration.md). `null` keeps them.

## Someone asks what you keep

In the Control Panel: Store → Back in Stock → **Find someone**, enter their email address, and **Download their data** (JSON). Or from the command line:

```bash
php please back-in-stock:forget someone@example.com --export
```

## Someone asks to be forgotten

**Delete everything**, on the same page, or:

```bash
php please back-in-stock:forget someone@example.com
```

This deletes all their requests, and the unsubscribed hash if there is one.

## A customer's own requests

Signed-in customers see their requests on an account page with `{{ back_in_stock:alerts }}`, and cancel them with `{{ back_in_stock:cancel }}` (see [Templates](templates.md#a-customers-alerts)). Anyone can unsubscribe from all back-in-stock emails with the link in each one.
