<?php

namespace NineteenNinetyFour\BackInStock\Cargo;

use DuncanMcClean\Cargo\Contracts\Products\Product as CargoProduct;
use DuncanMcClean\Cargo\Products\ProductVariant;
use DuncanMcClean\Cargo\Support\Money;
use Statamic\Sites\Site;
use Throwable;

/**
 * A Cargo product, or one of its variants: what people ask to hear about.
 */
class Product
{
    public function __construct(
        public readonly CargoProduct $entry,
        public readonly ?ProductVariant $variant = null,
    ) {}

    public function id(): string
    {
        return (string) $this->entry->id();
    }

    public function variantKey(): string
    {
        return $this->variant ? (string) $this->variant->key() : '';
    }

    /**
     * Whether Cargo counts this one's stock. Products without a stock field,
     * or with it left empty, never run out.
     */
    public function tracksStock(): bool
    {
        return (bool) ($this->variant ? $this->variant->isStockEnabled() : $this->entry->isStockEnabled());
    }

    /**
     * The stock, or null if it isn't counted.
     */
    public function stock(): ?int
    {
        if (! $this->tracksStock()) {
            return null;
        }

        $stock = $this->variant ? $this->variant->stock() : $this->entry->stock();

        return $stock === null ? null : (int) $stock;
    }

    public function isSoldOut(): bool
    {
        return $this->tracksStock() && (int) $this->stock() <= 0;
    }

    public function isInStock(): bool
    {
        return ! $this->isSoldOut();
    }

    /**
     * Live on the site: published, and not scheduled for later or expired.
     */
    public function isAvailable(): bool
    {
        return $this->entry->status() === 'published';
    }

    public function title(): string
    {
        return (string) ($this->entry->value('title') ?? $this->entry->id());
    }

    public function variantName(): ?string
    {
        return $this->variant ? (string) $this->variant->name() : null;
    }

    /**
     * "Title – Variant", for lists and emails.
     */
    public function name(): string
    {
        return $this->variant ? $this->title().' – '.$this->variantName() : $this->title();
    }

    public function price(): ?int
    {
        $price = $this->variant ? $this->variant->price() : $this->entry->price();

        return is_numeric($price) ? (int) $price : null;
    }

    public function formattedPrice(?Site $site = null): ?string
    {
        $price = $this->price();

        if ($price === null) {
            return null;
        }

        try {
            return Money::format($price, $site ?? $this->entry->site());
        } catch (Throwable) {
            return null;
        }
    }

    public function url(): ?string
    {
        $url = $this->entry->absoluteUrl();

        return is_string($url) && $url !== '' ? $url : null;
    }

    public function editUrl(): ?string
    {
        try {
            return $this->entry->editUrl();
        } catch (Throwable) {
            return null;
        }
    }
}
