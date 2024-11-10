<?php

namespace App\Interfaces;

interface IMustVerifyPhone
{
    /**
     * Determine if the user has verified their phone phone number.
     *
     * @return bool
     */
    public function hasVerifiedPhone();

    /**
     * Mark the given user's phone phone number as verified.
     *
     * @return bool
     */
    public function markPhoneAsVerified();

    /**
     * Send the phone phone number verification notification.
     *
     * @return void
     */
    public function sendPhoneVerificationNotification();
}
