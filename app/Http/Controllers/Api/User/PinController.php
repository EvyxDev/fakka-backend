<?php

namespace App\Http\Controllers\Api\User;

use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Resources\API\UserResource;

class PinController extends Controller
{
    use ApiResponse;

    //set bin code

    public function setpinCode(Request $request)
    {
        $request->validate([
            'pin_code' => 'required|string|min:6',
        ]);
        $user = auth()->user();
        if(!$user){
            return $this->errorResponse(400, 'User not found');
        }
        $user->pincode = request('pin_code');
        $user->save();
        return $this->successResponse(200, 'Bin code set successfully', new UserResource($user));
    }
    //change pin code by verifying old pin code

    public function changePinCode(Request $request)
    {
        $request->validate([
            'old_pin_code' => 'required|string|min:6',
            'new_pin_code' => 'required|string|min:6|confirmed',
        ]);
        $user = auth()->user();
        if(!$user){
            return $this->errorResponse(400, 'User not found');
        }
        if ($user->pincode != request('old_pin_code')) {
            return $this->errorResponse(400, 'Old pin code is incorrect');
        }
        if (request('old_pin_code') == request('new_pin_code')) {
            return $this->errorResponse(400, 'New pin code must be different from old pin code');
        }
        $user->pincode = request('new_pin_code');
        $user->save();
        return $this->successResponse(200, 'Pin code changed successfully', new UserResource($user));
    }
}
