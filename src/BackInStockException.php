<?php

namespace NineteenNinetyFour\BackInStock;

use RuntimeException;

/**
 * Something a visitor (or the PHP API) asked for that can't be done. The
 * message is the translated reason, safe to show; the field is the form
 * field it's about.
 */
class BackInStockException extends RuntimeException
{
    public function __construct(public readonly string $reason, public readonly string $field = 'email', public readonly int $status = 422)
    {
        parent::__construct((string) __('back-in-stock::messages.errors.'.$reason));
    }

    public static function because(string $reason, string $field = 'email', int $status = 422): self
    {
        return new self($reason, $field, $status);
    }
}
