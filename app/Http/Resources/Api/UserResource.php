<?php

namespace App\Http\Resources\API;
use Illuminate\Http\Request;

use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{

    public function toArray($request)
    {
        // Build the profile image URL
        $profileImageUrl = null;
        if ($this->profile_image) {
            // Check if the profile_image already contains a full URL
            if (filter_var($this->profile_image, FILTER_VALIDATE_URL)) {
                $profileImageUrl = $this->profile_image; // Use the existing URL
            } else {
                // Prepend the base URL to the profile image path
                $profileImageUrl = env('APP_URL') . '/public/' . $this->profile_image;
            }
        }
    
        return [
            'id' => $this->id ?? null,
            'name' => $this->name ?? null,
            'phone' => $this->phone ?? null,
            'profile_image' => $profileImageUrl,
            'balance' => $this->balance ?? null,
            'created_at' => $this->created_at ?? null,
            'updated_at' => $this->updated_at ?? null,
        ];
    }
}

