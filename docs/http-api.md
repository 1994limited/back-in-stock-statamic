# HTTP API

The form posts to these, so you can send it with `fetch()` too. Send `Accept: application/json` for a JSON answer; without it, each redirects back.

## `POST /!/back-in-stock/subscribe`

| Field | |
| --- | --- |
| `product` | The product's id. |
| `variant` | A variant's key, for a product with variants. |
| `email` | Their email. Optional when signed in. |
| `consent` | `1`. Required. |
| `_token` | The CSRF token. |

```js
const response = await fetch('/!/back-in-stock/subscribe', {
    method: 'POST',
    headers: { Accept: 'application/json' },
    body: new FormData(form),
});

const answer = await response.json();
// { success: true, message: "Nearly there: we’ve emailed you a link…" }
// or, with status 422: { success: false, message: "…", errors: { email: "…" } }
```

Statuses: `200`; `404` for a product that isn't live; `422` for anything else wrong; `429` when there have been too many tries (from this visitor, or to this address).

## `POST /!/back-in-stock/cancel`

A signed-in customer cancelling one of their requests: `alert` (its id), `_token`.

## The links in the emails

`GET /!/back-in-stock/confirm/{id}` and `GET /!/back-in-stock/unsubscribe/{id}` are signed links: changing them stops them working. Each opens a page whose button posts to the same address. The unsubscribe address also takes mail apps' one-click unsubscribe (`POST`, `List-Unsubscribe=One-Click`).

## `GET /!/back-in-stock/back-in-stock.js`

The optional script, for `follow` (see [Templates](templates.md#variants)).
