# PHP API

The `BackInStock` facade does what the forms and the Control Panel do.

```php
use NineteenNinetyFour\BackInStock\Facades\BackInStock;

// A product, or one of its variants.
$product = BackInStock::product('coral-arithmetic', 'Hardback');

$product->isSoldOut();      // true
$product->stock();          // 0, or null if stock isn't counted
$product->name();           // "Coral Arithmetic – Hardback"

// Ask on someone's behalf. They're emailed a link to confirm.
$alert = BackInStock::subscribe($product, 'reader@example.com', consent: 'Asked in the shop.');

BackInStock::waitingCount('coral-arithmetic', 'Hardback'); // confirmed, not yet emailed
BackInStock::cancel($alert);
BackInStock::unsubscribe('reader@example.com');   // cancels all of theirs
BackInStock::export('reader@example.com');        // everything kept, as an array
BackInStock::forget('reader@example.com');        // deletes it
```

`subscribe()` throws `NineteenNinetyFour\BackInStock\BackInStockException` when it can't (not sold out, an invalid address, too many requests): its message is safe to show, and `$e->field` says which field it's about.

The models are Eloquent, in `NineteenNinetyFour\BackInStock\Models`:

- `Item`: a product or variant someone asked about (`product_id`, `variant`, the stock last seen). `->alerts()`, `->restocks()`, `->product()`.
- `Alert`: one request.
- `Restock`: one time it came back.

```php
use NineteenNinetyFour\BackInStock\Models\Alert;

Alert::query()->where('status', 'waiting')->count();
```

To check for restocks yourself (after an import that doesn't save entries, say), run `php please back-in-stock:check`.
