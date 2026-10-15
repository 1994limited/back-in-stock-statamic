<?php

namespace NineteenNinetyFour\BackInStock\Http\Controllers;

use DuncanMcClean\Cargo\Facades\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\URL;
use NineteenNinetyFour\BackInStock\AlertStatus;
use NineteenNinetyFour\BackInStock\BackInStock;
use NineteenNinetyFour\BackInStock\BackInStockException;
use NineteenNinetyFour\BackInStock\Http\ReadsText;
use NineteenNinetyFour\BackInStock\Models\Alert;
use NineteenNinetyFour\BackInStock\Settings;
use Statamic\Facades\Site;
use Statamic\Facades\User;

/**
 * The front-end actions, at /!/back-in-stock/... Forms answer JSON to
 * fetch() (Accept: application/json) and redirect back to a plain form
 * post, with the message flashed as "back_in_stock_success", or errors in
 * the "back_in_stock" error bag.
 */
class ActionsController
{
    use ReadsText;

    public function __construct(protected BackInStock $backInStock) {}

    public function subscribe(Request $request): JsonResponse|RedirectResponse
    {
        $productId = (string) $this->text($request, 'product', 255);
        $variant = (string) $this->text($request, 'variant', 255);
        $item = $productId.'|'.$variant;

        // The hidden field people can't see. Bots that fill it in are told
        // it worked, and nothing happens.
        if ($this->text($request, (string) config('back-in-stock.honeypot', 'website')) !== null) {
            return $this->done($request, __('back-in-stock::messages.subscribed'), $item);
        }

        $product = $productId !== '' ? $this->backInStock->product($productId, $variant) : null;

        if (! $product) {
            $entry = $productId !== '' ? rescue(fn () => Product::find($productId), null, false) : null;
            $field = $entry && $entry->isVariantProduct() ? 'variant' : 'product';

            return $this->invalid($request, [$field => __('back-in-stock::messages.errors.'.$field)], $item);
        }

        $user = User::current();
        $email = $this->text($request, 'email', 254) ?? ($user?->email());
        $errors = [];

        if (! is_string($email) || ! $this->backInStock->isValidEmail(Alert::normaliseEmail($email))) {
            $errors['email'] = __('back-in-stock::messages.errors.email');
        }

        if (! $request->boolean('consent')) {
            $errors['consent'] = __('back-in-stock::messages.errors.consent');
        }

        if ($errors) {
            return $this->invalid($request, $errors, $item);
        }

        try {
            $this->backInStock->subscribe(
                $product,
                $email,
                Site::current(),
                $user && $user->email() && Alert::normaliseEmail($user->email()) === Alert::normaliseEmail($email) ? $user : null,
                Settings::text('consent_text'),
            );
        } catch (BackInStockException $e) {
            return $this->invalid($request, [$e->field => $e->getMessage()], $item, $e->status);
        }

        // The same answer whether or not they'd asked before, so the form
        // can't be used to find out who's waiting for what.
        return $this->done($request, __('back-in-stock::messages.subscribed'), $item);
    }

    /**
     * A signed-in customer cancelling one of their requests.
     */
    public function cancel(Request $request): JsonResponse|RedirectResponse
    {
        $user = User::current();
        $alert = ($id = $this->text($request, 'alert', 64)) ? Alert::find($id) : null;

        if (! $user || ! $alert || ! $this->owns($user, $alert) || ! $this->backInStock->cancel($alert)) {
            return $this->invalid($request, ['alert' => __('back-in-stock::messages.errors.not_yours')], '');
        }

        return $this->done($request, __('back-in-stock::messages.cancelled'), $alert->product_id.'|'.$alert->variant);
    }

    /**
     * The link in the confirmation email: a page with a button, so a link
     * checker opening it doesn't confirm anything.
     */
    public function confirmPage(Request $request, string $alert): Response
    {
        $alert = Alert::find($alert);

        if (! $alert || ! $request->hasValidSignature() || $alert->status === AlertStatus::Cancelled) {
            return $this->page('expired_title', __('back-in-stock::messages.errors.expired'), $alert, status: 403);
        }

        if ($alert->status !== AlertStatus::Unconfirmed) {
            return $this->page('confirmed_title', __('back-in-stock::messages.already_confirmed'), $alert);
        }

        $product = $alert->item?->product();

        return $this->page('confirm_title', __('back-in-stock::messages.page.confirm_text', [
            'product' => $product?->name() ?? $alert->product_id,
            'email' => $alert->email,
        ]), $alert, [
            'action' => $request->fullUrl(),
            'button' => Settings::text('confirm_button', ['product' => $product?->name() ?? ''], $alert->locale),
        ]);
    }

