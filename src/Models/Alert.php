<?php

namespace NineteenNinetyFour\BackInStock\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\URL;
use NineteenNinetyFour\BackInStock\AlertStatus;
use Statamic\Facades\Site;

/**
 * Someone waiting for a product, or variant, to be back in stock.
 *
 * @property string $id
 * @property int $item_id
 * @property string $product_id
 * @property string $variant
 * @property string $site
 * @property string|null $locale
 * @property string $email
 * @property string|null $user_id
 * @property AlertStatus $status
 * @property string|null $consent
 * @property Carbon|null $confirmation_sent_at
 * @property Carbon|null $confirmed_at
 * @property Carbon|null $claimed_at
 * @property Carbon|null $notified_at
 * @property Carbon|null $converted_at
 * @property string|null $order_id
 * @property Carbon|null $cancelled_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Alert extends Model
{
    use HasUlids;

    protected $table = 'back_in_stock_alerts';

    protected $guarded = [];

    protected $attributes = [
        'variant' => '',
    ];

    protected function casts(): array
    {
        return [
            'status' => AlertStatus::class,
            'confirmation_sent_at' => 'datetime',
            'confirmed_at' => 'datetime',
            'claimed_at' => 'datetime',
            'notified_at' => 'datetime',
            'converted_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function scopeOpen(Builder $query): void
    {
        $query->whereIn('status', AlertStatus::open());
    }

    public function scopeForEmail(Builder $query, string $email): void
    {
        $query->where('email', self::normaliseEmail($email));
    }

    public static function normaliseEmail(string $email): string
    {
        return mb_strtolower(trim($email));
    }

    public function isOpen(): bool
    {
        return in_array($this->status->value, AlertStatus::open(), true);
    }

    public function siteObject(): \Statamic\Sites\Site
    {
        return Site::get($this->site) ?? Site::default();
    }

    /**
     * The link in the confirmation email. It lasts config('back-in-stock.confirm_hours').
     */
    public function confirmUrl(): string
    {
        return URL::temporarySignedRoute(
            'statamic.back-in-stock.confirm',
            now()->addHours(max(1, (int) config('back-in-stock.confirm_hours', 72))),
            ['alert' => $this->id],
        );
    }

    /**
     * The unsubscribe link. It doesn't expire: emails are read late.
     */
    public function unsubscribeUrl(): string
    {
        return URL::signedRoute('statamic.back-in-stock.unsubscribe', ['alert' => $this->id]);
    }
}
