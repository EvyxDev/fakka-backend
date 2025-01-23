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
use App\Http\Resources\Api\VendorResource;
use Illuminate\Support\Facades\Validator;

class AuthController extends Controller
{
    use ApiResponse;

    // Register vendor
    public function VendorRegister(Request $request)
    {
        // Validate user input
        $validator = Validator::make($request->all(), [
            'name' => 'required|string',
            'phone' => 'required|string|unique:vendors,phone',
            'password' => 'required|string|min:6|confirmed',
            'profile_image' => 'nullable|file|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
            // 'business_id' => 'required|integer|exists:businesses,id',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse(422, __('validation.errors'), $validator->errors());
        }

        $vendor = Vendor::create([
            'name' => $request->name,
            'phone' => $request->phone,
            'password' => Hash::make($request->password),
            'phonecode' => $request->phonecode,
            // 'business_id' => $request->business_id,
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
    public function VendorVerifyOtp(Request $request)
    {
        // Validate user input
        $validator = Validator::make($request->all(), [
            'phone' => 'required|string',
            'phonecode' => 'required|string',
            'otp' => 'required|string',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse(422, __('validation.errors'), $validator->errors());
        }

        $vendor = Vendor::where('phone', $request->phone)
            ->where('phonecode', $request->phonecode)
            ->first();

        if (!$vendor) {
            return $this->errorResponse(404, __('auth.vendor_not_found'));
        }

        // Validate the OTP
        $otpService = new Otp();
        $phone = $vendor->phone;
        $otp = $request->otp;
        $response = $otpService->validate($phone, $otp);

        if ($response->status) {
            // Mark the vendor's phone as verified
            $vendor->phone_verified_at = Carbon::now();
            $vendor->save();

            // Generate a JWT token for the authenticated vendor
            $token = JWTAuth::fromUser($vendor);

            return $this->successResponse(200, __('auth.otp_verified'), [
                'token' => $token,
                'vendor' => new VendorResource($vendor),
            ]);
        }

        return $this->errorResponse(400, __('auth.invalid_otp'));
    }
    // Login vendor
    public function VendorLogin(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'phone' => 'required|string',
            'phonecode' => 'required|string',
            'password' => 'required|string',
        ]);

        $vendor = Vendor::where('phone', $request->phone)
            ->where('phonecode', $request->phonecode)
            ->first();


        
        if ($validator->fails()) {
            return $this->errorResponse(422, __('validation.errors'), $validator->errors());
        }

        $credentials = $request->only('phone', 'password');

        if (!Auth::guard('vendor')->attempt($credentials)) {
            return $this->errorResponse(401, __('auth.invalid_credentials'));
        }

        $vendor = Auth::guard('vendor')->user();

		if ($vendor->phone_verified_at == null) {
            return $this->errorResponse(401, __('auth.phone_not_verified'));
        }
        $token = JWTAuth::fromUser($vendor);

        return $this->successResponse(200, __('auth.login_success'), [
            'token' => $token,
            'vendor' => new VendorResource($vendor),
        ]);
    }

    // Logout vendor
    public function VendorLogout(Request $request)
    {
        Auth::guard('vendor')->logout();

        return $this->successResponse(200, __('auth.logout_success'));
    }

    // Resend OTP
    public function VendorResendOtp(Request $request)
    {
        // Validate user input
        $validator = Validator::make($request->all(), [
            'phone' => 'required|string',
            'phonecode' => 'required|string',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse(422, __('validation.errors'), $validator->errors());
        }

        $vendor = Vendor::where('phone', $request->phone)
            ->where('phonecode', $request->phonecode)
            ->first();

        if (!$vendor) {
            return $this->errorResponse(404, __('auth.vendor_not_found'));
        }

        $otpService = new Otp();
        $phone = $vendor->phone;
        $otpService->generate($phone, 'numeric', 4, 10);

        return $this->successResponse(200, __('auth.otp_sent'), ['phone' => $phone]);
    }

    // Forgot password
    public function VendorForgotPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'phone' => 'required|string',
            'phonecode' => 'required|string',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse(422, __('validation.errors'), $validator->errors());
        }

        $vendor = Vendor::where('phone', $request->phone)
            ->where('phonecode', $request->phonecode)
            ->first();

        if (!$vendor) {
            return $this->errorResponse(404, __('auth.vendor_not_found'));
        }

        // Generate and send OTP
        $otpService = new Otp();
        $phone = $vendor->phone;
        $otpService->generate($phone, 'numeric', 4, 10);

        return $this->successResponse(200, __('auth.otp_sent'), ['phone' => $phone]);
    }

    // Reset password
    public function VendorResetPassword(Request $request)
    {
        // Validate user input
        $validator = Validator::make($request->all(), [
            'phone' => 'required|string',
            'phonecode' => 'required|string', // Add phonecode validation
            'password' => 'required|string|min:6|confirmed',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse(422, __('validation.errors'), $validator->errors());
        }

        $vendor = Vendor::where('phone', $request->phone)
            ->where('phonecode', $request->phonecode)
            ->first();

        if (!$vendor) {
            return $this->errorResponse(404, __('auth.vendor_not_found'));
        }

        $vendor->password = Hash::make($request->password);
        $vendor->save();

        return $this->successResponse(200, __('auth.password_reset_success'));
    }

    // Change password
    public function VendorChangePassword(Request $request)
    {
        // Validate user input
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
            return $this->errorResponse(400, __('auth.new_password_must_be_different'));
        }

        $vendor->password = Hash::make($request->new_password);
        $vendor->save();

        return $this->successResponse(200, __('auth.password_changed_success'));
    }

    //show profile
    public function VendorProfile()
    {
        $vendor = Auth::guard('vendor')->user();
        return $this->successResponse(200, __('auth.profile'), new VendorResource($vendor));
    }

    //delete profile
    public function VendorDeleteProfile()
    {
        $vendor = Auth::guard('vendor')->user();
        $vendor->delete();
        return $this->successResponse(200, __('auth.account_deleted_success'));
    }
}
