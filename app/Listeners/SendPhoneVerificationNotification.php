<?php

namespace App\Listeners;

use App\Interfaces\IMustVerifyPhone;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Auth\Events\Registered;

class SendPhoneVerificationNotification
{

    /**
     * Handle the event.
     */
    public function handle(Registered $event): void
    {
        if ($event->user instanceof IMustVerifyPhone && ! $event->user->hasVerifiedPhone()) {
            $event->user->sendPhoneVerificationNotification();
        }
    }
}
