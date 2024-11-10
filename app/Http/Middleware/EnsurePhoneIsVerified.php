<?php

namespace App\Http\Middleware;

use App\Exceptions\ErrorMsgException;
use App\Interfaces\IMustVerifyPhone;
use Closure;

class EnsurePhoneIsVerified
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  string|null  $redirectToRoute
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse|null
     */
    public function handle($request, Closure $next, $redirectToRoute = null)
    {
        if (! $request->user() ||
            ($request->user() instanceof IMustVerifyPhone &&
            ! $request->user()->hasVerifiedPhone())) {
            return new ErrorMsgException(trans('auth.unverified-mobile'));
        }

        return $next($request);
    }
}
