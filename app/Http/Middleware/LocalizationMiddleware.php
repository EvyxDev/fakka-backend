<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Config;
use Symfony\Component\HttpFoundation\Response;

class LocalizationMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->header('lang', Config::get('app.locale'));
        if (!in_array($locale, Config::get('app.supported_locales'))) {
            $locale = Config::get('app.locale');
        }
        App::setLocale($locale);
        return $next($request);
    }
}
