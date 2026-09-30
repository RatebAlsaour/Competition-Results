<?php

namespace App\Http\Services;

use Closure;
use Illuminate\Support\Facades\Cache;

/**
 * Versioned cache for public results: flushing a competition just bumps its version.
 */
class ResultsCacheService
{
    public function remember(int|string $scope, string $key, Closure $callback): mixed
    {
        return Cache::remember(
            "results:{$scope}:v{$this->version($scope)}:" . md5($key),
            now()->addDay(),
            $callback
        );
    }

    public function flush(int|string $scope): void
    {
        Cache::forever($this->versionKey($scope), $this->version($scope) + 1);
    }

    private function version(int|string $scope): int
    {
        return (int) Cache::get($this->versionKey($scope), 0);
    }

    private function versionKey(int|string $scope): string
    {
        return "results:{$scope}:version";
    }
}
