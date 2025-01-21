<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\User\PinController as UserPinController;
use App\Http\Controllers\Api\User\AuthController as UserAuthController;
use App\Http\Controllers\Api\Vendor\AuthController as VendorAuthController;
use App\Http\Controllers\Api\User\PinController as VendorPinController;

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

Route::prefix('user')->group(function () {

    Route::post('register', [UserAuthController::class, 'register']);
    Route::post('login', [UserAuthController::class, 'login']);
    Route::post('verify-otp', [UserAuthController::class, 'verifyOtp']);
    Route::post('resend-otp', [UserAuthController::class, 'resendOtp']);
    Route::post('logout', [UserAuthController::class, 'logout']);
    Route::post('reset-password', [UserAuthController::class, 'resetPassword']);
    Route::post('change-password',[UserAuthController::class, 'changePassword']);
    Route::post('forget-password', [UserAuthController::class, 'forgotPassword']);
    Route::post('set-pin', [UserPinController::class, 'setpinCode']);
    Route::post('changePinCode' , [UserPinController::class, 'changePinCode']);
});


Route::prefix('vendor')->group(function () {
    Route::post('register', [VendorAuthController::class, 'register']);
    Route::post('login', [VendorAuthController::class, 'login']);
    Route::post('verify-otp', [VendorAuthController::class, 'verifyOtp']);
    Route::post('resend-otp', [VendorAuthController::class, 'resendOtp']);
    Route::post('logout',[VendorAuthController::class, 'logout']);
    Route::post('reset-password', [VendorAuthController::class, 'resetPassword']);
    Route::post('change-password',[VendorAuthController::class, 'changePassword']);
    Route::post('forgot-password', [VendorAuthController::class, 'forgotPassword']);
    Route::post('set-pin', [VendorPinController::class, 'setpinCode']);
    Route::post('changePinCode' , [VendorPinController::class, 'changePinCode']);
    
});