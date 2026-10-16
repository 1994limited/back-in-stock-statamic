# Restocks

## How stock coming back is noticed

Back in Stock remembers the stock of everything someone is waiting for. It's checked:

- **every time the product is saved**: in the Control Panel; by Cargo, when an order is cancelled and its stock put back; by an import or your own code that saves the entry;
- **every five minutes**, by `back-in-stock:check`, for stock changed without saving the entry: files edited by hand or by a deploy, an import that writes files, `saveQuietly()`.

When it's higher than it was, and the product is live (published, and not scheduled or expired), that's a restock. It's recorded (shown on the product's page in the Control Panel), the `ProductRestocked` [event](events.md) fires, and emails are lined up.

Stock that stops being counted (the stock field emptied) counts as back in stock too.

**Refunds and returns.** Cargo puts stock back when an order is cancelled, not when it's refunded or returned. If returned items go back on the shelf, change the stock in the Control Panel and Back in Stock takes it from there.

## Who's emailed

People are emailed oldest request first (by when they confirmed), once each. With **Who to email** set to:

- **Everyone waiting** (the default): everyone who has confirmed.
- **As many as came back**: as many as units came back, times the multiplier. Put 3 back in stock with a multiplier of 2, and the 6 people who asked first are emailed. The rest keep waiting. When more comes back, the next ones are emailed. Nobody's emailed when stock goes down, as items sell, and units sold before the emails go come off: put 5 back, sell 3 during the wait, and 2 people are emailed.

Someone who confirms while it's already in stock (the page they asked on was out of date) sets the next email off straight away. With "Everyone waiting", that's to everyone, them included. With "As many as came back", it's to whoever has waited longest, while there's stock for them.

**Email them now.** On a product's page in the Control Panel, "Email them now" emails everyone waiting, whatever the setting.

## When

- **With a queue**, emails go after the delay (5 minutes by default). Then the stock is checked again: if it's sold out again (a mistyped stock level put right), nobody's emailed. If the product was unpublished, nobody's emailed either.
- **Without a queue** (the `sync` driver), there's no delay: emails go once the page that saved the product has loaded, up to 500 at a time. Any more go with the next `back-in-stock:check`.

No more than the **Emails a minute** setting go each minute. The rest go in the following minutes: from the queue, or from `back-in-stock:check` without one. A shop with 500 people waiting on a mail provider allowing 100 a minute sends them over five minutes.

## Never twice

Each request is emailed once, then it's done. Before an email goes, its request is claimed, so two sends at once (a save and the scheduled check, say) can't both send it. If the email can't be sent (the mail server is down), the request goes back to waiting, and it's tried again later. A send that never finished (the server stopped) puts its requests back to waiting after an hour.

Addresses that [unsubscribed](emails.md#unsubscribing) aren't emailed.

## Bought

When someone who was emailed orders the product (the same variant, from the same email address) within 14 days, their request is marked bought. The Control Panel shows how many bought after the email. Change the days, or turn it off, with `conversion_days` in the [config](configuration.md).
