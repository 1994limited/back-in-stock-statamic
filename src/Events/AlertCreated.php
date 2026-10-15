<?php

namespace NineteenNinetyFour\BackInStock\Events;

use Illuminate\Foundation\Events\Dispatchable;
use NineteenNinetyFour\BackInStock\Models\Alert;

/**
 * Someone asked to hear when a product is back (not yet confirmed).
 */
class AlertCreated
{
    use Dispatchable;

    public function __construct(public Alert $alert) {}
}
