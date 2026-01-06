<?php

use App\Http\Controllers\Api\Wallet\DepositController;
use Illuminate\Support\Facades\Route;

Route::prefix('deposit')
    ->middleware(['throttle:60,1'])
    ->group(function () {
        Route::get('/', [DepositController::class, 'index']);
        Route::post('/payment', [DepositController::class, 'submitPayment']);
    });

