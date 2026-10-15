# Configuration

## In the Control Panel

Tools → Addons → Back in Stock → Settings. Anything left empty uses `config/back-in-stock.php`, or the default wording in the site's language.

**Sending**

- **Who to email**: everyone waiting, or as many as came back. With "As many as came back", put 3 back in stock and the 3 people who asked first are emailed; the rest keep waiting for the next restock. See [Restocks](restocks.md).
- **Multiplier**: with "As many as came back", email this many people per unit, as not everyone buys.
- **Wait (minutes)**: how long after stock comes back the emails go, so a mistyped stock level can be put right first. Needs a queue.
- **Emails a minute**: the most sent a minute, for mail providers with a limit.

**Form**

- **Consent wording**: next to the tick box, and saved with each request.

**Emails**

- **Logo** and **Brand colour**.
- **The confirmation email**: subject, heading, introduction, button.
- **The back-in-stock email**: subject, heading, introduction, button, small print.

Placeholders in the wording: `:product` (with the variant, "Coral Arithmetic – Hardback") and `:site`. Wording you type here is used for every site. Leave it empty to use the wording in `lang/`, in each site's language (see [Emails](emails.md)).

## `config/back-in-stock.php`

Publish it to change it:

```bash
php artisan vendor:publish --tag=back-in-stock-config
```

| Option | Default | |
| --- | --- | --- |
| `delay` | `5` | Minutes to wait after stock comes back before emailing. Needs a queue. |
| `limit` | `'all'` | `'all'`, or `'stock'`: as many people as units came back. |
| `multiplier` | `1` | With `'stock'`, people emailed per unit. |
| `per_minute` | `120` | The most back-in-stock emails sent a minute. |
| `email.queue` | `null` | The queue emails are sent on. |
| `email.bcc` | `null` | An address that gets a copy of every back-in-stock email. |
| `confirm_hours` | `72` | How long confirmation links work. |
| `pages.confirmed` | `null` | A page on your site to land on after confirming, such as `'/alerts/confirmed'`. Null shows the addon's own page. |
| `pages.unsubscribed` | `null` | The same, after unsubscribing. |
| `conversion_days` | `14` | An order for the product from the same address within this many days of the email counts as bought. `null` turns this off. |
| `retention.unconfirmed` | `7` | Days before unconfirmed requests are deleted. |
| `retention.finished` | `90` | Days before emailed, bought and cancelled requests are deleted. |
| `retention.waiting` | `365` | Days before requests still waiting are deleted. `null` keeps them. |
| `schedule` | `true` | Schedule `back-in-stock:check` (every five minutes) and `back-in-stock:prune` (daily). |
| `throttle.subscribe` | `10` | Requests a minute from one visitor. |
| `throttle.per_email` | `5` | Confirmation emails an hour to one address. |
| `throttle.per_address` | `50` | Open requests one address can have. |
| `honeypot` | `'website'` | The hidden spam field's name. Change it if you have a field called that. |
| `views.*` | | The views the emails and the confirm/unsubscribe page are drawn with. See [Emails](emails.md). |
| `database.connection` | `null` | The database connection for the tables. Null is your default. |

The settings in the Control Panel win over `delay`, `limit`, `multiplier` and `per_minute` once they're saved.
