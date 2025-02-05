<?php

namespace App\Http\Controllers\Api\Vendor;

use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\VendorResource;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use App\Models\Vendor;
use Ichtrojan\Otp\Otp;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Validator;

class PinController extends Controller
{
    use ApiResponse;

    // Set PIN code
    public function VendorSetPinCode(Request $request)
    {
        $request->validate([
            'pin_code' => 'required|string|min:6',
        ]);
        
        $vendor = Auth::guard('vendor')->user();
        if (!$vendor) {
            return $this->errorResponse(404, __('auth.vendor_not_found'));
        }
        
        if ($vendor->pincode) {
            return $this->errorResponse(409, __('auth.pin_already_set'));
        }
        
        $vendor->pincode = $request->pin_code;
        $vendor->save();
        
        return $this->successResponse(201, __('auth.pin_set_success'), new VendorResource($vendor));
    }

    // Change PIN code
    public function VendorChangePinCode(Request $request)
    {
        try {
            $request->validate([
                'old_pin_code' => 'required|string|min:6',
                'new_pin_code' => 'required|string|min:6|confirmed',
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse(422, $e->getMessage());
        }

        $vendor = Auth::guard('vendor')->user();

        if (!$vendor) {
            return $this->errorResponse(404, __('auth.vendor_not_found'));
        }

        if ($vendor->pincode != $request->old_pin_code) {
            return $this->errorResponse(400, __('auth.incorrect_old_pin'));
        }

        if ($request->old_pin_code == $request->new_pin_code) {
            return $this->errorResponse(400, __('auth.new_pin_must_differ'));
        }

        $vendor->pincode = $request->new_pin_code;
        $vendor->save();

        return $this->successResponse(200, __('auth.pin_changed_success'), new VendorResource($vendor));
    }
    
    // Verify PIN code
    public function VendorVerifyPinCode(Request $request)
    {
        $request->validate([
            'pin_code' => 'required|string|min:6',
        ]);
        
        $vendor = Auth::guard('vendor')->user();
        if (!$vendor) {
            return $this->errorResponse(404, __('auth.vendor_not_found'));
        }
        
        if ($vendor->pincode !== $request->pin_code) {
            return $this->errorResponse(403, __('auth.incorrect_pin'));
        }
        
        return $this->successResponse(200, __('auth.pin_verified_success'));
    }


    public function VendorRequestPinResetOtp(Request $request)
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
        $otpService->generate($vendor->phone, 'numeric', 4, 10);

        return $this->successResponse(200, __('auth.otp_sent'), ['phone' => $vendor->phone]);
    }

    public function VendorVerifyPinResetOtp(Request $request)
    {
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

        $otpService = new Otp();
        $response = $otpService->validate($vendor->phone, $request->otp);

        if (!$response->status) {
            return $this->errorResponse(400, __('auth.invalid_otp'));
        }
        Artisan::call('otp:clean');

        return $this->successResponse(200, __('auth.otp_verified'), ['phone' => $vendor->phone]);
    }
    
    public function VendorResetPinCode(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'phone' => 'required|string',
            'phonecode' => 'required|string',
            'pin_code' => 'required|string|min:6|confirmed',
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
    
        $vendor->pincode = $request->pin_code;
        $vendor->save();
    
        return $this->successResponse(200, __('auth.pin_reset_success'));
    }
}
