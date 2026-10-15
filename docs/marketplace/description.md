**"Tell me when it's back" for Cargo. Shoppers leave their email on a sold-out product, or a sold-out variant, and confirm it. When it's back in stock, they get one email. You see what people are waiting for, and who bought when it came back.**

## A form for sold-out products and variants

One tag, your markup. It shows only while it's sold out, works without JavaScript and on statically cached pages, and can follow the size, colour or edition chosen on the page.

## Confirmed by email

Every request is confirmed with a button on the linked page, so nobody can sign someone else up, and mail apps' link checkers can't confirm for them. A consent tick box records the wording people agreed to.

## Restocks noticed however they happen

Stock changed in the Control Panel, put back by a cancelled order, written by an import: Back in Stock notices, and emails people, oldest request first.

## Never twice, never too many

Each request is emailed once. Email everyone, or as many people as units came back. A short wait lets you fix a mistyped stock level, and a per-minute limit keeps your mail provider happy.

## One-click unsubscribe

In the email, and in the headers mail apps use.

## Demand in the Control Panel

**Store → Back in Stock**: the most wanted sold-out products, who's waiting, each restock, and how many bought after the email. A dashboard widget and CSV download.

## Privacy

Find, download or delete everything about an address. Old requests are deleted on a schedule. No IP addresses kept.

## Multisite

Emails go in the language of the site people asked on, with the right product link.

## Spam protection

A honeypot field, and limits per visitor and per address.

## Yours to change

Wording, logo and colours in the Control Panel. Publish the emails to change anything else. Events, a PHP API and a JSON API.

## Requirements

- Statamic 6.35.1 or later
- **Cargo 1.15 or later**
- PHP 8.3 or later, and a database (SQLite is fine)
- Laravel's scheduler running. A queue is recommended.
