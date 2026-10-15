# Events

Listen to these to sync requests to your mailing list, post to Slack when something's wanted, or count restocks. All are in `NineteenNinetyFour\BackInStock\Events`.

| Event | When | Properties |
| --- | --- | --- |
| `AlertCreated` | Someone asked (before confirming) | `$alert` |
| `AlertConfirmed` | They confirmed from the email | `$alert` |
| `AlertCancelled` | Cancelled: by them, by unsubscribing, or in the Control Panel | `$alert` |
| `ProductRestocked` | Something people are waiting for came back in stock | `$item`, `$restock` |
| `AlertNotified` | They were emailed that it's back | `$alert` |
| `AlertConverted` | They bought it after the email | `$alert` |

`$alert` is a `NineteenNinetyFour\BackInStock\Models\Alert`: `email`, `product_id`, `variant`, `site`, `status`, `consent`, and the times (`confirmed_at`, `notified_at`, `converted_at`, `order_id`…). `$alert->item->product()` is the product (see the [PHP API](php-api.md)).

`$restock` has `stock_before` and `stock_after`.

```php
use Illuminate\Support\Facades\Event;
use NineteenNinetyFour\BackInStock\Events\AlertConfirmed;

Event::listen(function (AlertConfirmed $event) {
    // Add $event->alert->email to your list, with their consent ($event->alert->consent).
});
```

Only email people from these events with their consent: they asked for one email about one product.
