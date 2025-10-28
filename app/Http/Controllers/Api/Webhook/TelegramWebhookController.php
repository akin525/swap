<?php

namespace App\Http\Controllers\Api\Webhook;

use App\Http\Controllers\Controller;
use Telegram\Bot\Laravel\Facades\Telegram;
use Illuminate\Http\Request;


class TelegramWebhookController extends Controller
{
    /**
     * Show the application dashboard.
     *
     * @return \Illuminate\Http\Response
     */

    //Login
    public function webhook(Request $request)
    {
        $update = Telegram::getWebhookUpdate();

        // Example response logic
        // $chatId = $update->getMessage()->getChat()->getId();
        // $text = $update->getMessage()->getText();

        // Telegram::sendMessage([
        //     'chat_id' => $chatId,
        //     'text' => "You said: $text",
        // ]);

        if ($update->isType('message')) {
            $message = $update->getMessage();
            $text = $message->getText();
            $chatId = $message->getChat()->getId();
    
            switch (true) {
                case str_starts_with($text, '/start'):
                    $response = "👋 Welcome! Your Chat ID is: ".$chatId;
                    break;
    
                case str_starts_with($text, '/help'):
                    $response = "🆘 Here's how to use the bot...";
                    break;
    
                case str_starts_with($text, '/donate'):
                    $response = "💳 You can donate here: https://example.com";
                    break;
    
                default:
                    $response = "❓ Unknown command. Type /help.";
            }
    
            Telegram::sendMessage([
                'chat_id' => $chatId,
                'text' => $response,
            ]);
        }

        return 'ok';
    }
}
