<?php

namespace NineteenNinetyFour\BackInStock;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use NineteenNinetyFour\BackInStock\Cargo\Product;
use NineteenNinetyFour\BackInStock\Cargo\Stock;
use NineteenNinetyFour\BackInStock\Events\AlertCancelled;
use NineteenNinetyFour\BackInStock\Events\AlertConfirmed;
use NineteenNinetyFour\BackInStock\Events\AlertCreated;
use NineteenNinetyFour\BackInStock\Mail\ConfirmMail;
use NineteenNinetyFour\BackInStock\Models\Alert;
use NineteenNinetyFour\BackInStock\Models\Item;
use NineteenNinetyFour\BackInStock\Models\Suppression;
use Statamic\Contracts\Auth\User;
use Statamic\Facades\Site;
use Statamic\Sites\Site as SiteObject;
use Throwable;

/**
 * Asking to hear when something's back, confirming, cancelling and
 * forgetting. The tags, the front-end routes and the Control Panel all go
 * through here; so can a site's own code (the BackInStock facade).
 */
class BackInStock
{
    public function __construct(protected Stock $stock, protected Watcher $watcher) {}

    /**
     * A product, or one of its variants, by id and variant key.
     */
    public function product(string $productId, ?string $variant = null): ?Product
    {
        return $this->stock->find($productId, (string) $variant);
    }

    /**
     * Ask to be emailed when a sold-out product or variant is back. A
     * confirmation email goes to the address first: nothing else is sent
     * until its link is clicked. Asking again for the same thing is fine
     * (an unconfirmed request gets its email again, at most every few
     * minutes); it never makes a second request.
     *
     * @throws BackInStockException
     */
    public function subscribe(Product $product, string $email, ?SiteObject $site = null, ?User $user = null, ?string $consent = null): Alert
    {
        $email = Alert::normaliseEmail($email);
        $site ??= Site::current();

        if (! $this->isValidEmail($email)) {
            throw BackInStockException::because('email');
        }

        if (! $product->isAvailable()) {
            throw BackInStockException::because('product', 'product', 404);
        }

        if (! $product->isSoldOut()) {
            throw BackInStockException::because('in_stock', 'product');
        }

        $existing = Alert::query()
            ->forEmail($email)
            ->where('product_id', $product->id())
            ->where('variant', $product->variantKey())
            ->open()
            ->first();

        if ($existing) {
            if ($existing->status === AlertStatus::Unconfirmed
                && (! $existing->confirmation_sent_at || $existing->confirmation_sent_at->lt(now()->subMinutes(5)))) {
                $this->sendConfirmation($existing);
            }

            return $existing;
        }

        $open = Alert::query()->forEmail($email)->open()->count();

        if ($open >= max(1, (int) config('back-in-stock.throttle.per_address', 50))) {
            throw BackInStockException::because('too_many');
        }

        if ($this->tooManyConfirmations($email)) {
            throw BackInStockException::because('too_fast', 'email', 429);
        }

        $item = Item::for($product->id(), $product->variantKey());

        $alert = $item->alerts()->create([
            'product_id' => $product->id(),
            'variant' => $product->variantKey(),
            'site' => $site->handle(),
            'locale' => $site->lang(),
            'email' => $email,
            'user_id' => $user?->id(),
            'status' => AlertStatus::Unconfirmed,
            'consent' => $consent !== null ? mb_substr($consent, 0, 2000) : null,
        ]);

        AlertCreated::dispatch($alert);

        $this->sendConfirmation($alert);

        return $alert;
    }

    /**
     * Email the confirmation link. No more than a few an hour to one
     * address, so the form can't be used to flood someone's inbox.
     *
     * @throws BackInStockException
     */
    public function sendConfirmation(Alert $alert): void
    {
        if ($this->tooManyConfirmations($alert->email)) {
            throw BackInStockException::because('too_fast', 'email', 429);
        }

        RateLimiter::hit($this->confirmationKey($alert->email), 3600);

        try {
            $mail = new ConfirmMail($alert);
            $queue = config('back-in-stock.email.queue');

            Mail::to($alert->email)
                ->locale($alert->locale ?? $alert->siteObject()->lang())
                ->queue($mail->onQueue(is_string($queue) ? $queue : null));
        } catch (Throwable $e) {
            Log::warning("Back in Stock couldn't send the confirmation for request {$alert->id}: {$e->getMessage()}");
            report($e);

            throw BackInStockException::because('not_sent', 'email', 500);
        }

        $alert->forceFill(['confirmation_sent_at' => now()])->save();
    }

