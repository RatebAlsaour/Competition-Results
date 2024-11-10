<?php

return [
    'syriatel' => [
        'url' => 'http://newshefaa1.lamsetshefaa.sy/api/sendSms',
        'job_name' => env('SYRIATEL_SMS_JOB_NAME'),
        'user_name' => env('SYRIATEL_SMS_USER_NAME'),
        'password' => env('SYRIATEL_SMS_PASSWORD'),
        'sender' => env('SYRIATEL_SMS_SENDER'),

        'templates_codes' => [
            'otp_template' => env('SYRIATEL_SMS_VER_TEMPLATE'), // Put your syriatel otp template code here
        ]
    ],
];
