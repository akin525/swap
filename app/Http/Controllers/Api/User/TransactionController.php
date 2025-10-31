<?php

namespace App\Http\Controllers\Api\User;

use App\Http\Controllers\Controller;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

class TransactionController extends Controller
{

    public function getTransactions(Request $request)
    {
        $user= auth('api')->user();

        $query = WalletTransaction::where('user_id', $user->id);

        // Filter by wallet/currency if provided
        if ($request->has('wallet_id')) {
            $query->where('wallet_id', $request->wallet_id);
        }

        if ($request->has('currency')) {
            $walletIds = Wallet::where('user_id', $user->id)
                ->where('currency', strtoupper($request->currency))
                ->pluck('id');
            $query->whereIn('wallet_id', $walletIds);
        }

        // Filter by type if provided
        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        // Filter by date range if provided
        if ($request->has('start_date') && $request->has('end_date')) {
            $query->whereBetween('created_at', [$request->start_date, $request->end_date]);
        }

        // Paginate results
        $perPage = $request->per_page ?? 15;
        $transactions = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $transactions
        ]);
    }


    public function getTransaction($reference)
    {
        $user = Auth::user();
        $transaction = WalletTransaction::where('reference', $reference)
            ->where('user_id', $user->id)
            ->first();

        if (!$transaction) {
            return response()->json([
                'success' => false,
                'message' => 'Transaction not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $transaction
        ]);
    }
    public function getTransactionById($id)
    {
        $user = Auth::user();
        $transaction = WalletTransaction::where('id', $id)
            ->where('user_id', $user->id)
            ->first();

        if (!$transaction) {
            return response()->json([
                'success' => false,
                'message' => 'Transaction not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $transaction
        ]);
    }


    public function deposit(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'amount' => 'required|numeric|min:1',
            'currency' => 'required|string|max:10',
            'source' => 'required|string|max:50',
            'reference' => 'nullable|string|max:200',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $user = Auth::user();
        $currency = strtoupper($request->currency);

        // Get or create wallet
        $wallet = Wallet::firstOrCreate(
            ['user_id' => $user->id, 'currency' => $currency],
            [
                'balance' => '0',
                'cashback' => '0',
                'transfer_single_limit' => '30000',
                'transfer_cumulative_limit' => '100000',
                'status' => 1
            ]
        );

        if ($wallet->status != 1) {
            return response()->json([
                'success' => false,
                'message' => 'Wallet is inactive'
            ], 400);
        }

        DB::beginTransaction();

        try {
            $balBefore = $wallet->balance;
            $amount = $request->amount;
            $balAfter = bcadd($balBefore, $amount, 2);

            // Update wallet balance
            $wallet->balance = $balAfter;
            $wallet->save();

            // Create transaction record
            $transaction = new WalletTransaction();
            $transaction->user_id = $user->id;
            $transaction->wallet_id = $wallet->id;
            $transaction->source = $request->source;
            $transaction->currency = $currency;
            $transaction->amount = $amount;
            $transaction->bal_before = $balBefore;
            $transaction->bal_after = $balAfter;
            $transaction->type = 'credit';
            $transaction->note = 'Deposit via ' . $request->source;
            $transaction->status = 'success';
            $transaction->reference = $request->reference ?? 'DEP' . Str::random(10);
            $transaction->save();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Deposit successful',
                'data' => [
                    'transaction' => $transaction,
                    'wallet' => $wallet
                ]
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Deposit failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    public function transfer(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'amount' => 'required|numeric|min:1',
            'currency' => 'required|string|max:10',
            'recipient' => 'required|string', // Can be email, phone, or username
            'note' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $user = Auth::user();
        $currency = strtoupper($request->currency);

        // Get sender wallet
        $senderWallet = Wallet::where('user_id', $user->id)
            ->where('currency', $currency)
            ->first();

        if (!$senderWallet) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have a wallet for this currency'
            ], 404);
        }

        if ($senderWallet->status != 1) {
            return response()->json([
                'success' => false,
                'message' => 'Your wallet is inactive'
            ], 400);
        }

        // Check if sender has sufficient balance
        if (bccomp($senderWallet->balance, $request->amount, 2) < 0) {
            return response()->json([
                'success' => false,
                'message' => 'Insufficient balance'
            ], 400);
        }

        // Check transfer limits
        if (bccomp($request->amount, $senderWallet->transfer_single_limit, 2) > 0) {
            return response()->json([
                'success' => false,
                'message' => 'Amount exceeds single transfer limit'
            ], 400);
        }

        // Find recipient
        $recipient = User::where('email', $request->recipient)
            ->orWhere('phone_number', $request->recipient)
            ->orWhere('username', $request->recipient)
            ->first();

        if (!$recipient) {
            return response()->json([
                'success' => false,
                'message' => 'Recipient not found'
            ], 404);
        }

        if ($recipient->id == $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'You cannot transfer to yourself'
            ], 400);
        }

        // Get or create recipient wallet
        $recipientWallet = Wallet::firstOrCreate(
            ['user_id' => $recipient->id, 'currency' => $currency],
            [
                'balance' => '0',
                'cashback' => '0',
                'transfer_single_limit' => '30000',
                'transfer_cumulative_limit' => '100000',
                'status' => 1
            ]
        );

        if ($recipientWallet->status != 1) {
            return response()->json([
                'success' => false,
                'message' => 'Recipient wallet is inactive'
            ], 400);
        }

        DB::beginTransaction();

        try {
            $amount = $request->amount;
            $reference = 'TRF' . Str::random(10);
            $note = $request->note ?? 'Transfer to ' . ($recipient->first_name ?? $recipient->email);

            // Debit sender
            $senderBalBefore = $senderWallet->balance;
            $senderBalAfter = bcsub($senderBalBefore, $amount, 2);

            $senderWallet->balance = $senderBalAfter;
            $senderWallet->save();

            // Create sender transaction
            $senderTransaction = new WalletTransaction();
            $senderTransaction->user_id = $user->id;
            $senderTransaction->wallet_id = $senderWallet->id;
            $senderTransaction->source = 'wallet';
            $senderTransaction->currency = $currency;
            $senderTransaction->amount = $amount;
            $senderTransaction->bal_before = $senderBalBefore;
            $senderTransaction->bal_after = $senderBalAfter;
            $senderTransaction->type = 'debit';
            $senderTransaction->note = $note;
            $senderTransaction->status = 'success';
            $senderTransaction->reference = $reference;
            $senderTransaction->save();

            // Credit recipient
            $recipientBalBefore = $recipientWallet->balance;
            $recipientBalAfter = bcadd($recipientBalBefore, $amount, 2);

            $recipientWallet->balance = $recipientBalAfter;
            $recipientWallet->save();

            // Create recipient transaction
            $recipientTransaction = new WalletTransaction();
            $recipientTransaction->user_id = $recipient->id;
            $recipientTransaction->wallet_id = $recipientWallet->id;
            $recipientTransaction->source = 'wallet';
            $recipientTransaction->currency = $currency;
            $recipientTransaction->amount = $amount;
            $recipientTransaction->bal_before = $recipientBalBefore;
            $recipientTransaction->bal_after = $recipientBalAfter;
            $recipientTransaction->type = 'credit';
            $recipientTransaction->note = 'Transfer from ' . ($user->first_name ?? $user->email);
            $recipientTransaction->status = 'success';
            $recipientTransaction->reference = $reference;
            $recipientTransaction->save();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Transfer successful',
                'data' => [
                    'transaction' => $senderTransaction,
                    'wallet' => $senderWallet
                ]
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Transfer failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    public function convert(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'amount' => 'required|numeric|min:1',
            'from_currency' => 'required|string|max:10',
            'to_currency' => 'required|string|max:10|different:from_currency',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $user = Auth::user();
        $fromCurrency = strtoupper($request->from_currency);
        $toCurrency = strtoupper($request->to_currency);

        // Get source wallet
        $sourceWallet = Wallet::where('user_id', $user->id)
            ->where('currency', $fromCurrency)
            ->first();

        if (!$sourceWallet) {
            return response()->json([
                'success' => false,
                'message' => 'Source wallet not found'
            ], 404);
        }

        if ($sourceWallet->status != 1) {
            return response()->json([
                'success' => false,
                'message' => 'Source wallet is inactive'
            ], 400);
        }

        // Check if user has sufficient balance
        if (bccomp($sourceWallet->balance, $request->amount, 2) < 0) {
            return response()->json([
                'success' => false,
                'message' => 'Insufficient balance'
            ], 400);
        }

        // Get or create destination wallet
        $destWallet = Wallet::firstOrCreate(
            ['user_id' => $user->id, 'currency' => $toCurrency],
            [
                'balance' => '0',
                'cashback' => '0',
                'transfer_single_limit' => '30000',
                'transfer_cumulative_limit' => '100000',
                'status' => 1
            ]
        );

        if ($destWallet->status != 1) {
            return response()->json([
                'success' => false,
                'message' => 'Destination wallet is inactive'
            ], 400);
        }

        // In a real app, you would get the exchange rate from a service
        // For this example, we'll use a mock exchange rate service
        $exchangeRate = $this->getExchangeRate($fromCurrency, $toCurrency);
        $convertedAmount = bcmul($request->amount, $exchangeRate, 2);

        DB::beginTransaction();

        try {
            $amount = $request->amount;
            $reference = 'CNV' . Str::random(10);

            // Debit source wallet
            $sourceBalBefore = $sourceWallet->balance;
            $sourceBalAfter = bcsub($sourceBalBefore, $amount, 2);

            $sourceWallet->balance = $sourceBalAfter;
            $sourceWallet->save();

            // Create source transaction
            $sourceTransaction = new WalletTransaction();
            $sourceTransaction->user_id = $user->id;
            $sourceTransaction->wallet_id = $sourceWallet->id;
            $sourceTransaction->source = 'conversion';
            $sourceTransaction->currency = $fromCurrency;
            $sourceTransaction->amount = $amount;
            $sourceTransaction->bal_before = $sourceBalBefore;
            $sourceTransaction->bal_after = $sourceBalAfter;
            $sourceTransaction->type = 'debit';
            $sourceTransaction->note = "Conversion from {$fromCurrency} to {$toCurrency}";
            $sourceTransaction->status = 'success';
            $sourceTransaction->reference = $reference;
            $sourceTransaction->save();

            // Credit destination wallet
            $destBalBefore = $destWallet->balance;
            $destBalAfter = bcadd($destBalBefore, $convertedAmount, 2);

            $destWallet->balance = $destBalAfter;
            $destWallet->save();

            // Create destination transaction
            $destTransaction = new WalletTransaction();
            $destTransaction->user_id = $user->id;
            $destTransaction->wallet_id = $destWallet->id;
            $destTransaction->source = 'conversion';
            $destTransaction->currency = $toCurrency;
            $destTransaction->amount = $convertedAmount;
            $destTransaction->bal_before = $destBalBefore;
            $destTransaction->bal_after = $destBalAfter;
            $destTransaction->type = 'credit';
            $destTransaction->note = "Conversion from {$fromCurrency} to {$toCurrency}";
            $destTransaction->status = 'success';
            $destTransaction->reference = $reference;
            $destTransaction->save();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Currency conversion successful',
                'data' => [
                    'from_transaction' => $sourceTransaction,
                    'to_transaction' => $destTransaction,
                    'exchange_rate' => $exchangeRate,
                    'from_wallet' => $sourceWallet,
                    'to_wallet' => $destWallet
                ]
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Currency conversion failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    private function getExchangeRate($fromCurrency, $toCurrency)
    {
        // In a real app, you would call an exchange rate API
        // For this example, we'll use mock rates
        $rates = [
            'NGN' => [
                'GHS' => 0.17,
                'ZAR' => 0.12,
                'USD' => 0.0022
            ],
            'GHS' => [
                'NGN' => 5.88,
                'ZAR' => 0.68,
                'USD' => 0.13
            ],
            'ZAR' => [
                'NGN' => 8.60,
                'GHS' => 1.48,
                'USD' => 0.055
            ],
            'USD' => [
                'NGN' => 460.0,
                'GHS' => 7.69,
                'ZAR' => 18.18
            ]
        ];

        if (isset($rates[$fromCurrency][$toCurrency])) {
            return $rates[$fromCurrency][$toCurrency];
        }

        return 1.0; // Default fallback
    }


    public function buyGiftCard(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'amount' => 'required|numeric|min:1',
            'currency' => 'required|string|max:10',
            'gift_card_type' => 'required|string|max:50',
            'recipient_email' => 'nullable|email',
            'recipient_name' => 'nullable|string|max:100',
            'message' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $user = Auth::user();
        $currency = strtoupper($request->currency);

        // Get wallet
        $wallet = Wallet::where('user_id', $user->id)
            ->where('currency', $currency)
            ->first();

        if (!$wallet) {
            return response()->json([
                'success' => false,
                'message' => 'Wallet not found'
            ], 404);
        }

        if ($wallet->status != 1) {
            return response()->json([
                'success' => false,
                'message' => 'Wallet is inactive'
            ], 400);
        }

        // Check if user has sufficient balance
        if (bccomp($wallet->balance, $request->amount, 2) < 0) {
            return response()->json([
                'success' => false,
                'message' => 'Insufficient balance'
            ], 400);
        }

        DB::beginTransaction();

        try {
            $amount = $request->amount;
            $reference = 'GFT' . Str::random(10);

            // Debit wallet
            $balBefore = $wallet->balance;
            $balAfter = bcsub($balBefore, $amount, 2);

            $wallet->balance = $balAfter;
            $wallet->save();

            // Create transaction
            $transaction = new WalletTransaction();
            $transaction->user_id = $user->id;
            $transaction->wallet_id = $wallet->id;
            $transaction->source = 'gift_card';
            $transaction->currency = $currency;
            $transaction->amount = $amount;
            $transaction->bal_before = $balBefore;
            $transaction->bal_after = $balAfter;
            $transaction->type = 'debit';
            $transaction->note = "Purchase of {$request->gift_card_type} gift card";
            $transaction->status = 'success';
            $transaction->reference = $reference;
            $transaction->save();

            // In a real app, you would create a gift card record and send it
            // For this example, we'll just return success

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Gift card purchased successfully',
                'data' => [
                    'transaction' => $transaction,
                    'wallet' => $wallet,
                    'gift_card' => [
                        'type' => $request->gift_card_type,
                        'amount' => $amount,
                        'currency' => $currency,
                        'reference' => $reference,
                        'recipient_email' => $request->recipient_email,
                        'recipient_name' => $request->recipient_name,
                        'message' => $request->message,
                        'created_at' => now()
                    ]
                ]
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Gift card purchase failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
