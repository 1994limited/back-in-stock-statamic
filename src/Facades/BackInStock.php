<?php

namespace NineteenNinetyFour\BackInStock\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \NineteenNinetyFour\BackInStock\Cargo\Product|null product(string $productId, ?string $variant = null)
 * @method static \NineteenNinetyFour\BackInStock\Models\Alert subscribe(\NineteenNinetyFour\BackInStock\Cargo\Product $product, string $email, ?\Statamic\Sites\Site $site = null, ?\Statamic\Contracts\Auth\User $user = null, ?string $consent = null)
 * @method static bool confirm(\NineteenNinetyFour\BackInStock\Models\Alert $alert)
 * @method static bool cancel(\NineteenNinetyFour\BackInStock\Models\Alert $alert)
 * @method static int unsubscribe(string $email)
 * @method static array export(string $email)
 * @method static int forget(string $email)
 * @method static int waitingCount(string $productId, ?string $variant = null)
 * @method static \NineteenNinetyFour\BackInStock\Models\Alert|null openFor(\Statamic\Contracts\Auth\User $user, string $productId, ?string $variant = null)
 * @method static int sendNow(\NineteenNinetyFour\BackInStock\Models\Item $item)
 *
 * @see \NineteenNinetyFour\BackInStock\BackInStock
 */
class BackInStock extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \NineteenNinetyFour\BackInStock\BackInStock::class;
    }
}
