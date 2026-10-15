<?php

namespace NineteenNinetyFour\BackInStock\Cargo;

use DuncanMcClean\Cargo\Facades\Product as Products;
use Statamic\Contracts\Entries\Entry;
use Throwable;

/**
 * Reading products and their stock from Cargo. Stock is kept on the
 * product entry ("stock"), or on each variant ("product_variants" options).
 */
class Stock
{
    /**
     * A product or variant, or null if there isn't one (or it isn't a product).
     */
    public function find(string $productId, string $variant = ''): ?Product
    {
        try {
            $entry = $productId !== '' ? Products::find($productId) : null;
        } catch (Throwable) {
            return null;
        }

        if (! $entry) {
            return null;
        }

        if ($entry->isVariantProduct()) {
            $found = $variant !== '' ? $entry->variant($variant) : null;

            return $found ? new Product($entry, $found) : null;
        }

        return $variant === '' ? new Product($entry) : null;
    }

    /**
     * The stock to remember for a product or variant, as Item columns.
     *
     * @return array{stock: int|null, stock_tracked: bool}
     */
    public function snapshot(string $productId, string $variant = ''): array
    {
        $product = $this->find($productId, $variant);

        return [
            'stock' => $product?->stock(),
            'stock_tracked' => $product ? $product->tracksStock() : true,
        ];
    }

    /**
     * Whether an entry is one of Cargo's products.
     */
    public function isProductEntry(mixed $entry): bool
    {
        return $entry instanceof Entry
            && in_array($entry->collectionHandle(), (array) config('statamic.cargo.products.collections', ['products']), true);
    }

    /**
     * An entry's id and those of its localisations, which can share its stock.
     *
     * @return array<int, string>
     */
    public function relatedIds(Entry $entry): array
    {
        $ids = [(string) $entry->id()];

        try {
            foreach ($entry->descendants() as $localisation) {
                $ids[] = (string) $localisation->id();
            }

            if ($entry->hasOrigin()) {
                // A localisation's stock can be its origin's.
                $ids[] = (string) $entry->root()->id();
            }
        } catch (Throwable) {
            // Not localised.
        }

        return array_values(array_unique($ids));
    }
}
