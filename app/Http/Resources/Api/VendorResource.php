<?php

namespace App\Http\Resources\API;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VendorResource extends JsonResource
{

    public function toArray($request)
    {
        $profileImageUrl = null;
        if ($this->profile_image) {
            if (filter_var($this->profile_image, FILTER_VALIDATE_URL)) {
                $profileImageUrl = $this->profile_image; // Use the existing URL
            } else {
                $profileImageUrl = env('APP_URL') . '/public/' . $this->profile_image;
            }
        }
        $isPinSet = $this->pincode ? 'exist' : null;

        return [
            'id' => $this->id ?? null,
            'name' => $this->name ?? null,
            'phone' => $this->phone ?? null,
            'phonecode' => $this->phonecode ?? null,
            'profile_image' => $profileImageUrl,
            'business_id' => $this->business_id ?? null,
            'balance' => $this->balance ?? null,
            'pin_code' => $isPinSet,
            'created_at' => $this->created_at ?? null,
            'updated_at' => $this->updated_at ?? null,
        ];
    }
}

