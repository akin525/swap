<?php

use App\Http\Controllers\Api\User\AuthController;
use App\Http\Controllers\Api\User\DashboardController;
use App\Http\Controllers\Api\User\NewAuthController;
use App\Http\Controllers\Api\User\TransactionController;
use App\Http\Controllers\Api\User\WalletController;
use Illuminate\Support\Facades\Route;

Route::get('swap/auth/country', [DashboardController::class, 'country']);

Route::group(['prefix' => 'swap/auth'], function () {
    Route::post('register', [NewAuthController::class, 'register']);
    Route::post('verify-phone', [NewAuthController::class, 'verifyPhone']);
    Route::post('resend-phone-code', [NewAuthController::class, 'resendPhoneCode']);
    Route::post('add-email', [NewAuthController::class, 'addEmail']);
    Route::get('verify-email', [NewAuthController::class, 'verifyEmail']);
    Route::post('resend-email-verification', [NewAuthController::class, 'resendEmailVerification']);
    Route::post('create-password', [NewAuthController::class, 'createPassword']);
    Route::post('user-info', [NewAuthController::class, 'addUserInfo']);
    Route::post('verify-bvn', [NewAuthController::class, 'verifyBvn']);
    Route::post('verify-bvn-code', [NewAuthController::class, 'verifyBvnCode']);
    Route::post('verify-face', [NewAuthController::class, 'verifyFace']);
    Route::post('login', [NewAuthController::class, 'login']);

    Route::middleware(['jwt.auth'])->group(function () {
        Route::post('logout', [NewAuthController::class, 'logout']);
        Route::post('refresh', [NewAuthController::class, 'refresh']);
        Route::get('me', [NewAuthController::class, 'me']);


        Route::get('/wallet/dashboard', [WalletController::class, 'dashboard']);
        Route::get('/wallets', [WalletController::class, 'getWallets']);
        Route::get('/wallet/{id}', [WalletController::class, 'getWallet']);
        Route::get('/wallet/currency/{currency}', [WalletController::class, 'getWalletByCurrency']);
        Route::post('/wallet/create', [WalletController::class, 'createWallet']);

        // Transaction routes
        Route::get('/transactions', [TransactionController::class, 'getTransactions']);
        Route::get('/transaction/{reference}', [TransactionController::class, 'getTransaction']);
        Route::post('/transaction/deposit', [TransactionController::class, 'deposit']);
        Route::post('/transaction/transfer', [TransactionController::class, 'transfer']);
        Route::post('/transaction/convert', [TransactionController::class, 'convert']);
        Route::post('/transaction/gift-card', [TransactionController::class, 'buyGiftCard']);
    });
});

