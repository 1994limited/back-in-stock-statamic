<?php

namespace NineteenNinetyFour\BackInStock\Events;

use Illuminate\Foundation\Events\Dispatchable;
use NineteenNinetyFour\BackInStock\Models\Alert;

/**
 * A request was cancelled: by the person, by unsubscribing, or in the Control Panel.
 */
class AlertCancelled
{
    use Dispatchable;

    public function __construct(public Alert $alert) {}
}
