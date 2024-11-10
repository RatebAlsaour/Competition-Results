<?php

namespace App\Notifications;

use App\Notifications\Messages\SyriatelMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class SendOtpNotification extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public $code,
        public $templateCode,
    ) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['syriatel'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toSyriatel(object $notifiable): SyriatelMessage
    {
        return SyriatelMessage::create()
                    ->setMsg($this->code)
                    ->setTemplateCode($this->templateCode);
    }
}
