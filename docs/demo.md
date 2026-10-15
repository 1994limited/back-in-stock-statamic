# The demo shop

[Dog-ear Books with Cargo](https://github.com/1994limited/back-in-stock-statamic-demo) is a small bookshop built with Back in Stock and Cargo. Some books, and some editions, are sold out.

It shows:

- the form on a sold-out book, without JavaScript;
- a book in hardback and paperback where only the hardback is sold out, with the form following the edition chosen in the basket form (`follow="variant"`);
- "Sold out" on the shelves with `{{ back_in_stock:sold_out }}`;
- a reader's alerts page with `{{ back_in_stock:alerts }}`;
- Store → Back in Stock and the dashboard widget, with people already waiting (`php artisan demo:requests`).

Its README says how to run it, and has things to try. Every template is short, and there to be copied.
