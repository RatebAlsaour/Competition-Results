<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetLocal
{
    private const SUPPORTED = ['ar', 'en'];

    /**
     * Handle an incoming request.
     *
     * Accept-Language is user input (ex: "ar-SY,ar;q=0.9" or anything an attacker sends),
     * so only a supported language code is passed to the translator.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $locale = strtolower(substr((string) $request->header('accept-language'), 0, 2));
        app()->setLocale(in_array($locale, self::SUPPORTED, true) ? $locale : config('app.locale'));
        return $next($request);
    }
}
