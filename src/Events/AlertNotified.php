<?php

namespace NineteenNinetyFour\BackInStock\Events;

use Illuminate\Foundation\Events\Dispatchable;
use NineteenNinetyFour\BackInStock\Models\Alert;

/**
 * Someone was emailed that a product is back in stock.
 */
class AlertNotified
{
    use Dispatchable;

    public function __construct(public Alert $alert) {}
}
