<?php

namespace App\Http\Controllers\Api\User;

use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\UserResource;
use Illuminate\Support\Facades\Validator;
use App\Models\User;
use Ichtrojan\Otp\Otp;
use Illuminate\Support\Facades\Artisan;

class PinController extends Controller
{
    use ApiResponse;

    // Set PIN code
    public function UserSetPinCode(Request $request)
    {
        $request->validate([
            'pin_code' => 'required|string|min:6',
        ]);

        $user = auth()->user();

        if (!$user) {
            return $this->errorResponse(404, __('auth.user_not_found'));
        }
        if ($user->pincode) {
            return $this->errorResponse(409, __('auth.pin_already_set'));
        }

        $user->pincode = $request->pin_code;
        $user->save();

        return $this->successResponse(201, __('auth.pin_set_success'), new UserResource($user));
    }

    // Change PIN code by verifying old PIN code
    public function UserChangePinCode(Request $request)
    {
        try {
            $request->validate([
                'old_pin_code' => 'required|string|min:6',
                'new_pin_code' => 'required|string|min:6|confirmed',
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse(422, $e->getMessage());
        }

        $user = auth()->user();

        if (!$user) {
            return $this->errorResponse(404, __('auth.user_not_found'));
        }

        if ($user->pincode != $request->old_pin_code) {
            return $this->errorResponse(400, __('auth.incorrect_old_pin'));
        }

        if ($request->old_pin_code == $request->new_pin_code) {
            return $this->errorResponse(400, __('auth.new_pin_must_differ'));
        }

        $user->pincode = $request->new_pin_code;
        $user->save();

        return $this->successResponse(200, __('auth.pin_changed_success'), new UserResource($user));
    }
    
    // Verify PIN code
    public function UserVerifyPinCode(Request $request)
    {
        $request->validate([
            'pin_code' => 'required|string|min:6',
        ]);

        $user = auth()->guard('user')->user();
        
        if (!$user) {
            return $this->errorResponse(404, __('auth.user_not_found'));
        }

        if ($user->pincode != $request->pin_code) {
            return $this->errorResponse(403, __('auth.incorrect_pin'));
        }
        
        return $this->successResponse(200, __('auth.pin_verified_success'));
    }

    public function UserRequestPinResetOtp(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'phone' => 'required|string',
            'phonecode' => 'required|string',
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

        // Generate and send OTP
        $otpService = new Otp();
        $otpService->generate($user->phone, 'numeric', 4, 10);

        return $this->successResponse(200, __('auth.otp_sent'), ['phone' => $user->phone]);
    }


    public function UserVerifyPinResetOtp(Request $request)
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
        $response = $otpService->validate($user->phone, $request->otp);

        if (!$response->status) {
            return $this->errorResponse(400, __('auth.invalid_otp'));
        }
        Artisan::call('otp:clean');

        return $this->successResponse(200, __('auth.otp_verified'), ['phone' => $user->phone]);
    }
    public function UserResetPinCode(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'phone' => 'required|string',
            'phonecode' => 'required|string',
            'pin_code' => 'required|string|min:6|confirmed',
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
    
        $user->pincode = $request->pin_code;
        $user->save();
    
        return $this->successResponse(200, __('auth.pin_reset_success'));
    }
    
}
