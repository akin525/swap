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

class WalletController extends Controller
{

    public function dashboard()
    {
        $user = Auth::user();
        $wallets = Wallet::where('user_id', $user->id)->get();

        // Get time of day for greeting
        $hour = date('H');
        $greeting = "Good morning";
        if ($hour >= 12 && $hour < 17) {
            $greeting = "Good afternoon";
        } elseif ($hour >= 17) {
            $greeting = "Good evening";
        }

        // Get recent transactions
        $recentTransactions = WalletTransaction::where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'greeting' => $greeting,
                'user_name' => $user->first_name ?? explode('@', $user->email)[0],
                'wallets' => $wallets,
                'primary_wallet' => $wallets->where('currency', 'NGN')->first(),
                'recent_transactions' => $recentTransactions
            ]
        ]);
    }

    public function getWallets()
    {
        $user = Auth::user();
        $wallets = Wallet::where('user_id', $user->id)->get();

        return response()->json([
            'success' => true,
            'data' => $wallets
        ]);
    }


    public function getWallet($id)
    {
        $user = Auth::user();
        $wallet = Wallet::where('id', $id)
            ->where('user_id', $user->id)
            ->first();

        if (!$wallet) {
            return response()->json([
                'success' => false,
                'message' => 'Wallet not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $wallet
        ]);
    }


    public function getWalletByCurrency($currency)
    {
        $user = Auth::user();
        $wallet = Wallet::where('currency', strtoupper($currency))
            ->where('user_id', $user->id)
            ->first();

        if (!$wallet) {
            return response()->json([
                'success' => false,
                'message' => 'Wallet not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $wallet
        ]);
    }


    public function createWallet(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'currency' => 'required|string|max:10',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $user = Auth::user();

        // Check if wallet already exists
        $existingWallet = Wallet::where('user_id', $user->id)
            ->where('currency', strtoupper($request->currency))
            ->first();

        if ($existingWallet) {
            return response()->json([
                'success' => false,
                'message' => 'Wallet already exists for this currency'
            ], 422);
        }

        // Create new wallet
        $wallet = new Wallet();
        $wallet->user_id = $user->id;
        $wallet->currency = strtoupper($request->currency);
        $wallet->balance = '0';
        $wallet->cashback = '0';
        $wallet->transfer_single_limit = '30000';
        $wallet->transfer_cumulative_limit = '100000';
        $wallet->status = 1;
        $wallet->save();

        return response()->json([
            'success' => true,
            'message' => 'Wallet created successfully',
            'data' => $wallet
        ], 201);
    }
}
