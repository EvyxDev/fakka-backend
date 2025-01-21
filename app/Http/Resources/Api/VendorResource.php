<?php

namespace App\Http\Resources\API;
use Illuminate\Http\Request;

use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{

    public function toArray($request)
    {
        return [
            'id' => $this->id ?? null,
            'username' => $this->username ?? null,
            'phone' => $this->phone ?? null,
            'profile_image' => env('APP_URL'). '/public/' . $this->profile_image ?? null,
            'pincode'=> $this->pincode ?? null,
            'created_at' => $this->created_at ?? null,
            'updated_at' => $this->updated_at ?? null,
        ];
    }
}

