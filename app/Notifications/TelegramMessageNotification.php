<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use NotificationChannels\Telegram\TelegramMessage;
use App\Models\GeneralSettings;

class TelegramMessageNotification extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public $message;
    public $note;

    public function __construct($message,$note)
    {
        //
        $this->message=$message;
        $this->note=$note;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['telegram'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toTelegram($notifiable)
    {
        $gnl = GeneralSettings::first();
        return TelegramMessage::create()
            ->to($notifiable->telegram_id) // or use a fixed ID for testing
            ->content("🚀 Hello *".$notifiable->firstname."!* ")
            ->line($this->message)
            ->lineIf($this->note != "", $this->note)
            ->line("*".$gnl->sitename." Team*")
            ->line("Regards!");
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            //
        ];
    }
}
