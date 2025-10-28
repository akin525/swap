<?php

// use App\Http\Controllers\Api\Webhook\TelegramWebhookController;
use Illuminate\Support\Facades\Route;
use App\Console\Commands\StartCommand;
use App\Console\Commands\ChatIdCommand;
use App\Console\Commands\HelpCommand;
use App\Console\Commands\SupportCommand;
use Telegram\Bot\Api;
use Telegram\Bot\Objects\Update;
use Telegram\Bot\Laravel\Facades\Telegram;
use Illuminate\Support\Facades\Log;
use Telegram\Bot\Keyboard\Keyboard;
use Illuminate\Http\Request;
// use Telegram;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::get('/', function () {
    return view('welcome');
});
Route::get('login', function () {
    return view('welcome');
});

Route::post('/telegram/webhook', function () {
    $telegram = new Api(env('TELEGRAM_BOT_API_KEY'));  // Initialize the Telegram API

    $update = $telegram->getWebhookUpdate();  // Get the webhook update

    try {
        // Handle callback buttons
        if ($update->isType('callback_query')) {
            $data = $update->getCallbackQuery()->getData();
            $chatId = $update->getCallbackQuery()->getMessage()->getChat()->getId();

            if ($data === 'chatid') {
                // Manually trigger command
                $command = app(\App\Console\Commands\ChatIdCommand::class);
                if ($command) {
                    $command->make($telegram, $update, []);  // Pass arguments if necessary
                    // $command->handle();
                } else {
                    Log::error('Command not found: ChatIdCommand');
                }
                return 'ok';
            }

            if ($data === 'help') {
                // Manually trigger command
                $command = app(\App\Console\Commands\HelpCommand::class);
                if ($command) {
                    $command->make($telegram, $update, []);  // Pass arguments if necessary
                    // $command->handle();
                } else {
                    Log::error('Command not found: HelpCommand');
                }
                return 'ok';
            }

            if ($data === 'support') {
                // Manually trigger command
                $command = app(\App\Console\Commands\SupportCommand::class);
                if ($command) {
                    $command->make($telegram, $update, []);  // Pass arguments if necessary
                    // $command->handle();
                } else {
                    Log::error('Command not found: SupportCommand');
                }
                return 'ok';
            }

            // Handle callback actions
            $response = match ($data) {
                'faqs' => '💰 Coming Soon. To get more details, Visit ' . env('MAIN_URL'),
                'contact_support' => '📤 Send us email via support@swappay.com',
                default => 'Unknown action',
            };

            Telegram::sendMessage([
                'chat_id' => $chatId,
                'text' => $response,
            ]);
        }

        // Handle command messages like /start
        if ($update->isType('message')) {
            $messageText = $update->getMessage()->getText();

            if (str_starts_with($messageText, '/start')) {
                $command = app(\App\Console\Commands\StartCommand::class);
                if ($command) {
                    Log::info('StartCommand resolved successfully.');
                    // $command->make($telegram, $update, [])->handle();
                    // Manually inject Telegram instance into the command
                    $command->make($telegram, $update, []);
                } else {
                    Log::error('Failed to resolve StartCommand.');
                }
            } elseif (str_starts_with($messageText, '/chatid')) {
                $command = app(\App\Console\Commands\ChatIdCommand::class);
                if ($command) {
                    Log::info('ChatIdCommand resolved successfully.');
                    // $command->make($telegram, $update, [])->handle(); // this will send the command twice because of ->handle() , so I need to use without it to send just once
                    $command->make($telegram, $update, []);
                } else {
                    Log::error('Failed to resolve ChatIdCommand.');
                }
                Log::info('ChatId sent successfully.');
            } elseif (str_starts_with($messageText, '/support')) {
                $command = app(\App\Console\Commands\SupportCommand::class);
                if ($command) {
                    Log::info('SupportCommand resolved successfully.');
                    // $command->make($telegram, $update, [])->handle(); // this will send the command twice because of ->handle() , so I need to use without it to send just once
                    $command->make($telegram, $update, []);
                } else {
                    Log::error('Failed to resolve SupportCommand.');
                }
            } elseif (str_starts_with($messageText, '/help')) {
                $command = app(\App\Console\Commands\HelpCommand::class);
                if ($command) {
                    Log::info('HelpCommand resolved successfully.');
                    // $command->make($telegram, $update, [])->handle(); // this will send the command twice because of ->handle() , so I need to use without it to send just once
                    $command->make($telegram, $update, []);
                } else {
                    Log::error('Failed to resolve HelpCommand.');
                }
            } else {
                Telegram::sendMessage([
                    'chat_id' => $update->getMessage()->getChat()->getId(),
                    'text' => "Sorry, I don't understand that command.",
                ]);
            }
        }

        return 'ok';
    } catch (\Throwable $e) {
        Log::error('Telegram webhook error', [
            'message' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ]);
        return response('Internal error', 500);
    }
});

Route::get('/set-telegram-webhook', function () {
    $response = Telegram::setWebhook(['url' => 'https://swappay.com/telegram/webhook']);
    return $response ? 'Webhook set!' : 'Failed to set webhook.';
});

Route::get('/delete-telegram-webhook', function () {
    $response = Telegram::deleteWebhook(['url' => 'https://swappay.com/telegram/webhook']);
    return $response ? 'Webhook set!' : 'Failed to set webhook.';
});