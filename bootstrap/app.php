<?php

use App\Exceptions\UnauthorizeMsgException;
use App\Http\Middleware\SetLocal;
use App\Http\Services\ApiResponseService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->append(SetLocal::class);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->render(function(NotFoundHttpException $exception, Request $request) {
            if($exception->getPrevious() instanceof ModelNotFoundException) {
                $modelPath = explode('\\', $exception->getModel());
                if(isset($modelPath[2]))
                    return ApiResponseService::notFoundResponse($modelPath[2].' invalid id');
                else
                    return ApiResponseService::notFoundResponse('invalid id');
            }
        });
        $exceptions->render(function(AuthenticationException $exception) {
            throw new UnauthorizeMsgException('Invalid token');
        });
    })->create();
