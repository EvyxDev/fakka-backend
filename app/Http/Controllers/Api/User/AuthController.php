<?php

namespace App\Http\Controllers\Api\User;

use Carbon\Carbon;
use App\Models\User;
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

    protected $userService;

    // Inject UserService to handle the business logic
    public function __construct()
    {
    }
    // Register new user
    public function register(Request $request)
    {
        // Validate user input
        $validator = Validator::make($request->all(), [
            'username' => 'required|string',
            'phone' => 'required|string|unique:users,phone',
            'password' => 'required|string|min:6|confirmed',
            'profile_image' => 'nullable|file|image|mimes:jpeg,png,jpg,gif,svg|max:2048',          
        ]);
        if ($validator->fails()) {
            return $this->errorResponse(422, __('words.bad_request'), $validator->errors());
        }
        $user = User::create([
            'username' => $request->username,
            'phone' => $request->phone,
            'password' => Hash::make($request->password),
        ]);
        if ($request->hasFile('profile_image')) {
            $imagePath = uploadImage($request->file('profile_image'), 'user/profile_image');
            $user->profile_image = $imagePath;
            $user->save();
        }
        $otpService = new Otp();
        $phone = $user->phone;
        $otpService->generate($phone, 'numeric', 4, 10);
        return $this->successResponse(200, __('messages.otp_sent_successfully_to_your_phone'));
    }
    // Login user
    public function login(Request $request)
    {
        // Validate user input
        $validator = Validator::make($request->all(), [
            'phone' => 'required|string',
            'password' => 'required|string',
        ]);
        if ($validator->fails()) {
            return $this->errorResponse(422, __('words.bad_request'), $validator->errors());
        }
        $credentials = $request->only('phone', 'password');
        if (!Auth::attempt($credentials)) {
            return $this->errorResponse(401, __('messages.invalid_credentials'));
        }
        $user = Auth::user();
        $token = JWTAuth::fromUser($user);
        return $this->successResponse(200, __('messages.login_success'), [
            'token' => $token,
            'user' => new UserResource($user),
        ]);
    }
    // Logout user
    public function logout()
    {
        Auth::logout();
        return $this->successResponse(200, __('messages.logout_success'));
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
            $user = User::where('phone', $phone)->first();
            $user->phone_verified_at = Carbon::now();
            $token = JWTAuth::fromUser($user);
            $user->save();
            return $this->successResponse(200, __('messages.otp_verified_successfully'), [
                'token' => $token,
                'user' => new UserResource($user),
            ]);
        }
        return $this->errorResponse(400, __('messages.invalid_otp'));
    }
    // Resend OTP
    public function resendOtp(Request $request)
    {
        // Validate user input
        $validator = Validator::make($request->all(), [
            'phone' => 'required|string',
        ]);
        if ($validator->fails()) {
            return $this->errorResponse(422, __('words.bad_request'), $validator->errors());
        }
        $otpService = new Otp();
        $phone = $request->phone;
        $otpService->generate($phone, 'numeric', 4, 10);
        return $this->successResponse(200, __('messages.otp_sent_successfully_to_your_phone'));
    }
    // Forgot password
    public function forgotPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'phone' => 'required|string|exists:users,phone',
        ]);
        if ($validator->fails()) {
            return $this->errorResponse(422, __('words.bad_request'), $validator->errors());
        }
        $otpService = new Otp();
        $phone = $request->phone;
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
            $user = User::where('phone', $phone)->first();
            $user->password = Hash::make($request->password);
            $user->save();
            return $this->successResponse(200, __('messages.password_reset_success'));
        }
        return $this->errorResponse(400, __('messages.invalid_otp'));
    }
    // Change password
    public function changePassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'old_password' => 'required|string',
            'new_password' => 'required|string|min:6|confirmed',
        ]);
        if ($validator->fails()) {
            return $this->errorResponse(422, __('words.bad_request'), $validator->errors());
        }
        $user = Auth::user();
        if(!$user){
            return $this->errorResponse(400, __('messages.user_not_found'));
        }
        if (!Hash::check($request->old_password, $user->password)) {
            return $this->errorResponse(400, __('messages.invalid_old_password'));
        }
        if (Hash::check($request->new_password, $user->password)) {
            return $this->errorResponse(400, __('messages.new_password_must_be_different'));
        }
        $user->password = Hash::make($request->new_password);
        $user->save();
        return $this->successResponse(200, __('messages.password_changed_success'));
    }
}