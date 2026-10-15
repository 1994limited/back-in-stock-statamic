# Back in Stock documentation

Back in Stock adds "Tell me when it's back" to [Cargo](https://builtwithcargo.dev), Statamic's e-commerce addon. Shoppers leave their email on a sold-out product, or a sold-out variant, and confirm it from an email. When it's back in stock, they get one email, oldest request first. You see what people are waiting for in the Control Panel.

## Setting up

1. [Installation](installation.md): requirements, installing, the scheduler and queue, licensing, updating.
2. [Configuration](configuration.md): the settings in the Control Panel, and every option in `config/back-in-stock.php`.

## Building the front end

3. [Templates](templates.md): the form, variants, messages, a customer's alerts, and static caching. Start here.
4. [Tags](tags.md): every `{{ back_in_stock }}` tag, its parameters and variables.
5. [The demo shop](demo.md): Dog-ear Books, a shop built with Back in Stock.

## Running a shop

6. [Restocks](restocks.md): how stock coming back is noticed, and who's emailed when.
7. [Emails](emails.md): the confirmation and back-in-stock emails, unsubscribing, languages, and testing.
8. [The Control Panel](control-panel.md): Store → Back in Stock, the dashboard widget, and permissions.
9. [Privacy](privacy.md): consent, what's kept and for how long, and answering requests for someone's data.

## Reference

10. [Events](events.md)
11. [PHP API](php-api.md)
12. [HTTP API](http-api.md): the `/!/back-in-stock/…` endpoints.
13. [Troubleshooting](troubleshooting.md)

## Requirements

- PHP 8.3 or later.
- Statamic 6.35.1 or later, and Cargo 1.15 or later (before 2.0).
- A database for requests (SQLite, MySQL, PostgreSQL or SQL Server), whichever way Statamic and Cargo keep their own content.
- Laravel's scheduler running. A queue is recommended.
- Products with stock: a `stock` field on the product blueprint, or a `stock` option field on Cargo's `product_variants`.
