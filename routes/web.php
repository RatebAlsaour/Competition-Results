<?php

use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;
use Illuminate\View\Middleware\ShareErrorsFromSession;

// البوابة العامة: صفحة ثابتة بلا جلسة ولا كوكيز ولا قاعدة بيانات (تتحمل الضغط العالي ويمكن تخزينها في CDN)
Route::view('/', 'portal', ['section' => 'results'])
    ->withoutMiddleware([StartSession::class, ShareErrorsFromSession::class, ValidateCsrfToken::class, EncryptCookies::class, AddQueuedCookiesToResponse::class])
    ->middleware('cache.headers:public;max_age=60;etag');

// قسم الامتحانات (التراجمة المحلفون...): صفحة مستقلة برابط غير قابل للتخمين، بلا ذكر مديرية التنمية الإدارية
Route::view('/' . config('exams.public_path'), 'portal', ['section' => 'exams'])
    ->withoutMiddleware([StartSession::class, ShareErrorsFromSession::class, ValidateCsrfToken::class, EncryptCookies::class, AddQueuedCookiesToResponse::class])
    ->middleware('cache.headers:public;max_age=60;etag');

// لوحة التحكم (تطبيق React يدير مساراته بنفسه)
Route::view('/admin/{any?}', 'admin')->where('any', '.*')->name('login');
