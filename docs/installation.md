# Installation

## Requirements

- PHP 8.3 or later
- Statamic 6.35.1 or later
- Cargo 1.15 or later, before 2.0
- A database: requests are kept in tables, whichever way Statamic and Cargo keep their own content
- Laravel's scheduler running, and ideally a queue

## Install

```bash
composer require 1994/back-in-stock-statamic
php artisan migrate
```

The migration adds four tables, all starting `back_in_stock_`. To use another database connection, set it before migrating (see [Configuration](configuration.md)). To keep the migration in your own app, publish it:

```bash
php artisan vendor:publish --tag=back-in-stock-migrations
```

## Stock

Back in Stock reads stock the way Cargo does:

- **A product in one version**: a `stock` field on the product blueprint (an Integer field).
- **A product with variants**: a `stock` option field on Cargo's `product_variants` field, so each variant has its own.

A product (or variant) whose stock is empty isn't counted, so it never sells out, and the form doesn't show for it. See [Cargo's docs on stock](https://builtwithcargo.dev/docs/products).

> **One blueprint per kind of product.** Cargo's Variants field always has a row, and its fields are required, so a product blueprint with both a price and Variants can't be saved for a product in one version. Give products with variants their own blueprint (the [demo](demo.md) has "Book" and "Book with editions").

## The scheduler and the queue

Back in Stock schedules two commands:

- `back-in-stock:check`, every five minutes: catches stock that changed without the product being saved (an import writing files, a deploy), and sends anything left over.
- `back-in-stock:prune`, daily: deletes old requests (see [Privacy](privacy.md)).

If your site doesn't run Laravel's scheduler yet, add the cron entry from [Laravel's docs](https://laravel.com/docs/scheduling#running-the-scheduler):

```bash
* * * * * cd /path-to-your-site && php artisan schedule:run >> /dev/null 2>&1
```

To schedule them yourself instead, set `'schedule' => false` in the config.

**A queue is recommended.** With one (any driver but `sync`), emails go a few minutes after stock comes back, so a mistyped stock level can be put right first, and sending hundreds of emails never slows the Control Panel. Without one, they're sent straight after the page that saved the product has loaded, and the delay setting is ignored.

## Emails

Back in Stock sends with your site's mailer (`MAIL_MAILER`). Set a real "from" address (`MAIL_FROM_ADDRESS`, `MAIL_FROM_NAME`): the emails come from your shop.

## Publishing

| Tag | What |
| --- | --- |
| `back-in-stock-config` | `config/back-in-stock.php` |
| `back-in-stock-views` | The emails and the confirm/unsubscribe page, to `resources/views/vendor/back-in-stock` |
| `back-in-stock-translations` | The wording, to `lang/vendor/back-in-stock` |
| `back-in-stock-migrations` | The migration |

```bash
php artisan vendor:publish --tag=back-in-stock-config
```

## Licensing

Back in Stock is commercial software, sold on the [Statamic Marketplace](https://statamic.com/addons). It's free to try on local and staging sites. Buy a licence for each live site, and add it in the [Statamic Marketplace](https://statamic.com/account) as you would any addon. See [LICENSE.md](../LICENSE.md).

## Updating

```bash
composer update 1994/back-in-stock-statamic
php artisan migrate
```

Read the [changelog](../CHANGELOG.md) first.
