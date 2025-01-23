<?php

namespace App\Http\Controllers\Api\Bussiness;

use App\Models\Business;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Traits\ApiResponse;

class BussinessController extends Controller
{
    use ApiResponse;
    //return all bussiness
    public function index(){ 
        $perPage = $validated['per_page'] ?? 5; 
        $bussiness = Business::paginate($perPage);
        return $this->successResponse(200, __('word.All bussiness'), $bussiness);    
    }
}