    /**
     * The link in the confirmation email was clicked. Returns false if the
     * request was already confirmed (or is finished).
     */
    public function confirm(Alert $alert): bool
    {
        if ($alert->status !== AlertStatus::Unconfirmed) {
            return false;
        }

        $alert->forceFill(['status' => AlertStatus::Waiting, 'confirmed_at' => now()])->save();

        // Asking again after unsubscribing means it.
        Suppression::lift($alert->email);

        AlertConfirmed::dispatch($alert);

        $this->watcher->confirmedWhileInStock($alert);

        return true;
    }

    /**
     * Cancel one request. Returns false if it wasn't open.
     */
    public function cancel(Alert $alert): bool
    {
        if (! $alert->isOpen()) {
            return false;
        }

        $alert->forceFill(['status' => AlertStatus::Cancelled, 'cancelled_at' => now(), 'claimed_at' => null])->save();

        AlertCancelled::dispatch($alert);

        return true;
    }

    /**
     * Stop all back-in-stock emails to an address: its open requests are
     * cancelled, and it's remembered (as a hash) until it asks again.
     * Returns how many requests were cancelled.
     */
    public function unsubscribe(string $email): int
    {
        Suppression::add($email);

        return Alert::query()->forEmail($email)->open()->get()
            ->filter(fn (Alert $alert) => $this->cancel($alert))
            ->count();
    }

    /**
     * Everything kept about an address, for a subject access request.
     *
     * @return array<int, array<string, mixed>>
     */
    public function export(string $email): array
    {
        return Alert::query()->forEmail($email)->oldest()->get()
            ->map(fn (Alert $alert) => [
                'id' => $alert->id,
                'product' => $alert->item?->product()?->name() ?? $alert->product_id,
                'product_id' => $alert->product_id,
                'variant' => $alert->variant !== '' ? $alert->variant : null,
                'site' => $alert->site,
                'email' => $alert->email,
                'user_id' => $alert->user_id,
                'status' => $alert->status->value,
                'consent' => $alert->consent,
                'asked_at' => $alert->created_at?->toIso8601String(),
                'confirmed_at' => $alert->confirmed_at?->toIso8601String(),
                'emailed_at' => $alert->notified_at?->toIso8601String(),
                'bought_at' => $alert->converted_at?->toIso8601String(),
                'order_id' => $alert->order_id,
                'cancelled_at' => $alert->cancelled_at?->toIso8601String(),
            ])
            ->values()
            ->all();
    }

    /**
     * Delete everything kept about an address (the right to be forgotten).
     * Returns how many requests were deleted.
     */
    public function forget(string $email): int
    {
        Suppression::lift($email);

        return Alert::query()->forEmail($email)->delete();
    }

    /**
     * How many people are waiting for a product or variant (confirmed only).
     */
    public function waitingCount(string $productId, ?string $variant = null): int
    {
        $query = Alert::query()->where('product_id', $productId)->where('status', AlertStatus::Waiting->value);

        if ($variant !== null) {
            $query->where('variant', $variant);
        }

        return $query->count();
    }

    /**
     * A signed-in person's open request for a product or variant, if any.
     */
    public function openFor(User $user, string $productId, ?string $variant = null): ?Alert
    {
        $query = Alert::query()
            ->where('product_id', $productId)
            ->open()
            ->where(function ($query) use ($user) {
                $query->where('user_id', (string) $user->id());

                if ($user->email()) {
                    $query->orWhere('email', Alert::normaliseEmail($user->email()));
                }
            });

        if ($variant !== null) {
            $query->where('variant', $variant);
        }

        return $query->oldest()->first();
    }

    /**
     * Email everyone waiting for a product or variant now, if it's in stock
     * (the Control Panel's "Send now").
     */
    public function sendNow(Item $item): int
    {
        $this->watcher->queue($item, null, 0);

        return app(Sender::class)->send($item->refresh(), force: true);
    }

    protected function tooManyConfirmations(string $email): bool
    {
        return RateLimiter::tooManyAttempts($this->confirmationKey($email), max(1, (int) config('back-in-stock.throttle.per_email', 5)));
    }

    protected function confirmationKey(string $email): string
    {
        return 'back-in-stock:confirm:'.Suppression::hash($email);
    }

    public function isValidEmail(string $email): bool
    {
        return strlen($email) <= 254 && filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
}
