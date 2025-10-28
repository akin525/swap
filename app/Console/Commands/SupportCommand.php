<?php

namespace App\Console\Commands;

use Telegram\Bot\Commands\Command;
use Telegram\Bot\Keyboard\Keyboard;

class SupportCommand extends Command
{
    protected string $name = 'support';
    protected string $description = 'Support command to resolve user bid peer payment';

    public function handle()
    {
        // Create the inline keyboard with correct button attributes
        $keyboard = Keyboard::make()->inline()
            // Pass each button as an array
            ->row([
                Keyboard::inlineButton(['text' => '🆘 FAQs', 'callback_data' => 'faqs']),
                Keyboard::inlineButton(['text' => '📤 Contact Support', 'callback_data' => 'contact_support'])
            ]);

        // Send the response with the inline keyboard
        $this->replyWithMessage([
            'text' => "❗️ Welcome to our Support Area!\n\n Kindly Choose your prefer support option below:",
            'reply_markup' => $keyboard
        ]);
    }
}
