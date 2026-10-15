<?php

namespace NineteenNinetyFour\BackInStock;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use NineteenNinetyFour\BackInStock\Cargo\Product;
use NineteenNinetyFour\BackInStock\Events\AlertCancelled;
use NineteenNinetyFour\BackInStock\Events\AlertNotified;
use NineteenNinetyFour\BackInStock\Jobs\SendAlerts;
use NineteenNinetyFour\BackInStock\Mail\RestockedMail;
use NineteenNinetyFour\BackInStock\Models\Alert;
use NineteenNinetyFour\BackInStock\Models\Item;
use NineteenNinetyFour\BackInStock\Models\Suppression;
use Throwable;

/**
 * Sending the back-in-stock emails for a product or variant: oldest
 * request first, never twice, no more a minute than the limit, and only
 * while it's still live and in stock.
 */
class Sender
{
    public const RATE_KEY = 'back-in-stock:emails';

    /**
     * Send what's due for one product or variant. Returns how many were emailed.
     */
    public function send(Item $item, bool $force = false): int
    {
        $lock = Cache::lock('back-in-stock:send:'.$item->id, 600);

        if (! $lock->get()) {
            return 0;
        }

        try {
            return $this->sendLocked($item, $force);
        } finally {
            $lock->release();
        }
    }

    protected function sendLocked(Item $item, bool $force): int
    {
        $item->refresh();

        if (! $item->pending) {
            return 0;
        }

        if (! $force && $item->send_after && $item->send_after->isFuture()) {
            return 0;
        }

        $product = $item->product();

        // Sold out again (a mistyped stock level put right, say), gone or
        // not live: nothing to send. Stock coming back starts again.
        if (! $product || ! $product->isAvailable() || $product->isSoldOut()) {
            $this->finish($item);

            return 0;
        }

        $remaining = $item->pending_limit;
        $sent = 0;

        while ($remaining === null || $remaining > 0) {
            $alert = $item->waiting()->orderBy('confirmed_at')->orderBy('created_at')->first();

            if (! $alert) {
                break;
            }

            if (RateLimiter::tooManyAttempts(self::RATE_KEY, Settings::perMinute())) {
                $this->later($item, $remaining, RateLimiter::availableIn(self::RATE_KEY));
                $this->recordSent($item, $sent);

                return $sent;
            }

            $result = $this->sendOne($alert, $product);

            if ($result === null) {
                // A mail problem: try again later, from this one.
                $this->later($item, $remaining, 60);
                $this->recordSent($item, $sent);

                return $sent;
            }

            if ($result) {
                $sent++;
                $remaining = $remaining === null ? null : $remaining - 1;
            }
        }

        $this->finish($item);
        $this->recordSent($item, $sent);

        return $sent;
    }

    /**
     * Email one person. True if emailed, false if skipped, null if the
     * email couldn't be sent.
     */
    public function sendOne(Alert $alert, Product $product): ?bool
    {
        // Claim it, so two sends at once can't both email it.
        $claimed = Alert::query()
            ->whereKey($alert->id)
            ->where('status', AlertStatus::Waiting->value)
            ->update(['status' => AlertStatus::Sending->value, 'claimed_at' => now()]);

        if (! $claimed) {
            return false;
        }

        $alert->refresh();

        if (Suppression::blocks($alert)) {
            $alert->forceFill(['status' => AlertStatus::Cancelled, 'cancelled_at' => now(), 'claimed_at' => null])->save();
            AlertCancelled::dispatch($alert);

            return false;
        }

        RateLimiter::hit(self::RATE_KEY, 60);

        try {
            $mail = Mail::to($alert->email)->locale($alert->locale ?? $alert->siteObject()->lang());

            if ($bcc = config('back-in-stock.email.bcc')) {
                $mail->bcc($bcc);
            }

            $mail->send(new RestockedMail($alert, $product));
        } catch (Throwable $e) {
            $alert->forceFill(['status' => AlertStatus::Waiting, 'claimed_at' => null])->save();
            Log::warning("Back in Stock couldn't email request {$alert->id}: {$e->getMessage()}");
            report($e);

            return null;
        }

        $alert->forceFill(['status' => AlertStatus::Notified, 'notified_at' => now(), 'claimed_at' => null])->save();

        AlertNotified::dispatch($alert);

        return true;
    }

    /**
     * Requests claimed by a send that never finished (the server stopped
     * mid-send, say) go back to waiting after an hour.
     */
    public function releaseStuck(): int
    {
        return Alert::query()
            ->where('status', AlertStatus::Sending->value)
            ->where('claimed_at', '<', now()->subHour())
            ->update(['status' => AlertStatus::Waiting->value, 'claimed_at' => null]);
    }

    /**
     * Send everything that's due (the scheduler's job, for sites without a queue).
     */
    public function sendDue(): int
    {
        return Item::query()
            ->where('pending', true)
            ->where(fn ($query) => $query->whereNull('send_after')->orWhere('send_after', '<=', now()))
            ->get()
            ->sum(fn (Item $item) => $this->send($item));
    }

    protected function later(Item $item, ?int $remaining, int $seconds): void
    {
        $item->forceFill([
            'pending_limit' => $remaining,
            'send_after' => now()->addSeconds(max(1, $seconds)),
        ])->save();

        if (SendAlerts::canWait()) {
            SendAlerts::dispatchFor($item);
        }

        // Without a queue, back-in-stock:check carries on in a few minutes.
    }

    protected function finish(Item $item): void
    {
        $item->forceFill(['pending' => false, 'pending_limit' => null, 'send_after' => null])->save();
    }

    protected function recordSent(Item $item, int $sent): void
    {
        if ($sent > 0 && $restock = $item->restocks()->latest('id')->first()) {
            $restock->increment('sent', $sent);
        }
    }
}
