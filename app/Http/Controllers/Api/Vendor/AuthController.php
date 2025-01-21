<?php

namespace App\Http\Controllers\Api\Vendor;

use Carbon\Carbon;
use App\Models\Vendor;
use Ichtrojan\Otp\Otp;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Tymon\JWTAuth\Facades\JWTAuth;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use App\Http\Resources\API\VendorResource;
use Illuminate\Support\Facades\Validator;

class AuthController extends Controller
{
    use ApiResponse;

    // Register vendor
    public function register(Request $request)
    {
        // Validate user input
        $validator = Validator::make($request->all(), [
            'name' => 'required|string',
            'phone' => 'required|string|unique:vendors,phone',
            'password' => 'required|string|min:6|confirmed',
            'profile_image' => 'nullable|file|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
            'business_id' => 'required|integer|exists:businesses,id',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse(422, __('validation.errors'), $validator->errors());
        }

        $vendor = Vendor::create([
            'name' => $request->name,
            'phone' => $request->phone,
            'password' => Hash::make($request->password),
            'business_id' => $request->business_id,
        ]);

        if ($request->hasFile('profile_image')) {
            $imagePath = uploadImage($request->file('profile_image'), 'vendor/profile_image');
            $vendor->profile_image = $imagePath;
            $vendor->save();
        }

        $otpService = new Otp();
        $phone = $vendor->phone;
        $otpService->generate($phone, 'numeric', 4, 10);

        return $this->successResponse(201, __('auth.otp_sent'), ['phone' => $phone]);
    }

    // Verify OTP
    public function verifyOtp(Request $request)
    {
        // Validate user input
        $validator = Validator::make($request->all(), [
            'phone' => 'required|string',
            'otp' => 'required|string',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse(422, __('validation.errors'), $validator->errors());
        }

        $otpService = new Otp();
        $phone = $request->phone;
        $otp = $request->otp;
        $response = $otpService->validate($phone, $otp);

        if ($response->status) {
            $vendor = Vendor::where('phone', $phone)->first();
            $vendor->phone_verified_at = Carbon::now();
            $token = JWTAuth::fromUser($vendor);
            $vendor->save();

            return $this->successResponse(200, __('auth.otp_verified'), [
                'token' => $token,
                'vendor' => new VendorResource($vendor),
            ]);
        }

        return $this->errorResponse(400, __('auth.invalid_otp'));
    }

    // Login vendor
    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'phone' => 'required|string',
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse(422, __('validation.errors'), $validator->errors());
        }

        $credentials = $request->only('phone', 'password');

        if (!Auth::guard('vendor')->attempt($credentials)) {
            return $this->errorResponse(401, __('auth.invalid_credentials'));
        }

        $vendor = Auth::guard('vendor')->user();
        $token = JWTAuth::fromUser($vendor);

        return $this->successResponse(200, __('auth.login_success'), [
            'token' => $token,
            'vendor' => new VendorResource($vendor),
        ]);
    }

    // Logout vendor
    public function logout(Request $request)
    {
        Auth::guard('vendor')->logout();

        return $this->successResponse(200, __('auth.logout_success'));
    }

    // Resend OTP
    public function resendOtp(Request $request)
    {
        // Validate user input
        $validator = Validator::make($request->all(), [
            'phone' => 'required|string',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse(422, __('validation.errors'), $validator->errors());
        }

        $otpService = new Otp();
        $phone = $request->phone;
        $otpService->generate($phone, 'numeric', 4, 10);

        return $this->successResponse(200, __('auth.otp_sent'), ['phone' => $phone]);
    }

    // Forgot password
    public function forgotPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'phone' => 'required|string|exists:vendors,phone',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse(422, __('validation.errors'), $validator->errors());
        }

        $vendor = Vendor::where('phone', $request->phone)->first();

        if (!$vendor) {
            return $this->errorResponse(404, __('auth.vendor_not_found'));
        }

        $otpService = new Otp();
        $phone = $vendor->phone;
        $otpService->generate($phone, 'numeric', 4, 10);

        return $this->successResponse(200, __('auth.otp_sent'), ['phone' => $phone]);
    }

    // Reset password
    public function resetPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'phone' => 'required|string',
            'password' => 'required|string|min:6|confirmed',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse(422, __('validation.errors'), $validator->errors());
        }

        $vendor = Vendor::where('phone', $request->phone)->first();

        if (!$vendor) {
            return $this->errorResponse(404, __('auth.vendor_not_found'));
        }

        $vendor->password = Hash::make($request->password);
        $vendor->save();

        return $this->successResponse(200, __('auth.password_reset_success'));
    }

    // Change password
    public function changePassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'old_password' => 'required|string|min:6',
            'new_password' => 'required|string|min:6|confirmed',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse(422, __('validation.errors'), $validator->errors());
        }

        $vendor = Auth::guard('vendor')->user();

        if (!$vendor) {
            return $this->errorResponse(404, __('auth.vendor_not_found'));
        }

        if (!Hash::check($request->old_password, $vendor->password)) {
            return $this->errorResponse(400, __('auth.invalid_old_password'));
        }

        if (Hash::check($request->new_password, $vendor->password)) {
            return $this->errorResponse(400, __('auth.new_password_must_differ'));
        }

        $vendor->password = Hash::make($request->new_password);
        $vendor->save();

        return $this->successResponse(200, __('auth.password_changed_success'));
    }
}