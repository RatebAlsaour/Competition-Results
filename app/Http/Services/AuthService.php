<?php

namespace App\Http\Services;

use App\Exceptions\ErrorMsgException;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

/**
 * Session-based authentication for the dashboard.
 */
class AuthService
{
    /**
     * @throws ErrorMsgException
     */
    public function login(string $email, string $password, bool $remember = false): User
    {
        if (!Auth::guard('web')->attempt(['email' => $email, 'password' => $password], $remember))
        {
            throw new ErrorMsgException('البريد الإلكتروني أو كلمة المرور غير صحيحة', 422);
        }

        request()->session()->regenerate();

        return Auth::guard('web')->user();
    }

    public function logout(): void
    {
        Auth::guard('web')->logout();
        request()->session()->invalidate();
        request()->session()->regenerateToken();
    }
}
