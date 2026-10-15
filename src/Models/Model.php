<?php

namespace NineteenNinetyFour\BackInStock\Models;

use Illuminate\Database\Eloquent\Model as Eloquent;

abstract class Model extends Eloquent
{
    public function getConnectionName(): ?string
    {
        return config('back-in-stock.database.connection') ?? parent::getConnectionName();
    }
}
