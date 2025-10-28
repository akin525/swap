<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use NotificationChannels\Telegram\TelegramMessage;
use App\Models\GeneralSettings;

class TelegramAccVerifiedNotification extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct()
    {
        //
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
            ->line("Finally, your *".$gnl->sitename."* account is now functional and you can now receive Telegram Notifications.")
            ->line("Click Below to join our Channel for all updates")
            ->line($gnl->telegram)
            ->line("Good Luck!");
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
