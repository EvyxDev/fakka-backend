<?php

namespace App\Http\Controllers\Api\Vendor;

use Carbon\Carbon;
use App\Models\Vendor;
use Ichtrojan\Otp\Otp;
use App\Models\Voucher;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Tymon\JWTAuth\Facades\JWTAuth;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Artisan;
use App\Http\Resources\API\UserResource;
use Illuminate\Support\Facades\Validator;
use App\Http\Resources\API\VendorResource;

class AuthController extends Controller
{
    use ApiResponse;

    //register vendor
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
            return $this->errorResponse(422, __('words.bad_request'), $validator->errors());
        }
        $vendor = Vendor::create([
            'name' => $request->name,
            'phone' => $request->phone,
            'password' => Hash::make($request->password),
            'business_id' => $request->business_id,
        ]);
        // if ($request->hasFile('profile_image')) {
        //     $imagePath = uploadImage($request->profile_image, 'profile_image', 'images/vendor');
        //     $vendor->profile_image = $imagePath;
        //     $vendor->save();
        // }
        $otpService = new Otp();
        $phone = $vendor->phone;
        $otpService->generate($phone, 'numeric', 4, 10);
        return $this->successResponse(200, __('messages.otp_sent_successfully_to_your_phone'));
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
            return $this->errorResponse(422, __('words.bad_request'), $validator->errors());
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
            return $this->successResponse(200, __('messages.otp_verified_successfully'), [
                'token' => $token,
                'vendor' => new VendorResource($vendor),
            ]);
        }
        return $this->errorResponse(400, __('messages.invalid_otp'));
    }
    // Login vendor
    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'phone' => 'required|string',
            'password' => 'required|string',
        ]);
        if ($validator->fails()) {
            return $this->errorResponse(422, __('words.bad_request'), $validator->errors());
        }
        $credentials = $request->only('phone', 'password');
        if (!Auth::attempt($credentials)) {
            return $this->errorResponse(401, __('messages.unauthorized'));
        }
    }
    // Logout vendor
    public function logout(Request $request)
    {
        Auth::guard('vendor')->logout();

        return $this->successResponse(200, __('messages.logout_success'));
    }
    // Forgot password
    public function forgotPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'phone' => 'required|string|exists:vendors,phone',
        ]);
        if ($validator->fails()) {
            return $this->errorResponse(422, __('words.bad_request'), $validator->errors());
        }
        $vendor = Vendor::where('phone', $request->phone)->first();
        $otpService = new Otp();
        $phone = $vendor->phone;
        $otpService->generate($phone, 'numeric', 4, 10);
        return $this->successResponse(200, __('messages.otp_sent_successfully_to_your_phone'));
    }
    // Reset password
    public function resetPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'phone' => 'required|string',
            'password' => 'required|string|min:6|confirmed',
        ]);
        if ($validator->fails()) {
            return $this->errorResponse(422, __('words.bad_request'), $validator->errors());
        }
        $phone = $request->phone;
        if ($phone) {
            $vendor = Vendor::where('phone', $phone)->first();
            $vendor->password = Hash::make($request->password);
            $vendor->save();
            return $this->successResponse(200, __('messages.password_reset_success'));
        }
    }
    // Change password
    public function changePassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'old_password' => 'required|string|min:6',
            'new_password' => 'required|string|min:6|confirmed',
        ]);
        if ($validator->fails()) {
            return $this->errorResponse(422, __('words.bad_request'), $validator->errors());
        }
        $vendor = Auth::guard('vendor')->user();
        if(!$vendor){
            return $this->errorResponse(400, __('messages.user_not_found'));
        }
        if (!Hash::check($request->old_password, Auth::guard('vendor')->user()->password)) {
            return $this->errorResponse(400, __('messages.invalid_old_password'));
        }
        if (Hash::check($request->new_password, Auth::guard('vendor')->user()->password)) {
            return $this->errorResponse(400, __('messages.new_password_same_as_old_password'));
        }
        $user = Auth::guard('vendor')->user();
        $user->password = Hash::make($request->password);
        $user->save();
        return $this->successResponse(200, __('messages.password_changed_successfully'));
    }
}
