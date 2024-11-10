<?php

namespace App\Notifications\Channels;

use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;

class SyriatelChannel
{
    /**
     * Send the given notification.
     */
    public function send(object $notifiable, Notification $notification): void
    {
        $message = $notification->toSyriatel($notifiable);
        $client = new \GuzzleHttp\Client();

        $response = $client->get(config('sms.syriatel.url'), [
            'query' => [
                'user_name'     => config('sms.syriatel.user_name'),
                'password'      => config('sms.syriatel.password'),
                'param_list'    => $message->msg,
                'template_code' => $message->templateCode,
                'sender'        => config('sms.syriatel.sender'),
                'to'            => $notifiable->phone
            ],
        ]);

        $this->report(json_decode($response->getBody()->getContents()));
    }

    public function report($message): void
    {
        Log::channel('sms')->info('*******************************************************');
        Log::channel('sms')->info('Syriatel sms:');
        Log::channel('sms')->info($message);
        Log::channel('sms')->info('*******************************************************');
    }
}
