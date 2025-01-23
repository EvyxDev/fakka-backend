<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Config;
use Symfony\Component\HttpFoundation\Response;

class LocalizationMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $supportedLocales = ['en', 'ar']; 
        $locale = $request->header('lang');
        if ($locale && in_array($locale, $supportedLocales)) {
            app()->setLocale($locale); 
        } else {
            app()->setLocale('en'); 
        }
        return $next($request);
    }
}
