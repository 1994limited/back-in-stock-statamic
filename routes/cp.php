<?php

use Illuminate\Support\Facades\Route;
use NineteenNinetyFour\BackInStock\Http\Controllers\CP\AlertsController;

Route::prefix('back-in-stock')->name('back-in-stock.')->group(function () {
    Route::get('/', [AlertsController::class, 'index'])->name('index');
    Route::get('export', [AlertsController::class, 'export'])->name('export');
    Route::get('person', [AlertsController::class, 'person'])->name('person');
    Route::get('person/download', [AlertsController::class, 'download'])->name('person.download');
    Route::post('person/forget', [AlertsController::class, 'forget'])->name('person.forget');
    Route::get('{item}', [AlertsController::class, 'show'])->name('show')->whereNumber('item');
    Route::post('{item}/send', [AlertsController::class, 'send'])->name('send')->whereNumber('item');
    Route::post('requests/{alert}/cancel', [AlertsController::class, 'cancel'])->name('cancel');
    Route::delete('requests/{alert}', [AlertsController::class, 'destroy'])->name('destroy');
});
