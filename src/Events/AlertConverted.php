<?php

namespace NineteenNinetyFour\BackInStock\Events;

use Illuminate\Foundation\Events\Dispatchable;
use NineteenNinetyFour\BackInStock\Models\Alert;

/**
 * Someone who was emailed bought the product.
 */
class AlertConverted
{
    use Dispatchable;

    public function __construct(public Alert $alert) {}
}
