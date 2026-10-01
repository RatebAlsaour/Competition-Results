<?php

use App\Exceptions\UnauthorizeMsgException;
use App\Http\Middleware\EnsurePhoneIsVerified;
use App\Http\Middleware\ForceJsonResponse;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\SetLocal;
use App\Http\Services\ApiResponseService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // خلف Nginx (Docker + المضيف): لمعرفة HTTPS وعنوان IP الحقيقي للزائر
        if ($proxies = env('TRUSTED_PROXIES')) {
            $middleware->trustProxies(at: $proxies === '*' ? '*' : explode(',', $proxies));
        }
        $middleware->append(SetLocal::class);
        $middleware->append(SecurityHeaders::class);
        $middleware->api(
            prepend: [
                ForceJsonResponse::class
            ]
        );
        $middleware->alias([
            'phone-verified'   => EnsurePhoneIsVerified::class
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->render(function(NotFoundHttpException $exception) {
            if($exception->getPrevious() instanceof ModelNotFoundException) {
                $modelPath = explode('\\', $exception->getPrevious()->getModel());
                if(isset($modelPath[2]))
                    return ApiResponseService::notFoundResponse($modelPath[2].' invalid id');
                else
                    return ApiResponseService::notFoundResponse('invalid id');
            }
            else
                return ApiResponseService::notFoundResponse('Page Not Found. please insert right Url');
        });
        $exceptions->render(function(AuthenticationException $exception) {
            throw new UnauthorizeMsgException(trans('auth.invalid_token'));
        });
        $exceptions->render(function(AccessDeniedHttpException $exception) {
            throw new UnauthorizeMsgException();
        });
    })->create();
