<?php

namespace App\Providers;

use App\Http\Classes\ExcelResultsSheetReader;
use App\Interfaces\IResultsSheetReader;
use App\Notifications\Channels\SyriatelChannel;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(IResultsSheetReader::class, ExcelResultsSheetReader::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Notification::extend('syriatel', function ($app) {
            return new SyriatelChannel();
        });

    }
}
