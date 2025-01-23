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
use App\Http\Resources\Api\UserResource;
use Illuminate\Support\Facades\Validator;

class AuthController extends Controller
{
    use ApiResponse;

    protected $userService;

    // Inject UserService to handle the business logic
    public function __construct() {}
    // Register new user
    public function UserRegister(Request $request)
    {
        // Validate user input
        $validator = Validator::make($request->all(), [
            'name' => 'required|string',
            'phone' => 'required|string|unique:users,phone',
            'password' => 'required|string|min:6|confirmed',
            'profile_image' => 'nullable|file|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse(422, __('validation.errors'), $validator->errors());
        }

        $user = User::create([
            'name' => $request->name,
            'phone' => $request->phone,
            'phonecode'=>$request->phonecode,
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

        return $this->successResponse(201, __('auth.otp_sent'), ['phone' => $phone]);
    }

    // Login user
    public function UserLogin(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'phone' => 'required|string',
            'phonecode' => 'required|string', 
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse(422, __('validation.errors'), $validator->errors());
        }

        $user = User::where('phone', $request->phone)
                    ->where('phonecode', $request->phonecode)
                    ->first();

        
        if (!$user) {
            return $this->errorResponse(404, __('auth.user_not_found'));
        }

        if ($user->phone_verified_at == null) {
            return $this->successResponse(200, __('auth.phone_not_verified'), [
                'token' =>null,
            ]);
        }

        if (!Auth::attempt(['phone' => $request->phone, 'password' => $request->password])) {
            return $this->errorResponse(401, __('auth.invalid_credentials'));
        }
        $user = User::where('phone', $request->phone)->first();
        
        if ($user->phone_verified_at == null) {
            return $this->errorResponse(401, __('auth.phone_not_verified'));
        }

        $token = JWTAuth::fromUser($user);

        return $this->successResponse(200, __('auth.login_success'), [
            'token' => $token,
            'user' => new UserResource($user),
        ]);
    }

    // Logout user
    public function UserLogout()
    {
        Auth::logout();
        return $this->successResponse(200, __('auth.logout_success'));
    }

    // Verify OTP
    public function UserVerifyOtp(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'phone' => 'required|string',
            'phonecode' => 'required|string', 
            'otp' => 'required|string',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse(422, __('validation.errors'), $validator->errors());
        }

        $user = User::where('phone', $request->phone)
                    ->where('phonecode', $request->phonecode)
                    ->first();

        if (!$user) {
            return $this->errorResponse(404, __('auth.user_not_found'));
        }

        $otpService = new Otp();
        $phone = $user->phone;
        $otp = $request->otp;
        $response = $otpService->validate($phone, $otp);

        if ($response->status) {
            $user->phone_verified_at = Carbon::now();
            $user->save();
            Artisan::call('otp:clean');

            $token = JWTAuth::fromUser($user);

            return $this->successResponse(200, __('auth.otp_verified'), [
                'token' => $token,
                'user' => new UserResource($user),
            ]);
        }

        return $this->errorResponse(400, __('auth.invalid_otp'));
    }

    // Resend OTP
    public function UserResendOtp(Request $request)
    {
        // Validate user input
        $validator = Validator::make($request->all(), [
            'phone' => 'required|string',
            'phonecode' => 'required|string', // Add phonecode validation
        ]);
    
        if ($validator->fails()) {
            return $this->errorResponse(422, __('validation.errors'), $validator->errors());
        }
    
        // Find the user by phone and phonecode
        $user = User::where('phone', $request->phone)
                    ->where('phonecode', $request->phonecode)
                    ->first();
    
        // Check if the user exists
        if (!$user) {
            return $this->errorResponse(404, __('auth.user_not_found'));
        }
    
        // Generate and send OTP
        $otpService = new Otp();
        $phone = $user->phone;
        $otpService->generate($phone, 'numeric', 4, 10);
    
        return $this->successResponse(200, __('auth.otp_sent'), ['phone' => $phone]);
    }

    // Forgot password
    public function UserForgotPassword(Request $request)
    {
        // Validate user input
        $validator = Validator::make($request->all(), [
            'phone' => 'required|string',
            'phonecode' => 'required|string', // Add phonecode validation
        ]);

        if ($validator->fails()) {
            return $this->errorResponse(422, __('validation.errors'), $validator->errors());
        }

        // Find the user by phone and phonecode
        $user = User::where('phone', $request->phone)
                    ->where('phonecode', $request->phonecode)
                    ->first();

        // Check if the user exists
        if (!$user) {
            return $this->errorResponse(404, __('auth.user_not_found'));
        }

        // Generate and send OTP
        $otpService = new Otp();
        $phone = $user->phone;
        $otpService->generate($phone, 'numeric', 4, 10);

        return $this->successResponse(200, __('auth.otp_sent'), ['phone' => $phone]);
    }

    // Reset password
    public function UserResetPassword(Request $request)
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

        // Find the user by phone and phonecode
        $user = User::where('phone', $request->phone)
                    ->where('phonecode', $request->phonecode)
                    ->first();

        // Check if the user exists
        if (!$user) {
            return $this->errorResponse(404, __('auth.user_not_found'));
        }

        // Update the user's password
        $user->password = Hash::make($request->password);
        $user->save();

        return $this->successResponse(200, __('auth.password_reset_success'));
    }
    // Change password
    public function UserChangePassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'old_password' => 'required|string',
            'new_password' => 'required|string|min:6|confirmed',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse(422, __('validation.errors'), $validator->errors());
        }

        $user = Auth::user();

        if (!$user) {
            return $this->errorResponse(404, __('auth.user_not_found'));
        }

        if (!Hash::check($request->old_password, $user->password)) {
            return $this->errorResponse(400, __('auth.invalid_old_password'));
        }

        if (Hash::check($request->new_password, $user->password)) {
            return $this->errorResponse(400, __('auth.new_password_must_be_different'));
        }

        $user->password = Hash::make($request->new_password);
        $user->save();

        return $this->successResponse(200, __('auth.password_changed_success'));
    }
    //show user profile
    public function UserProfile()
    {
        $user = Auth::user();
        return $this->successResponse(200, __('auth.user_profile'), new UserResource($user));
    }
    // delete account
    public function UserDeleteAccount()
    {
        $user = Auth::user();
        $user->delete();
        return $this->successResponse(200, __('auth.account_deleted_success'));
    }
}
