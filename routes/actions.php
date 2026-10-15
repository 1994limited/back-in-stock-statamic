<?php

use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Support\Facades\Route;
use NineteenNinetyFour\BackInStock\Http\Controllers\ActionsController;

// At /!/back-in-stock/...

// Asking to hear when something's back, and a signed-in customer
// cancelling one of theirs. Limited per visitor.
Route::middleware('throttle:back-in-stock')->group(function () {
    Route::post('subscribe', [ActionsController::class, 'subscribe'])->name('subscribe');
    Route::post('cancel', [ActionsController::class, 'cancel'])->name('cancel');
});

// The links in the emails. They're signed, so they can't be guessed or
// changed: confirming lasts a few days, unsubscribing doesn't expire.
// Each opens a page with a button, as mail apps and virus checkers open
// links in emails by themselves; only the button (a POST) does anything.
// The signature stands in for a CSRF token.
Route::get('confirm/{alert}', [ActionsController::class, 'confirmPage'])->name('confirm');
Route::post('confirm/{alert}', [ActionsController::class, 'confirm'])
    ->name('confirm.post')
    ->withoutMiddleware([ValidateCsrfToken::class, VerifyCsrfToken::class]);

Route::middleware('signed')->group(function () {
    Route::get('unsubscribe/{alert}', [ActionsController::class, 'unsubscribePage'])->name('unsubscribe');

    // Mail apps' one-click unsubscribe (RFC 8058) posts here too.
    Route::post('unsubscribe/{alert}', [ActionsController::class, 'unsubscribe'])
        ->name('unsubscribe.post')
        ->withoutMiddleware([ValidateCsrfToken::class, VerifyCsrfToken::class]);
});
