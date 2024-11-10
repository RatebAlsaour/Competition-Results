<?php

namespace App\Traits;

use App\Notifications\SendOtpNotification;
use Carbon\Carbon;

trait MustVerifyPhone
{
    /**
     * Determine if the user has verified their phone phone number.
     *
     * @return bool
     */
    public function hasVerifiedPhone()
    {
        return ! is_null($this->phone_verified_at);
    }

    /**
     * Mark the given user's phone phone number as verified.
     *
     * @return bool
     */
    public function markPhoneAsVerified()
    {
        return $this->forceFill([
            'phone_verified_at' => $this->freshTimestamp(),
        ])->save();
    }

    /**
     * Send the phone phone number verification notification.
     *
     * @return void
     */
    public function sendPhoneVerificationNotification()
    {
        $code = rand(10000, 90000);

        $this->forceFill([
            'phone_verification_code' => $code,
            'phone_verification_code_sent_at' => $this->freshTimestamp(),
            'phone_verification_code_expires_at' => Carbon::now()->addMinutes(config('auth.verification_timeout', 60)),
        ])->save();

        $this->notify(new SendOtpNotification($code));
    }
}
