<?php

use App\Models\Vendor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Bussiness\BussinessController;
use App\Http\Controllers\Api\Transaction\TransactionController;
use App\Http\Controllers\Api\User\PinController as UserPinController;
use App\Http\Controllers\Api\User\AuthController as UserAuthController;
use App\Http\Controllers\Api\Vendor\PinController as VendorPinController;
use App\Http\Controllers\Api\Vendor\AuthController as VendorAuthController;
use App\Http\Controllers\Api\Brand\BrandController;


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

Route::get('/get-user-or-vendor', [UserAuthController::class, 'getUserOrVendor']);

// Route::middleware(['auth:api', 'Check.token.expiration'])->group(function () {
    Route::prefix('user')->middleware('Localization')->group(function () {
        Route::post('register', [UserAuthController::class, 'UserRegister']);
        Route::post('login', [UserAuthController::class, 'UserLogin']);
        Route::post('verify-otp', [UserAuthController::class, 'UserVerifyOtp']);
        Route::post('resend-otp', [UserAuthController::class, 'UserResendOtp']);
        Route::post('logout', [UserAuthController::class, 'UserLogout']);
        Route::post('reset-password', [UserAuthController::class, 'UserResetPassword']);
        Route::post('change-password', [UserAuthController::class, 'UserChangePassword']);
        Route::post('forget-password', [UserAuthController::class, 'UserForgotPassword']);
        Route::get('profile', [UserAuthController::class, 'UserProfile']);
        Route::post('set-pin', [UserPinController::class, 'UserSetpinCode']);
        Route::post('changePinCode', [UserPinController::class, 'UserChangePinCode']);
        Route::post('changePinCode', [UserPinController::class, 'UserChangePinCode']);
        Route::post('delete-account', [UserAuthController::class, 'UserDeleteAccount']);
        Route::post('verify-pin', [UserPinController::class, 'UserVerifyPinCode']);
        Route::post('Check-token',[UserAuthController::class,'UserTokenCheck']);
        Route::post('request-pin-reset-otp', [UserPinController::class, 'UserRequestPinResetOtp']);
        Route::post('verify-pin-reset-otp', [UserPinController::class, 'UserVerifyPinResetOtp']);
        Route::post('reset-pin-code', [UserPinController::class, 'UserResetPinCode']);
    });
    Route::prefix('vendor')->middleware('Localization')->group(function () {
        Route::post('register', [VendorAuthController::class, 'VendorRegister']);
        Route::post('login', [VendorAuthController::class, 'VendorLogin']);
        Route::post('verify-otp', [VendorAuthController::class, 'VendorVerifyOtp']);
        Route::post('resend-otp', [VendorAuthController::class, 'VendorResendOtp']);
        Route::post('logout', [VendorAuthController::class, 'VendorLogout']);
        Route::post('reset-password', [VendorAuthController::class, 'VendorResetPassword']);
        Route::post('change-password', [VendorAuthController::class, 'VendorChangePassword']);
        Route::post('forgot-password', [VendorAuthController::class, 'VendorForgotPassword']);
        Route::post('set-pin', [VendorPinController::class, 'VendorSetpinCode']);
        Route::post('changePinCode', [VendorPinController::class, 'VendorChangePinCode']);
        Route::get('profile', [VendorAuthController::class, 'VendorProfile']);
        Route::post('delete-account', [VendorAuthController::class, 'VendorDeleteAccount']);
        Route::post('verify-pin', [VendorPinController::class, 'VendorVerifyPinCode']);
        Route::post('Check-token',[VendorAuthController::class,'VendorTokenCheck']);
        Route::post('request-pin-reset-otp', [VendorPinController::class, 'VendorRequestPinResetOtp']);
        Route::post('verify-pin-reset-otp', [VendorPinController::class, 'VendorVerifyPinResetOtp']);
        Route::post('reset-pin-code', [VendorPinController::class, 'VendorResetPinCode']);
    });
    Route::prefix('Transaction')->group(function () {
        Route::post('generateQrCode', [TransactionController::class, 'generateQrCode']);
        Route::post('scanQrCode', [TransactionController::class, 'scanQrCode']);
        Route::get('transactionHistory', [TransactionController::class, 'transactionHistory']);
    });
    Route::get('business', [BussinessController::class, 'index']);
    Route::get('notifications', [TransactionController::class, 'getNotifications']);
    
    Route::get('/user/transactions', [TransactionController::class, 'getUserTransactions']);
    Route::get('/user/transactions-by-month', [TransactionController::class, 'getUserTransactionsByMonth']);

    Route::post('/brands', [BrandController::class, 'storeBrand']);
    Route::get('/brands', [BrandController::class, 'indexBrand']);

// });
