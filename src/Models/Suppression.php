<?php

namespace NineteenNinetyFour\BackInStock\Models;

/**
 * An address that unsubscribed. Only a hash of it is kept.
 *
 * @property string $email_hash
 */
class Suppression extends Model
{
    protected $table = 'back_in_stock_suppressions';

    protected $primaryKey = 'email_hash';

    public $incrementing = false;

    protected $keyType = 'string';

    public const UPDATED_AT = null;

    protected $guarded = [];

    public static function hash(string $email): string
    {
        return hash('sha256', Alert::normaliseEmail($email));
    }

    public static function add(string $email): void
    {
        static::query()->firstOrCreate(['email_hash' => self::hash($email)]);
    }

    public static function lift(string $email): void
    {
        static::query()->whereKey(self::hash($email))->delete();
    }

    /**
     * Whether an address unsubscribed after confirming this request (or
     * since, as one-click unsubscribing stops everything). Confirming a new
     * request lifts it.
     */
    public static function blocks(Alert $alert): bool
    {
        $suppression = static::query()->find(self::hash($alert->email));

        return $suppression !== null
            && ($alert->confirmed_at === null || $suppression->created_at === null || $alert->confirmed_at->lte($suppression->created_at));
    }
}
