<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CheckTokenExpiration
{
    // public function handle(Request $request, Closure $next)
    // {
    //     // if (Auth::guard('user')->check() && Auth::guard('user')->user()->token()->expires_at->isPast()) {
    //     //     return response()->json([
    //     //         'message' => 'Your session has expired. You have to log in again.',
    //     //     ], 401);
    //     // }
    //     // return $next($request);
    // }
}
