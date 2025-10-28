<?php

// use App\Http\Controllers\Api\Webhook\TelegramWebhookController;
use App\Http\Controllers\Api\User\AuthController;
use App\Http\Controllers\Api\User\DashboardController;
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

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});


// WEBHOOKS STARTS HERE
// Route::post('telegram/webhook', [TelegramWebhookController::class, 'webhook']);
// WEBHOOKS ENDS HERE


Route::post('v1/register', [AuthController::class, 'register']);
Route::post('v1/login', [AuthController::class, 'login']);
Route::post('v1/reset_password_code', [AuthController::class, 'reset_password_code']);
Route::post('v1/reset_password_code_submit', [AuthController::class, 'reset_password_code_submit']);
Route::get('v1/system-config', [DashboardController::class, 'settings']);

Route::group(['prefix' => 'v1','middleware' => ['auth:sanctum']], function () {
    //User Dashboard Routes
    Route::controller(DashboardController::class)->group(function () {
        Route::post('/verify-telegram', 'verifyTelegram');
        Route::post('/verify-telegram-otp', 'verifyTelegramCode');
        // email verification
    });
});


Route::group(['prefix' => 'v1','middleware' => ['auth:sanctum','CheckStatus']], function () {
    //User Dashboard Routes
    Route::controller(DashboardController::class)->group(function () {
        Route::get('/dashboard', 'index');
        Route::get('/referrals', 'referrals');
    });
});




require __DIR__ . '/admin.php';
require __DIR__ . '/swappay.php';
