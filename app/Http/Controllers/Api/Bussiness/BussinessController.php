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
        $bussiness = Business::all();
        return $this->successResponse(200, __('word.All bussiness'), $bussiness);    
    }
}
