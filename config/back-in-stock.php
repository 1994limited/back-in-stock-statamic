<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Sending
    |--------------------------------------------------------------------------
    |
    | When a sold-out product or variant comes back, the people waiting for
    | it are emailed, oldest request first.
    |
    | delay: minutes to wait after stock comes back before emailing, so a
    | mistyped stock level can be put right first. Stock is checked again
    | when the emails go. Needs a queue (any but "sync") or the scheduler.
    |
    | limit: who's emailed when stock comes back.
    |   'all'   – everyone waiting.
    |   'stock' – as many people as units came back, times the multiplier
    |             below. Everyone else keeps waiting for the next restock.
    |
    | per_minute: the most back-in-stock emails sent a minute, for mail
    | providers with a limit. The rest go in the following minutes.
    |
    | These three can also be changed in the Control Panel:
    | Tools → Addons → Back in Stock → Settings.
    |
    */

    'delay' => 5,

    'limit' => 'all',

    'multiplier' => 1,

    'per_minute' => 120,

    /*
    |--------------------------------------------------------------------------
    | Emails
    |--------------------------------------------------------------------------
    |
    | queue: the queue connection's queue the emails are sent on (null for
    | the default). Back-in-stock emails are always sent from a queued job;
    | with the "sync" queue, that job runs once the page has been sent.
    |
    | bcc: an address that gets a copy of every back-in-stock email.
    |
    */

    'email' => [
        'queue' => null,
        'bcc' => null,
    ],

    /*
    |--------------------------------------------------------------------------
    | Confirming
    |--------------------------------------------------------------------------
    |
    | Every request is confirmed from a link emailed to the address given,
    | so nobody can sign someone else up. Links last this many hours.
    |
    | pages: where people land after confirming or unsubscribing. Null shows
    | the addon's own page (publish the views to restyle it). A URL on your
    | site, such as '/alerts/confirmed', redirects there instead, with the
    | message in {{ back_in_stock:success }}.
    |
    */

    'confirm_hours' => 72,

    'pages' => [
        'confirmed' => null,
        'unsubscribed' => null,
    ],

    /*
    |--------------------------------------------------------------------------
    | Sales
    |--------------------------------------------------------------------------
    |
    | An order for the product from the same email address within this many
    | days of the back-in-stock email marks the request as converted. Shown
    | in the Control Panel. Null turns this off.
    |
    */

    'conversion_days' => 14,

    /*
    |--------------------------------------------------------------------------
    | Keeping data
    |--------------------------------------------------------------------------
    |
    | Requests are deleted after this many days by `php please
    | back-in-stock:prune`, which runs daily with Laravel's scheduler.
    |
    | unconfirmed: never confirmed.
    | finished: emailed, converted or cancelled.
    | waiting: still waiting (the product never came back). Null keeps them.
    |
    */

    'retention' => [
        'unconfirmed' => 7,
        'finished' => 90,
        'waiting' => 365,
    ],

    /*
    |--------------------------------------------------------------------------
    | Scheduler
    |--------------------------------------------------------------------------
    |
    | back-in-stock:check runs every five minutes to catch stock changed
    | without saving the entry (imports writing files, say), and to send
    | anything left over. back-in-stock:prune runs daily. Set false to
    | schedule them yourself.
    |
    */

    'schedule' => true,

    /*
    |--------------------------------------------------------------------------
    | Rate limits
    |--------------------------------------------------------------------------
    |
    | subscribe: requests a minute from one visitor (by IP address).
    | per_email: confirmation emails an hour to one address, so a form
    | can't be used to flood someone's inbox.
    | per_address: the most open requests one email address can have.
    |
    */

    'throttle' => [
        'subscribe' => 10,
        'per_email' => 5,
        'per_address' => 50,
    ],

    /*
    |--------------------------------------------------------------------------
    | Spam
    |--------------------------------------------------------------------------
    |
    | A hidden field people can't see; forms that fill it in are thrown away
    | (quietly, so bots aren't told). Change the name if it clashes with a
    | field of yours.
    |
    */

    'honeypot' => 'website',

    /*
    |--------------------------------------------------------------------------
    | Views
    |--------------------------------------------------------------------------
    |
    | To change a little, publish the addon's views (php artisan
    | vendor:publish --tag=back-in-stock-views) and edit them. Or point these
    | at your own views.
    |
    */

    'views' => [
        'confirm' => 'back-in-stock::emails.confirm',
        'confirm_text' => 'back-in-stock::emails.confirm-text',
        'restocked' => 'back-in-stock::emails.restocked',
        'restocked_text' => 'back-in-stock::emails.restocked-text',
        'page' => 'back-in-stock::page',
    ],

    /*
    |--------------------------------------------------------------------------
    | Database
    |--------------------------------------------------------------------------
    |
    | Requests are kept in database tables: sending needs a database's
    | locks, whichever way Statamic and Cargo keep their own content.
    |
    */

    'database' => [
        'connection' => null,
    ],

];
