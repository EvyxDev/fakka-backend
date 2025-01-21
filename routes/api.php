<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\User\PinController;
use App\Http\Controllers\Api\User\AuthController;
use App\Http\Controllers\Api\Vendor\AuthController as VendorAuthController;
use App\Models\Vendor;

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


Route::post('register', [AuthController::class, 'register']);
Route::post('login', [AuthController::class, 'login']);
Route::post('verify-otp', [AuthController::class, 'verifyOtp']);
Route::post('resend-otp', [AuthController::class, 'resendOtp']);
Route::post('logout', [AuthController::class, 'logout']);
Route::post('reset-password', [AuthController::class, 'resetPassword']);
Route::post('change-password',[AuthController::class, 'changePassword']);
Route::post('forget-password', [AuthController::class, 'forgotPassword']);

Route::prefix('pin')->group(function () {
    Route::post('set-pin', [PinController::class, 'setpinCode']);
    Route::post('changePinCode' , [PinController::class, 'changePinCode']);
});


Route::prefix('vendor')->group(function () {
    Route::post('register', [VendorAuthController::class, 'register']);
    Route::post('verify-otp', [VendorAuthController::class, 'verifyOtp']);
    Route::post('login', [VendorAuthController::class, 'login']);
    Route::post('logout',[VendorAuthController::class, 'logout']);
    Route::post('forgot-password', [VendorAuthController::class, 'forgotPassword']);
    Route::post('reset-password', [VendorAuthController::class, 'resetPassword']);
    Route::post('change-password',[VendorAuthController::class, 'changePassword']);
});