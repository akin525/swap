<?php

namespace App\Console\Commands;

use Telegram\Bot\Commands\Command;
use Telegram\Bot\Laravel\Facades\Telegram;

class ChatIdCommand extends Command
{
    protected string $name = 'chat_id';
    protected string $description = 'chat_id command to get the user telegram chat id';

    public function handle()
    {
        $update = $this->getUpdate();

        if ($update->isType('message')) {
            $chatId = $update->getMessage()->getChat()->getId();
        } elseif ($update->isType('callback_query')) {
            $chatId = $update->getCallbackQuery()->getMessage()->getChat()->getId();
        } else {
            $chatId = null;
        }

        if ($chatId) {
            Telegram::sendMessage([
                'chat_id' => $chatId,
                'text' => "Your Chat ID is: ".$chatId,
            ]);
        } else {
            \Log::warning('Unable to determine chat ID from update', ['update' => $update]);
        }
    }
}
