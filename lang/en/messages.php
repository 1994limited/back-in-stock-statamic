<?php

return [

    // The front end.
    'subscribed' => 'Nearly there: we’ve emailed you a link. Click it to confirm, and we’ll let you know when it’s back.',
    'cancelled' => 'You won’t hear about that one now.',
    'confirmed' => 'Confirmed. We’ll email you once when it’s back in stock.',
    'already_confirmed' => 'That’s already confirmed. We’ll email you when it’s back in stock.',
    'unsubscribed' => 'You’re unsubscribed. We won’t email you about anything coming back in stock.',
    'already_unsubscribed' => 'You’re already unsubscribed.',

    'errors' => [
        'email' => 'Enter a valid email address.',
        'consent' => 'Tick the box to say we can email you.',
        'product' => 'That product can’t be found.',
        'variant' => 'Choose which one you’d like.',
        'in_stock' => 'Good news: that’s in stock now.',
        'too_many' => 'That email address is already waiting for a lot of products.',
        'not_sent' => 'We couldn’t send the confirmation email. Please try again later.',
        'not_yours' => 'That request can’t be found.',
        'expired' => 'That link has expired. Ask again from the product page and we’ll send a new one.',
        'too_fast' => 'Too many tries. Please wait a little and try again.',
    ],

    'status' => [
        'unconfirmed' => 'Unconfirmed',
        'waiting' => 'Waiting',
        'sending' => 'Sending',
        'notified' => 'Emailed',
        'converted' => 'Bought',
        'cancelled' => 'Cancelled',
    ],

    // Defaults for the settings (Tools → Addons → Back in Stock → Settings).
    // Placeholders: :product (with the variant), :site.
    'defaults' => [
        'consent_text' => 'Email me once when it’s back in stock. Nothing else.',
        'confirm_subject' => 'Confirm your back-in-stock alert for :product',
        'confirm_heading' => 'Confirm your alert',
        'confirm_intro' => 'You asked :site to let you know when :product is back in stock. Confirm it’s you, and we’ll email you once when it is.',
        'confirm_button' => 'Yes, email me',
        'confirm_ignore' => 'Didn’t ask? Ignore this email and you won’t hear from us.',
        'restocked_subject' => ':product is back in stock',
        'restocked_heading' => 'It’s back',
        'restocked_intro' => 'Good news: :product is back in stock at :site. It may not stay long.',
        'restocked_button' => 'Take a look',
        'restocked_footer' => 'You asked us to email you when this was back in stock, so this is the only email you’ll get about it.',
    ],

    'email' => [
        'price' => 'Price',
        'unsubscribe' => 'Unsubscribe from all back-in-stock emails',
        'link_expires' => 'The link works for :hours hours.',
    ],

    'page' => [
        'confirm_title' => 'Confirm your alert',
        'confirm_text' => 'Email :email once when :product is back in stock?',
        'confirmed_title' => 'You’re on the list',
        'unsubscribe_title' => 'Unsubscribe?',
        'unsubscribe_text' => 'Stop all back-in-stock emails to :email?',
        'unsubscribe_button' => 'Unsubscribe',
        'unsubscribed_title' => 'Unsubscribed',
        'expired_title' => 'Link expired',
        'back' => 'Back to :site',
    ],

    // The Control Panel.
    'cp' => [
        'title' => 'Back in Stock',
        'widget' => 'Wanted while sold out',
    ],

    'permissions' => [
        'group' => 'Back in Stock',
        'view' => 'View back-in-stock requests',
        'manage' => 'Send, cancel and delete requests',
        'export' => 'Download requests, and people’s data',
    ],

];
