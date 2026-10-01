<?php

namespace App\Http\Services;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * Audit trail for sensitive actions (storage/logs/security-YYYY-MM-DD.log).
 * Every entry records who did it, from which IP, and with which browser.
 */
class SecurityLogService
{
    public function info(string $event, array $context = []): void
    {
        Log::channel('security')->info($event, $this->context($context));
    }

    public function warning(string $event, array $context = []): void
    {
        Log::channel('security')->warning($event, $this->context($context));
    }

    private function context(array $context): array
    {
        $request = request();
        $user = Auth::guard('web')->user();

        return array_merge([
            'user_id'    => $user?->id,
            'user_email' => $user?->email,
            'ip'         => $request?->ip(),
            'user_agent' => mb_substr((string) $request?->userAgent(), 0, 200),
        ], $context);
    }
}
