# Tags

Every tag escapes what it gives you. See [Templates](templates.md) for them in use, in Antlers and Blade.

## `{{ back_in_stock }}`

The "Tell me when it's back" form. A tag pair: the form's fields go inside. Shows nothing unless the product (or variant) is sold out and live.

| Parameter | |
| --- | --- |
| `product` | The product's id. Defaults to the entry in context. |
| `variant` | A variant's key, for a product with variants. Leave it out to choose from `{{ variants }}`. |
| `follow` | The name of the page's own variant choice (`variant`) for the form to follow. Needs `{{ back_in_stock:scripts }}`. |
| `redirect` | Where to go after sending. Defaults to the page it was on. |
| `error_redirect` | Where to go when something's wrong. Defaults to the page it was on. |
| `personal` | `true` to fill in the signed-in customer's email on a statically cached site (inside `{{ nocache }}`). |
| anything else | An attribute on the `<form>`: `class="notify"`, `id="…"`. |

| Variable | |
| --- | --- |
| `product_id` | The product's id. |
| `title` | The product's title. |
| `variant`, `variant_name` | The variant's key and name, when one was given. |
| `has_variants` | True when the shopper chooses a variant from `variants`. |
| `variants` | The sold-out variants: `key`, `name`, `selected` (chosen before the form came back). |
| `email` | What was typed, or the signed-in customer's email. |
| `signed_in` | Whether a customer is signed in. |
| `consent_text` | The wording next to the tick box, from the settings. |
| `consent_checked` | Whether the box was ticked before the form came back with errors. |
| `success` | The message after sending, for this form only. |
| `errors` | Error messages by field (`errors:email`, `errors:consent`, `errors:variant`, `errors:product`), for this form only. `errors:back_in_stock` is the first. |
| `waiting` | How many people are waiting (confirmed). |

The form posts `email`, `consent` (`1` when ticked), and `variant` when the shopper chooses one. It adds `product`, `_token` and a hidden honeypot field itself.

## `{{ back_in_stock:sold_out }}`

True when the product (or `variant="…"`) is sold out: Cargo counts its stock, and it's 0 or less. On a product with variants and no variant given, true when any variant is. Takes `product` and `variant`.

## `{{ back_in_stock:count }}`

How many people are waiting for the product (or `variant="…"`): confirmed requests not yet emailed. Without `variant` on a product with variants, all its variants together.

## `{{ back_in_stock:subscribed }}`

True when the signed-in customer has an open request for the product (or `variant="…"`). Personal: see `personal` above.

## `{{ back_in_stock:alerts }}`

The signed-in customer's open requests, newest first. A tag pair, or a loop. Personal.

| Parameter | |
| --- | --- |
| `limit` | At most this many (100). |
| `personal` | As above. |

| Variable | |
| --- | --- |
| `id` | The request's id, for `{{ back_in_stock:cancel }}`. |
| `product_id`, `variant` | The product's id and variant key. |
| `title`, `variant_name`, `name` | The product's title, the variant's name, and both together ("Coral Arithmetic – Hardback"). |
| `url` | The product's page. |
| `status`, `status_label` | `unconfirmed` or `waiting`, and its label. |
| `is_unconfirmed`, `is_waiting` | |
| `created_at` | When they asked. |

## `{{ back_in_stock:cancel }}`

A form with a button that cancels one of the signed-in customer's requests: `{{ back_in_stock:cancel :alert="id" }}<button>Cancel</button>{{ /back_in_stock:cancel }}`. Takes `alert`, `redirect` and `error_redirect`.

## `{{ back_in_stock:success }}` and `{{ back_in_stock:error }}`

The message after a form post, confirming or unsubscribing; and what went wrong (`field="email"` for one field's).

## `{{ back_in_stock:scripts }}`

The script tag for `follow`. It's loaded with `defer`, from `/!/back-in-stock/back-in-stock.js`.
