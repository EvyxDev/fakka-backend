<?php

namespace App\Http\Resources\API;
use Illuminate\Http\Request;

use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{

    public function toArray($request)
    {
        $profileImageUrl = null;
        if ($this->profile_image) {
            if (filter_var($this->profile_image, FILTER_VALIDATE_URL)) {
                $profileImageUrl = $this->profile_image; 
            } else {
                $profileImageUrl = env('APP_URL') . '/public/' . $this->profile_image;
            }
        }
        // if the pincode is set, return true, else return false
        $isPinSet = $this->pincode ? 'exist' : null;
        
        return [
            'id' => $this->id ?? null,
            'name' => $this->name ?? null,
            'phone' => $this->phone ?? null,
            'profile_image' => $profileImageUrl,
            'balance' => $this->balance ?? null,
            'pin_code' => $isPinSet,
            'created_at' => $this->created_at ?? null,
            'updated_at' => $this->updated_at ?? null,
        ];
    }
}

