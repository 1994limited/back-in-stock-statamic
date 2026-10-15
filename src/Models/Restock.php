<?php

namespace NineteenNinetyFour\BackInStock\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A time stock came back, and how many people were emailed about it.
 *
 * @property int $id
 * @property int $item_id
 * @property int|null $stock_before
 * @property int|null $stock_after
 * @property int $sent
 * @property string $trigger
 * @property Carbon|null $created_at
 */
class Restock extends Model
{
    protected $table = 'back_in_stock_restocks';

    protected $guarded = [];

    protected $attributes = [
        'sent' => 0,
    ];

    protected function casts(): array
    {
        return [
            'stock_before' => 'integer',
            'stock_after' => 'integer',
            'sent' => 'integer',
        ];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }
}
