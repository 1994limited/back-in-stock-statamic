<?php

namespace NineteenNinetyFour\BackInStock\Events;

use Illuminate\Foundation\Events\Dispatchable;
use NineteenNinetyFour\BackInStock\Models\Item;
use NineteenNinetyFour\BackInStock\Models\Restock;

/**
 * A product or variant someone is waiting for came back in stock. Emails
 * follow after the delay. $restock has the stock before and after.
 */
class ProductRestocked
{
    use Dispatchable;

    public function __construct(public Item $item, public Restock $restock) {}
}
