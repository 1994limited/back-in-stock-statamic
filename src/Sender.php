<?php

namespace NineteenNinetyFour\BackInStock;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
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

    /** The most people one pass looks at; the rest go in the next. */
    public const BATCH = 500;

    /**
     * Send what's due for one product or variant. Returns how many were emailed.
     */
    public function send(Item $item, bool $force = false): int
    {
        $lock = Cache::lock('back-in-stock:send:'.$item->id, 600);

        if (! $lock->get()) {
            return 0;
        }

        $this->again = false;

        try {
            $sent = $this->sendLocked($item, $force);
        } finally {
            $lock->release();
        }

        // More to send (lined up meanwhile, or past one pass): with a
        // queue, a new job. Without one, back-in-stock:check carries on in
        // a few minutes, rather than sending on and on in this request.
        if ($this->again && SendAlerts::canWait()) {
            SendAlerts::dispatchFor($item->refresh());
        }

        return $sent;
    }

    /** Whether more emails were lined up while sending. */
    protected bool $again = false;

    protected function sendLocked(Item $item, bool $force): int
    {
        $item->refresh();

        if (! $item->pending) {
            return 0;
        }

        if (! $force && $item->send_after && $item->send_after->isFuture()) {
            return 0;
        }

        $version = (int) $item->pending_version;
        $product = $item->product();

        // Sold out again (a mistyped stock level put right, say), gone or
        // not live: nothing to send. Stock coming back starts again.
        if (! $product || ! $product->isAvailable() || $product->isSoldOut()) {
            $this->finish($item, $version);

            return 0;
        }

        $sent = 0;
        $tries = 0;

        while (true) {
            // A bounded pass: anything left goes in the next one.
            if (++$tries > static::BATCH) {
                $this->again = true;
                $this->recordSent($item, $sent);

                return $sent;
            }

            // What's left to send, as it is now: units sold since the
            // restock come off it, and anything lined up meanwhile is added.
            $item->refresh();
            $limit = $item->pending_limit;
            $item->stock = ($item->product() ?? $product)->stock();
            app(Watcher::class)->capToStock($item);
            $remaining = $item->pending_limit;

            if ($remaining !== null && $limit !== null && $remaining < $limit) {
                // Only if nothing was lined up since this was read: that
                // would be lost. If it was, read it again.
                $capped = Item::query()
                    ->whereKey($item->id)
                    ->where('pending_version', $item->pending_version)
                    ->where('pending_limit', $limit)
                    ->update(['pending_limit' => $remaining]);

                if (! $capped) {
                    continue;
                }
            }

            if ($remaining !== null && $remaining <= 0) {
                break;
            }

            $alert = $item->waiting()->orderBy('confirmed_at')->orderBy('created_at')->first();

            if (! $alert) {
                break;
            }

            if (RateLimiter::tooManyAttempts(self::RATE_KEY, Settings::perMinute())) {
                $this->later($item, RateLimiter::availableIn(self::RATE_KEY), $remaining);
                $this->recordSent($item, $sent);

                return $sent;
            }

            $result = $this->sendOne($alert, $product);

            if ($result === null) {
                // A mail problem: try again later, from this one.
                $this->later($item, 60, $remaining);
                $this->recordSent($item, $sent);

                return $sent;
            }

            if ($result) {
                $sent++;

                if ($remaining !== null) {
                    Item::query()->whereKey($item->id)->update(['pending_limit' => DB::raw('case when pending_limit > 0 then pending_limit - 1 else 0 end')]);
                }
            }
        }

        $this->finish($item, $version);
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

        // Unsubscribed since, or a second request for the same thing from
        // the same address (two forms sent at once) that's been emailed.
        // When two are being sent at once, the older one goes.
        $duplicate = Alert::query()
            ->where('item_id', $alert->item_id)
            ->where('email', $alert->email)
            ->whereKeyNot($alert->id)
            ->where(fn ($query) => $query
                ->whereIn('status', [AlertStatus::Notified->value, AlertStatus::Converted->value])
                ->orWhere(fn ($query) => $query->where('status', AlertStatus::Sending->value)->where('id', '<', $alert->id)))
            ->exists();

        if ($duplicate || Suppression::blocks($alert)) {
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

    protected function later(Item $item, int $seconds, ?int $remaining): void
    {
        // pending_limit is already counted down as emails go; just wait.
        Item::query()->whereKey($item->id)->update([
            'send_after' => now()->addSeconds(max(1, $seconds)),
        ]);

        if (SendAlerts::canWait()) {
            SendAlerts::dispatchFor($item->refresh());
        }

        // Without a queue, back-in-stock:check carries on in a few minutes.
    }

    /**
     * Done, unless more was lined up while sending (the version moved on).
     */
    protected function finish(Item $item, int $version): void
    {
        $finished = Item::query()
            ->whereKey($item->id)
            ->where('pending_version', $version)
            ->update(['pending' => false, 'pending_limit' => null, 'send_after' => null]);

        if (! $finished) {
            $this->again = true;
        }
    }

    protected function recordSent(Item $item, int $sent): void
    {
        if ($sent > 0 && $restock = $item->restocks()->latest('id')->first()) {
            $restock->increment('sent', $sent);
        }
    }
}
