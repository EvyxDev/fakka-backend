<?php

namespace App\Http\Controllers\Api\Vendor;

use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Resources\API\VendorResource;
use Illuminate\Support\Facades\Auth;

class PinController extends Controller
{
    use ApiResponse;

    //set bin code

    public function VendorSetpinCode(Request $request)
    {
        $request->validate([
            'pin_code' => 'required|string|min:6',
        ]);
        $vendor = Auth::guard('vendor')->user();
        if(!$vendor){
            return $this->errorResponse(400, 'vendor not found');
        }
        $vendor->pincode = request('pin_code');
        $vendor->save();
        return $this->successResponse(200, 'Bin code set successfully', new VendorResource($vendor));
    }
    //change pin code by verifying old pin code

    public function VendorChangePinCode(Request $request)
    {
        $request->validate([
            'old_pin_code' => 'required|string|min:6',
            'new_pin_code' => 'required|string|min:6|confirmed',
        ]);
        $vendor = Auth::guard('vendor')->user();
        if(!$vendor){
            return $this->errorResponse(400, 'vendor not found');
        }
        if ($vendor->pincode != request('old_pin_code')) {
            return $this->errorResponse(400, 'Old pin code is incorrect');
        }
        if (request('old_pin_code') == request('new_pin_code')) {
            return $this->errorResponse(400, 'New pin code must be different from old pin code');
        }
        $vendor->pincode = request('new_pin_code');
        $vendor->save();
        return $this->successResponse(200, 'Pin code changed successfully', new VendorResource($vendor));
    }
}
