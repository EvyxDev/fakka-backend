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
            'pincode' => rand(1000, 9999),
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
                'user' => new UserResource($vendor),
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
}
