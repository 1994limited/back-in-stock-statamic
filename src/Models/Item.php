<?php

namespace NineteenNinetyFour\BackInStock\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;
use NineteenNinetyFour\BackInStock\AlertStatus;
use NineteenNinetyFour\BackInStock\Cargo\Product;
use NineteenNinetyFour\BackInStock\Cargo\Stock;

/**
 * A product, or one of its variants, that someone asked about. Keeps the
 * stock last seen, so a rise can be noticed however it happened, and any
 * emails still to send for it.
 *
 * @property int $id
 * @property string $product_id
 * @property string $variant
 * @property int|null $stock
 * @property bool $stock_tracked
 * @property bool $pending
 * @property int|null $pending_limit
 * @property int $pending_version
 * @property Carbon|null $send_after
 * @property Carbon|null $restocked_at
 */
class Item extends Model
{
    protected $table = 'back_in_stock_items';

    protected $guarded = [];

    protected $attributes = [
        'variant' => '',
        'stock_tracked' => true,
        'pending' => false,
    ];

    protected function casts(): array
    {
        return [
            'stock' => 'integer',
            'stock_tracked' => 'boolean',
            'pending' => 'boolean',
            'pending_limit' => 'integer',
            'pending_version' => 'integer',
            'send_after' => 'datetime',
            'restocked_at' => 'datetime',
        ];
    }

    public static function for(string $productId, ?string $variant = null): self
    {
        return static::firstOrCreate(
            ['product_id' => $productId, 'variant' => (string) $variant],
            app(Stock::class)->snapshot($productId, (string) $variant),
        );
    }

    public function alerts(): HasMany
    {
        return $this->hasMany(Alert::class);
    }

    public function restocks(): HasMany
    {
        return $this->hasMany(Restock::class);
    }

    public function latestRestock(): HasOne
    {
        return $this->hasOne(Restock::class)->latestOfMany();
    }

    public function waiting(): HasMany
    {
        return $this->alerts()->where('status', AlertStatus::Waiting->value);
    }

    /**
     * The product or variant in Cargo, or null if it's gone.
     */
    public function product(): ?Product
    {
        return app(Stock::class)->find($this->product_id, $this->variant);
    }
}
