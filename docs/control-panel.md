# The Control Panel

## Store → Back in Stock

What people are waiting for, most wanted first: each product or variant, its stock now, how many are waiting, how many haven't confirmed yet, how many were emailed, and how many bought after the email. A demand signal: these are the products to restock first.

At the top: everyone waiting, and the last 30 days' emails and sales. Search by product, or show everything anyone has asked about. **Download CSV** downloads every request.

Click a product to see:

- **Who's waiting**, in the order they'll be emailed, with each request's status. Search by email, or filter by status. Cancel or delete a request.
- **Restocks**: each time it came back, the stock before and after, and how many were emailed.
- **Email them now**, while it's in stock, to email everyone waiting straight away.

**Find someone**, at the bottom of the list, shows everything kept about one email address, to download or delete. See [Privacy](privacy.md).

## The dashboard widget

"Wanted while sold out": the sold-out products and variants with the most people waiting. Add it to `config/statamic/cp.php`:

```php
'widgets' => [
    ['type' => 'back_in_stock', 'width' => 50],
],
```

Options: `title`, `limit` (5). It sits well next to Cargo's own `low_stock_products` widget.

## Settings

Tools → Addons → Back in Stock → Settings: who to email, the wait, emails a minute, the consent wording, and the emails. See [Configuration](configuration.md).

## Permissions

| Permission | Lets them |
| --- | --- |
| View back-in-stock requests | See Store → Back in Stock and the widget |
| Send, cancel and delete requests | Email them now, cancel and delete requests, delete everything about an address |
| Download requests, and people's data | Download CSV, and download an address's data |

Super users can do everything.
