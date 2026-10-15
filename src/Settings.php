<?php

namespace NineteenNinetyFour\BackInStock;

use Statamic\Contracts\Assets\Asset;
use Statamic\Facades\Addon;
use Statamic\Facades\Site;
use Throwable;

/**
 * The addon's settings, edited in the Control Panel (Tools → Addons → Back
 * in Stock → Settings). Each falls back to config/back-in-stock.php, or to
 * the wording in lang/, so an empty setting means "the default".
 * Placeholders are written :like_this.
 */
class Settings
{
    public const ADDON = '1994/back-in-stock-statamic';

    public static function get(string $key, mixed $default = null): mixed
    {
        try {
            $value = Addon::get(self::ADDON)?->settings()->get($key);
        } catch (Throwable) {
            $value = null;
        }

        if (is_string($value)) {
            $value = trim($value);
        }

        return $value === '' || $value === null ? $default : $value;
    }

    /**
     * Minutes to wait after stock comes back before emailing.
     */
    public static function delay(): int
    {
        return max(0, (int) self::get('delay', config('back-in-stock.delay', 5)));
    }

    /**
     * 'all' or 'stock'.
     */
    public static function limit(): string
    {
        return self::get('limit', config('back-in-stock.limit', 'all')) === 'stock' ? 'stock' : 'all';
    }

    public static function multiplier(): int
    {
        return max(1, (int) self::get('multiplier', config('back-in-stock.multiplier', 1)));
    }

    public static function perMinute(): int
    {
        return max(1, (int) self::get('per_minute', config('back-in-stock.per_minute', 120)));
    }

    /**
     * A piece of wording, with its placeholders filled in. In the site's
     * language when the setting is left empty.
     *
     * @param  array<string, string|null>  $replace
     */
    public static function text(string $key, array $replace = [], ?string $locale = null): string
    {
        $text = self::get($key) ?? (string) __('back-in-stock::messages.defaults.'.$key, [], $locale);

        $replace += ['site' => Site::current()?->name() ?? config('app.name')];

        // Longest first, so :site_name wouldn't be read as :site.
        uksort($replace, fn ($a, $b) => strlen($b) <=> strlen($a));

        foreach ($replace as $name => $value) {
            $text = str_replace(':'.$name, (string) $value, $text);
        }

        return $text;
    }

    /**
     * The brand colour, as a CSS hex colour.
     */
    public static function colour(): string
    {
        $colour = self::get('accent_colour');

        return is_string($colour) && preg_match('/^#(?:[0-9a-fA-F]{3}){1,2}$/', $colour) ? $colour : '#1f2937';
    }

    /**
     * Dark or white text, whichever reads better on the brand colour.
     */
    public static function onColour(): string
    {
        $hex = ltrim(self::colour(), '#');

        if (strlen($hex) === 3) {
            $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }

        [$r, $g, $b] = array_map(function (string $pair) {
            $c = hexdec($pair) / 255;

            return $c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
        }, str_split($hex, 2));

        $luminance = 0.2126 * $r + 0.7152 * $g + 0.0722 * $b;

        return (1.05 / ($luminance + 0.05)) >= (($luminance + 0.05) / 0.0607) ? '#ffffff' : '#111827';
    }

    public static function logo(): ?Asset
    {
        try {
            $addon = Addon::get(self::ADDON);
            $value = $addon?->settings()->raw()['logo'] ?? null;

            if (! $value || ! $field = $addon->settingsBlueprint()?->field('logo')) {
                return null;
            }

            $asset = $field->setValue($value)->augment()->value();
            $asset = is_iterable($asset) ? collect($asset)->first() : $asset;

            return $asset instanceof Asset ? $asset : null;
        } catch (Throwable) {
            return null;
        }
    }
}
