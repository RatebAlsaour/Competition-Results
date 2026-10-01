<?php

return [

    'headers_enabled' => env('SECURITY_HEADERS_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Content Security Policy
    |--------------------------------------------------------------------------
    | يمنع تنفيذ أي JavaScript لم يأتِ من الموقع نفسه (حماية من XSS).
    | الخطوط من Google Fonts، والأنماط المضمّنة مطلوبة لتصميم البوابة.
    | في وضع التطوير (npm run dev) يُعطَّل لأن Vite يحقن سكربتات من منفذ آخر.
    */
    'csp' => env('SECURITY_CSP', env('APP_ENV') === 'local' ? null : implode('; ', [
        "default-src 'self'",
        "script-src 'self'",
        "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com",
        "font-src 'self' data: https://fonts.gstatic.com",
        "img-src 'self' data:",
        "connect-src 'self'",
        "object-src 'none'",
        "base-uri 'self'",
        "form-action 'self'",
        "frame-ancestors 'self'",
    ])),

    'hsts_max_age' => env('SECURITY_HSTS_MAX_AGE', 31536000),

    /*
    |--------------------------------------------------------------------------
    | Dashboard login protection
    |--------------------------------------------------------------------------
    | قفل الحساب بعد عدد محاولات فاشلة (لكل بريد + لكل عنوان IP)، بغض النظر عن عدد الأجهزة.
    */
    'login' => [
        'max_attempts_per_account' => env('LOGIN_MAX_ATTEMPTS', 5),
        'max_attempts_per_ip'      => env('LOGIN_IP_MAX_ATTEMPTS', 20),
        'lockout_minutes'          => env('LOGIN_LOCKOUT_MINUTES', 15),
    ],
];
