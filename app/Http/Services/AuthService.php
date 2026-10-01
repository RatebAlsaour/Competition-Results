<?php

namespace App\Http\Services;

use App\Exceptions\ErrorMsgException;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

/**
 * Session-based authentication for the dashboard, with brute-force lockout
 * per account (email) and per IP address.
 */
class AuthService
{
    public function __construct(
        protected SecurityLogService $securityLog
    ) {}

    /**
     * @throws ErrorMsgException
     */
    public function login(string $email, string $password, bool $remember = false): User
    {
        $accountKey = 'login:account:' . Str::lower($email);
        $ipKey = 'login:ip:' . request()->ip();
        $decay = (int) config('security.login.lockout_minutes') * 60;

        if (RateLimiter::tooManyAttempts($accountKey, (int) config('security.login.max_attempts_per_account'))
            || RateLimiter::tooManyAttempts($ipKey, (int) config('security.login.max_attempts_per_ip')))
        {
            $seconds = max(RateLimiter::availableIn($accountKey), RateLimiter::availableIn($ipKey));
            $this->securityLog->warning('login.locked', ['email' => $email]);
            throw new ErrorMsgException('محاولات دخول فاشلة كثيرة. حاول مجدداً بعد ' . max(1, (int) ceil($seconds / 60)) . ' دقيقة', 429);
        }

        if (!Auth::guard('web')->attempt(['email' => $email, 'password' => $password], $remember))
        {
            RateLimiter::hit($accountKey, $decay);
            RateLimiter::hit($ipKey, $decay);
            $this->securityLog->warning('login.failed', ['email' => $email]);
            throw new ErrorMsgException('البريد الإلكتروني أو كلمة المرور غير صحيحة', 422);
        }

        RateLimiter::clear($accountKey);
        request()->session()->regenerate();
        $this->securityLog->info('login.success');

        return Auth::guard('web')->user();
    }

    public function logout(): void
    {
        $this->securityLog->info('logout');
        Auth::guard('web')->logout();
        request()->session()->invalidate();
        request()->session()->regenerateToken();
    }
}
