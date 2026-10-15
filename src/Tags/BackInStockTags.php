<?php

namespace NineteenNinetyFour\BackInStock\Tags;

use Illuminate\Support\MessageBag;
use NineteenNinetyFour\BackInStock\AlertStatus;
use NineteenNinetyFour\BackInStock\BackInStock;
use NineteenNinetyFour\BackInStock\Cargo\Product;
use NineteenNinetyFour\BackInStock\Cargo\Stock;
use NineteenNinetyFour\BackInStock\Models\Alert;
use NineteenNinetyFour\BackInStock\Settings;
use Statamic\Facades\User;
use Statamic\Tags\Concerns\RendersForms;
use Statamic\Tags\Tags;

/**
 * {{ back_in_stock }} and {{ back_in_stock:* }}
 *
 * The form works without JavaScript: it posts to /!/back-in-stock/subscribe
 * and comes back with a success message or errors. Everything a visitor
 * typed is escaped before it's handed to Antlers.
 *
 * Personal things (the signed-in customer's email, their requests) are only
 * shown when static caching is off, or with personal="true" on a tag inside
 * {{ nocache }}: a cached page would show them to everyone.
 */
class BackInStockTags extends Tags
{
    use RendersForms;

    protected static $handle = 'back_in_stock';

    /**
     * {{ back_in_stock product="{{ id }}" }} … {{ /back_in_stock }}
     *
     * The "Email me when it's back" form, for a sold-out product, or a
     * variant (variant="Hardback"). For a product with variants and no
     * variant given, {{ variants }} lists the sold-out ones to choose from.
     * Shows nothing when there's nothing sold out.
     */
    public function index(): string
    {
        $entry = $this->entry();

        if (! $entry) {
            return '';
        }

        $variant = $this->variantParam();
        $stock = app(Stock::class);
        $fixed = $variant !== null ? $stock->find($entry->id(), $variant) : null;

        if ($variant !== null && ! $fixed) {
            return '';
        }

        $choices = $fixed || ! $entry->isVariantProduct()
            ? collect()
            : $entry->variantOptions()->map(fn ($v) => $stock->find($entry->id(), (string) $v->key()))->filter()->values();

        $product = $fixed ?? ($entry->isVariantProduct() ? null : $stock->find($entry->id()));
        $soldOut = $product ? $product->isSoldOut() : $choices->contains(fn (Product $p) => $p->isSoldOut());

        if (! $soldOut || ! ($product ?? $choices->first())?->isAvailable()) {
            return '';
        }

        $isThisForm = $this->isThisForm((string) $entry->id(), $product?->variantKey());
        $oldVariant = $isThisForm ? session()->getOldInput('variant') : null;
        $user = $this->personal() ? User::current() : null;

        $data = [
            'product_id' => e((string) $entry->id()),
            'title' => e($product?->title() ?? (string) $entry->value('title')),
            'variant' => $product ? e($product->variantKey()) : null,
            'variant_name' => $product?->variantName() !== null ? e($product->variantName()) : null,
            'has_variants' => $product === null,
            'variants' => $choices->filter(fn (Product $p) => $p->isSoldOut())->map(fn (Product $p) => [
                'key' => e($p->variantKey()),
                'name' => e((string) $p->variantName()),
                'selected' => is_string($oldVariant) && $oldVariant === $p->variantKey(),
            ])->values()->all(),
            'email' => e((string) ($isThisForm && is_string($old = session()->getOldInput('email')) ? $old : ($user?->email() ?? ''))),
            'signed_in' => $user !== null,
            'consent_text' => e(Settings::text('consent_text')),
            'consent_checked' => $isThisForm && (bool) session()->getOldInput('consent'),
            'success' => $isThisForm ? $this->success() : null,
            'errors' => $isThisForm ? $this->errorMessages() : [],
            'waiting' => app(BackInStock::class)->waitingCount((string) $entry->id(), $product?->variantKey()),
        ];

        $hidden = ['product' => (string) $entry->id()];

        if ($product?->variantKey()) {
            $hidden['variant'] = $product->variantKey();
        }

        return $this->actionForm('subscribe', $hidden, $data, honeypot: true);
    }

    /**
     * {{ if {back_in_stock:sold_out} }}: whether the product (or the
     * variant="…" given) is sold out. For a product with variants and no
     * variant given: whether any of them is.
     */
    public function soldOut(): bool
    {
        $entry = $this->entry();

        if (! $entry) {
            return false;
        }

        $stock = app(Stock::class);
        $variant = $this->variantParam();

        if ($variant !== null || ! $entry->isVariantProduct()) {
            return (bool) $stock->find($entry->id(), (string) $variant)?->isSoldOut();
        }

        return $entry->variantOptions()->contains(fn ($v) => (bool) $stock->find($entry->id(), (string) $v->key())?->isSoldOut());
    }

    /**
     * {{ back_in_stock:count }}: how many people are waiting for the
     * product (or the variant="…" given). Confirmed requests only.
     */
    public function count(): int
    {
        $entry = $this->entry();

        return $entry ? app(BackInStock::class)->waitingCount((string) $entry->id(), $this->variantParam()) : 0;
    }

    /**
     * {{ if {back_in_stock:subscribed} }}: whether the signed-in customer
     * has asked about this product (or the variant="…" given). Personal.
     */
    public function subscribed(): bool
    {
        $entry = $this->entry();
        $user = $this->personal() ? User::current() : null;

        return $entry && $user && app(BackInStock::class)->openFor($user, (string) $entry->id(), $this->variantParam()) !== null;
    }

