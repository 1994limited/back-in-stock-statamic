<?php

namespace NineteenNinetyFour\BackInStock\Mail;

use NineteenNinetyFour\BackInStock\Cargo\Product;
use NineteenNinetyFour\BackInStock\Models\Alert;
use NineteenNinetyFour\BackInStock\Settings;

/**
 * What both emails are drawn with.
 */
trait Presents
{
    /**
     * @return array<string, mixed>
     */
    protected function shared(Alert $alert, ?Product $product): array
    {
        $site = $alert->siteObject();
        $logo = Settings::logo();

        return [
            'alert' => $alert,
            'product' => $product,
            'productName' => $this->productName($alert, $product),
            'locale' => str_replace('_', '-', $site->locale()),
            'siteName' => $site->name(),
            'siteUrl' => $site->absoluteUrl(),
            'colour' => Settings::colour(),
            'onColour' => Settings::onColour(),
            'logo' => $logo?->absoluteUrl(),
        ];
    }

    protected function productName(Alert $alert, ?Product $product): string
    {
        return $product?->name() ?? trim($alert->product_id.' '.$alert->variant);
    }

    /**
     * @return array<string, string>
     */
    protected function replacements(Alert $alert, ?Product $product): array
    {
        return [
            'product' => $this->productName($alert, $product),
            'site' => $alert->siteObject()->name(),
        ];
    }
}
