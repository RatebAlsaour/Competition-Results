<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Browser security headers for every Laravel response (portal page, dashboard, API).
 * Static files (/data, /build) are served by nginx with their own headers.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (!config('security.headers_enabled'))
        {
            return $response;
        }

        $headers = [
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options'        => 'SAMEORIGIN',
            'Referrer-Policy'        => 'strict-origin-when-cross-origin',
            'Permissions-Policy'     => 'camera=(), microphone=(), geolocation=(), payment=(), usb=()',
            'Cross-Origin-Opener-Policy' => 'same-origin',
        ];

        if ($csp = config('security.csp'))
        {
            $headers['Content-Security-Policy'] = $csp;
        }

        if ($request->isSecure() && config('security.hsts_max_age'))
        {
            $headers['Strict-Transport-Security'] = 'max-age=' . (int) config('security.hsts_max_age');
        }

        foreach ($headers as $name => $value)
        {
            if (!$response->headers->has($name))
            {
                $response->headers->set($name, $value);
            }
        }

        $response->headers->remove('X-Powered-By');

        return $response;
    }
}