    public function confirm(Request $request, string $alert): Response|RedirectResponse
    {
        $alert = Alert::find($alert);

        if (! $alert || ! $request->hasValidSignature() || $alert->status === AlertStatus::Cancelled) {
            return $this->page('expired_title', __('back-in-stock::messages.errors.expired'), $alert, status: 403);
        }

        $message = $this->backInStock->confirm($alert)
            ? __('back-in-stock::messages.confirmed')
            : __('back-in-stock::messages.already_confirmed');

        return $this->landing('confirmed', 'confirmed_title', $message, $alert);
    }

    /**
     * The unsubscribe link opens a page with a button, so link checkers
     * that open links in emails don't unsubscribe anyone.
     */
    public function unsubscribePage(string $alert): Response
    {
        $alert = Alert::findOrFail($alert);

        return $this->page('unsubscribe_title', __('back-in-stock::messages.page.unsubscribe_text', ['email' => $alert->email]), $alert, [
            'action' => URL::signedRoute('statamic.back-in-stock.unsubscribe.post', ['alert' => $alert->id]),
            'button' => __('back-in-stock::messages.page.unsubscribe_button'),
        ]);
    }

    public function unsubscribe(Request $request, string $alert): Response|RedirectResponse
    {
        $alert = Alert::find($alert);

        // Deleted (forgotten) since the email: nothing left to stop.
        if (! $alert) {
            return $this->page('unsubscribed_title', __('back-in-stock::messages.already_unsubscribed'));
        }

        $this->backInStock->unsubscribe($alert->email);

        // Mail apps' one-click unsubscribe just needs a 200.
        if ($request->input('List-Unsubscribe') === 'One-Click') {
            return response('', 200);
        }

        return $this->landing('unsubscribed', 'unsubscribed_title', __('back-in-stock::messages.unsubscribed'), $alert);
    }

    protected function owns(\Statamic\Contracts\Auth\User $user, Alert $alert): bool
    {
        return $alert->user_id === (string) $user->id()
            || ($user->email() && Alert::normaliseEmail($user->email()) === $alert->email);
    }

    /**
     * After confirming or unsubscribing: the site's own page, if one's set
     * in config('back-in-stock.pages'), or the addon's.
     */
    protected function landing(string $page, string $title, string $message, Alert $alert): Response|RedirectResponse
    {
        $to = config("back-in-stock.pages.{$page}");

        if (is_string($to) && $to !== '') {
            return redirect($to)->with('back_in_stock_success', $message);
        }

        return $this->page($title, $message, $alert);
    }

    /**
     * @param  array<string, string>|null  $form
     */
    protected function page(string $title, string $message, ?Alert $alert = null, ?array $form = null, int $status = 200): Response
    {
        $site = $alert?->siteObject() ?? Site::current();

        app()->setLocale($alert?->locale ?? $site->lang());

        return response()->view(config('back-in-stock.views.page', 'back-in-stock::page'), [
            'title' => __('back-in-stock::messages.page.'.$title),
            'message' => $message,
            'form' => $form,
            'siteName' => $site->name(),
            'siteUrl' => $site->absoluteUrl(),
            'locale' => str_replace('_', '-', $site->locale()),
            'colour' => Settings::colour(),
            'onColour' => Settings::onColour(),
        ], $status)->header('Cache-Control', 'no-store, private')->header('X-Robots-Tag', 'noindex');
    }

    protected function done(Request $request, string $message, string $item): JsonResponse|RedirectResponse
    {
        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'message' => $message]);
        }

        return $this->back($request, '_redirect')
            ->with('back_in_stock_success', $message)
            ->with('back_in_stock_item', $item);
    }

    /**
     * @param  array<string, string>  $errors
     */
    protected function invalid(Request $request, array $errors, string $item, int $status = 422): JsonResponse|RedirectResponse
    {
        if ($request->wantsJson()) {
            return response()->json(['success' => false, 'message' => reset($errors), 'errors' => $errors], $status);
        }

        return $this->back($request, '_error_redirect')
            ->withErrors($errors + ['back_in_stock' => reset($errors)], 'back_in_stock')
            ->withInput($request->only(['email', 'variant', 'consent']))
            ->with('back_in_stock_item', $item);
    }

    /**
     * Back to where the form was, or to a "_redirect" on this site.
     */
    protected function back(Request $request, string $field): RedirectResponse
    {
        $to = (string) $this->text($request, $field);
        $home = rtrim(url('/'), '/').'/';

        // A path on this site, or a full URL that starts with this site's.
        // Nothing with a backslash or control characters, which browsers
        // read differently from PHP.
        $safe = $to !== '' && ! preg_match('/[\\\\\x00-\x1F]/', $to);
        $local = $safe && ((str_starts_with($to, '/') && ! str_starts_with($to, '//')) || str_starts_with($to, $home));

        return $local ? redirect($to) : back();
    }
}
