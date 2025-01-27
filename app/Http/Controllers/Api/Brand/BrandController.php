<?php

namespace App\Http\Controllers\Api\Brand;

use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\VendorResource;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use App\Models\Brand;

class BrandController extends Controller
{
    use ApiResponse;

    public function storeBrand(Request $request)
    {
        // Validate the request
        $request->validate([
            'image' => 'required|image|mimes:jpeg,png,jpg,gif|max:2048', 
        ]);

        $imagePath = uploadImage($request->file('image'), 'brands');

        $brand = Brand::create([
            'image' => $imagePath,
        ]);

        // Return a success response
        return response()->json([
            'message' => 'Brand created successfully',
            'data' => $brand,
        ], 201);
    }

    public function indexBrand()
    {
        $brands = Brand::all()->map(function ($brand) {
            $brand->image = url($brand->image);
            return $brand;
        });

        return response()->json([
            'message' => 'Brands fetched successfully',
            'data' => $brands,
        ], 200);
    }
}