    /**
     * {{ back_in_stock:alerts }} {{ name }} {{ status_label }} {{ /back_in_stock:alerts }}
     *
     * The signed-in customer's open requests, newest first, for an account
     * page. Personal.
     */
    public function alerts(): array|string
    {
        $user = $this->personal() ? User::current() : null;

        if (! $user) {
            return $this->isPair ? '' : [];
        }

        $alerts = Alert::query()
            ->open()
            ->where(function ($query) use ($user) {
                $query->where('user_id', (string) $user->id());

                if ($user->email()) {
                    $query->orWhere('email', Alert::normaliseEmail($user->email()));
                }
            })
            ->latest()
            ->limit(max(1, (int) $this->params->get('limit', 100)))
            ->get()
            ->map(function (Alert $alert) {
                $product = $alert->item?->product();

                return [
                    'id' => e($alert->id),
                    'product_id' => e($alert->product_id),
                    'variant' => e($alert->variant),
                    'title' => e($product?->title() ?? $alert->product_id),
                    'variant_name' => $product?->variantName() !== null ? e($product->variantName()) : null,
                    'name' => e($product?->name() ?? $alert->product_id),
                    'url' => $product?->url(),
                    'status' => $alert->status->value,
                    'status_label' => e($alert->status->label()),
                    'is_unconfirmed' => $alert->status === AlertStatus::Unconfirmed,
                    'is_waiting' => $alert->status === AlertStatus::Waiting,
                    'created_at' => $alert->created_at,
                ];
            })
            ->all();

        return $alerts === [] && $this->isPair ? '' : $alerts;
    }

    /**
     * {{ back_in_stock:cancel :alert="id" }} <button>Cancel</button> {{ /back_in_stock:cancel }}
     *
     * For the signed-in customer's own requests.
     */
    public function cancel(): string
    {
        return $this->actionForm('cancel', ['alert' => (string) $this->params->get('alert')]);
    }

    /**
     * {{ back_in_stock:success }}: the message after a form post, if any.
     */
    public function success(): ?string
    {
        $message = session('back_in_stock_success');

        return is_string($message) ? e($message) : null;
    }

    /**
     * {{ back_in_stock:error }} or {{ back_in_stock:error field="email" }}: what went wrong.
     */
    public function error(): ?string
    {
        $field = (string) $this->params->get('field', 'back_in_stock');
        $message = $this->errorBag()?->first($field);

        return $message ? e($message) : null;
    }

    /**
     * A form posting to an action, with hidden fields, and the tag's
     * contents parsed with $data.
     *
     * @param  array<string, string>  $fields
     * @param  array<string, mixed>  $data
     */
    protected function actionForm(string $action, array $fields, array $data = [], bool $honeypot = false): string
    {
        $known = ['product', 'variant', 'alert', 'redirect', 'error_redirect', 'personal'];

        $html = $this->formOpen(url("!/back-in-stock/{$action}"), 'POST', $known);

        $fields += array_filter([
            '_redirect' => $this->params->get('redirect'),
            '_error_redirect' => $this->params->get('error_redirect'),
        ]);

        foreach ($fields as $name => $value) {
            $html .= '<input type="hidden" name="'.e($name).'" value="'.e((string) $value).'">';
        }

        $html .= $this->isPair ? $this->parse($data) : '';

        if ($honeypot) {
            $html .= '<div aria-hidden="true" style="position:absolute;left:-10000px;top:auto;width:1px;height:1px;overflow:hidden;">'
                .'<label>Leave this empty <input type="text" name="'.e((string) config('back-in-stock.honeypot', 'website')).'" value="" tabindex="-1" autocomplete="off"></label>'
                .'</div>';
        }

        return $html.$this->formClose();
    }

    /**
     * The product the tag is about: product="…", or the entry in context.
     */
    protected function entry(): ?\DuncanMcClean\Cargo\Contracts\Products\Product
    {
        $id = $this->params->get('product', $this->context->get('id'));
        $id = is_object($id) && method_exists($id, 'value') ? $id->value() : $id;

        if (! is_string($id) || $id === '') {
            return null;
        }

        try {
            return \DuncanMcClean\Cargo\Facades\Product::find($id);
        } catch (\Throwable) {
            return null;
        }
    }

    protected function variantParam(): ?string
    {
        $variant = $this->params->get('variant');
        $variant = is_object($variant) && method_exists($variant, 'value') ? $variant->value() : $variant;

        return is_scalar($variant) && (string) $variant !== '' ? (string) $variant : null;
    }

    /**
     * Whether the last form post was this one (a page can have a form per
     * product, or per variant): its message and errors show only here.
     */
    protected function isThisForm(string $productId, ?string $variant): bool
    {
        $item = session('back_in_stock_item');

        if (! is_string($item)) {
            return false;
        }

        [$posted, $postedVariant] = array_pad(explode('|', $item, 2), 2, '');

        return $posted === $productId && ($variant === null || $variant === '' || $postedVariant === $variant);
    }

    /**
     * Whether personal things can be shown: not on a statically cached
     * page, unless the template says it's inside {{ nocache }}.
     */
    protected function personal(): bool
    {
        return $this->params->bool('personal', false) || ! config('statamic.static_caching.strategy');
    }

    /**
     * @return array<string, string>
     */
    protected function errorMessages(): array
    {
        return collect($this->errorBag()?->messages() ?? [])
            ->map(fn (array $messages) => e((string) ($messages[0] ?? '')))
            ->all();
    }

    protected function errorBag(): ?MessageBag
    {
        $errors = session('errors');

        return $errors ? $errors->getBag('back_in_stock') : null;
    }
}
