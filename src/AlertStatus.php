<?php

namespace NineteenNinetyFour\BackInStock;

enum AlertStatus: string
{
    /** Waiting for the person to click the link in the confirmation email. */
    case Unconfirmed = 'unconfirmed';

    /** Confirmed, waiting for stock. */
    case Waiting = 'waiting';

    /** Being emailed right now (claimed by a send, so it's never emailed twice). */
    case Sending = 'sending';

    /** Emailed that it's back in stock. */
    case Notified = 'notified';

    /** Emailed, then bought. */
    case Converted = 'converted';

    /** Cancelled by the person, or by unsubscribing. */
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return __('back-in-stock::messages.status.'.$this->value);
    }

    /**
     * Still to be emailed (or confirmed).
     *
     * @return array<int, string>
     */
    public static function open(): array
    {
        return [self::Unconfirmed->value, self::Waiting->value, self::Sending->value];
    }

    /**
     * @return array<int, string>
     */
    public static function finished(): array
    {
        return [self::Notified->value, self::Converted->value, self::Cancelled->value];
    }
}
