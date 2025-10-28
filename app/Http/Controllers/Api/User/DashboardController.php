<?php

namespace App\Http\Controllers\Api\User;

use App\Models\Country;
use App\Notifications\TelegramNotification;
use App\Notifications\TelegramAccVerifiedNotification;
use App\Notifications\TelegramMessageNotification;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Bids;
use App\Models\Asks;
use App\Models\Plan;
use App\Models\Peer;
use App\Models\Investment;
use App\Models\Transaction;
use App\Models\GeneralSettings;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;
use Carbon\Carbon;

class DashboardController extends Controller
{
    //
    public function index(){
        //
        $user = User::find(auth()->user()->id);
        if(!$user){
            return response()->json(['success' => false, 'message' => 'User not found or not logged in']);
        }

        $siteBot = env('TELEGRAM_BOT_NAME');

        return response()->json(['success'=>true, 'data'=>['user' => $user->makeHidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'email_verified_at', 'email_code', 'telegram_otp', 'flac'])], 'siteBot' => $siteBot], 200);
    }

    public function settings(){
        //
        $data = GeneralSettings::select('sitename','registration','login','maintain','telegram')->first();

        return response()->json(['success'=>true, 'data'=>$data], 200);
    }

    public function verifyTelegram(Request $request){
        //
        $validator = Validator::make($request->all(), [
            'telegram_id' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => 'Incomplete request', 'error' => $validator->errors()]);
        }

        $user = User::find(auth()->user()->id);
        if(!$user){
            return response()->json(['success' => false, 'message' => 'User not found or not logged in']);
        }

        // if already verified
        if ($user->telegram_id != NULL && $user->telegram_verified == 1) {
            return response()->json(['success' => false, 'message' => 'Telegram Id already verified']);
        }

        $otp = rand(100000,999999);

        $user->telegram_id = $request->telegram_id;
        $user->telegram_otp = $otp;

        // send verification code
        try{
            $user->notify(new TelegramNotification($otp));
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Unable to send OTP. Kindly check if your Chat ID is correct & you have Sent /Start to '.env('TELEGRAM_BOT_NAME').' bot']);
        }

        $user->save();

        return response()->json(['success' => true, 'message' => 'Kindly check your telegram chat for verification code']);
    }

    public function verifyTelegramCode(Request $request){
        //
        $validator = Validator::make($request->all(), [
            'otp' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => 'Incomplete request', 'error' => $validator->errors()]);
        }

        $user = User::find(auth()->user()->id);
        if(!$user){
            return response()->json(['success' => false, 'message' => 'User not found or not logged in']);
        }

        // if already verified
        if ($user->telegram_id != NULL && $user->telegram_verified == 1) {
            return response()->json(['success' => false, 'message' => 'Telegram Id already verified']);
        }

        if ($user->telegram_otp != $request->otp) {
            return response()->json(['success' => false, 'message' => 'Telegram Account Verification Failed']);
        }

        $user->telegram_verified = 1;
        $user->save();

        $user->notify(new TelegramAccVerifiedNotification());

        return response()->json(['success' => true, 'message' => 'Your Telegram ID is Verified Successfully.']);
    }

    public function referrals(){
        //
        $data = User::select('firstname','lastname','email')->where(['referral'=>auth()->user()->ref_code])->latest()->paginate(20);

        return response()->json(['success'=>true, 'data'=>$data], 200);
    }

    public function country()
    {
        $data=Country::all();
        return response()->json([
            'success' => true,
            'data' => $data
        ]);

    }

}
