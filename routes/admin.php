<?php

use App\Http\Controllers\Api\Admin\AuthController;
use App\Http\Controllers\Api\Admin\DashboardController;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/


Route::post('sc/login', [AuthController::class, 'login']);


Route::group(['prefix' => 'sc','middleware' => ['CheckAdminStatus']], function () {
    //Admin Auth Routes
    Route::controller(AuthController::class)->group(function () {
        Route::post('/change-password', 'changePassword');
    });

    //User Dashboard Routes
    Route::controller(DashboardController::class)->group(function () {
        Route::get('/dashboard', 'index');
        Route::get('/settings', 'settings');
        Route::get('/users', 'users');
        Route::get('/user-details/{id}', 'userDetails');
        Route::get('/user-status-update/{id}/{status}', 'updateUserStatus');
        Route::get('/bids/{status}', 'bids');
        Route::get('/bid-details/{id}', 'bidDetails');
        Route::get('/cancel-bid/{id}', 'cancelBid');
        Route::get('/asks/{status}', 'asks');
        Route::get('/ask-details/{id}', 'askDetails');
        Route::get('/cancel-ask/{id}', 'cancelAsk');
        Route::get('/plans', 'plans');
        Route::get('/peers/{status}', 'peers');
        Route::get('/investments/{status}', 'investments');
        Route::get('/investment-details/{id}', 'investmentDetails');
        Route::post('/approve-payment/{peer_id}', 'adminConfirmPayment');
        Route::get('/unpair-peering/{peer_id}', 'unpairPeering');
        Route::post('/bot-cast', 'botCast');
    });
});
