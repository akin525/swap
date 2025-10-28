<?php

namespace App\Console\Commands;

use Telegram\Bot\Commands\Command;
use Telegram\Bot\Keyboard\Keyboard;

class StartCommand extends Command
{
    protected string $name = 'start';
    protected string $description = 'Start command to welcome the user';

    public function handle()
    {
        // Create the inline keyboard with correct button attributes
        $keyboard = Keyboard::make()->inline()
            // Pass each button as an array
            ->row([
                Keyboard::inlineButton(['text' => '💳 Get Chat ID', 'callback_data' => 'chatid']),
                Keyboard::inlineButton(['text' => '🆘 Get Support', 'callback_data' => 'support']),
                Keyboard::inlineButton(['text' => 'ℹ️ Help', 'callback_data' => 'help'])
            ]);

        // Send the response with the inline keyboard
        $this->replyWithMessage([
            'text' => "👋 Welcome to ".env('TELEGRAM_BOT_NAME')."!\n\nChoose an option below:",
            'reply_markup' => $keyboard
        ]);
    }
}
