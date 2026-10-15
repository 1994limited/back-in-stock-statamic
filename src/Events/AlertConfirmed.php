<?php

namespace NineteenNinetyFour\BackInStock\Events;

use Illuminate\Foundation\Events\Dispatchable;
use NineteenNinetyFour\BackInStock\Models\Alert;

/**
 * Someone confirmed their request from the email link.
 */
class AlertConfirmed
{
    use Dispatchable;

    public function __construct(public Alert $alert) {}
}
