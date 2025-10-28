<?php

namespace App\Console\Commands;

use Telegram\Bot\Commands\Command;

class HelpCommand extends Command
{
    protected string $name = 'help';
    protected string $description = 'List available commands.';

    public function handle()
    {
        $text = "🤖 Available commands:\n";
        $text .= "/start - Welcome message and Get Main Menus.\n";
        $text .= "/chatid - To get your chat id.\n";
        $text .= "/support - To get Support Menus.\n";
        $text .= "/faqs - To get frequently ask questions.\n";
        $text .= "/contact_support - To get email contact for further support.\n";
        $text .= "/help - List available commands\n";

        $this->replyWithMessage(['text' => $text]);
    }
}
