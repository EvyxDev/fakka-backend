<?php

namespace App\Http\Controllers\Api\Vendor;

use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\VendorResource;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

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
}
