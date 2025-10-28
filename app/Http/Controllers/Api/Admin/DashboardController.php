<?php

namespace App\Http\Controllers\Api\Admin;

use App\Notifications\TelegramNotification;
use App\Notifications\TelegramAccVerifiedNotification;
use App\Notifications\TelegramMessageNotification;
use App\Notifications\TelegramBotCastNotification;
use App\Http\Controllers\Controller;
use App\Models\Admin;
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
        $data['admin'] = Admin::find(auth()->guard('admin')->user()->id);
        if(!$data['admin']){
            return response()->json(['success' => false, 'message' => 'Admin not found or not logged in']);
        }
        // 
        $data['users'] = User::count();
        $data['bids'] = Bids::count();
        $data['asks'] = Asks::count();
        $data['active_users'] = User::where(['status'=>'active'])->count();
        $data['success_bids'] = Bids::where(['status'=>'success'])->count();
        $data['success_asks'] = Asks::where(['status'=>'success'])->count();
        $data['sum_bids'] = Bids::sum('amount');
        $data['sum_asks'] = Asks::sum('amount');

        return response()->json(['success'=>true, 'data'=>$data], 200);
    }

    public function users(){
        // 
        $data = User::latest()->paginate(20);

        return response()->json(['success'=>true, 'data'=>$data], 200);
    }

    public function userDetails($id){
        // 
        $data['profile'] = User::find($id);
        $data['bids'] = Bids::where(['user_id'=>$id])->count();
        $data['asks'] = Asks::where(['user_id'=>$id])->count();
        $data['success_bids'] = Bids::where(['user_id'=>$id, 'status'=>'success'])->count();
        $data['success_asks'] = Asks::where(['user_id'=>$id, 'status'=>'success'])->count();
        $data['sum_bids'] = Bids::where(['user_id'=>$id, 'status'=>'success'])->sum('amount');
        $data['sum_asks'] = Asks::where(['user_id'=>$id, 'status'=>'success'])->sum('amount');

        return response()->json(['success'=>true, 'data'=>$data], 200);
    }

    public function updateUserStatus($id, $status){
        //
        $statuses = ['inactive','active','blocked'];
        if(!in_array($status,$statuses)){
            return response()->json(['success'=>false, 'message'=>"Invalid Status Specified"]);
        }
        // 
        $data = User::find($id);
        if(!$data){
            return response()->json(['success'=>false, 'message'=>"User not found"]);
        }
        $data->status = $status;
        $data->save();

        return response()->json(['success'=>true, 'message'=>"User Status Updated Successfully"], 200);
    }

    public function settings(){
        // 
        $data = GeneralSettings::select('sitename','currency','currency_sym','registration','login','maintain','telegram_channel','telegram_group','opening_time','closing_time')->first();

        return response()->json(['success'=>true, 'data'=>$data], 200);
    }

    public function bids($status){
        // 
        if($status == 'all'){
            $data = Bids::latest()->paginate(20);
        }else{
            $data = Bids::where(['status'=>$status])->latest()->paginate(20);
        }

        return response()->json(['success'=>true, 'data'=>$data], 200);
    }

    public function bidDetails($id){
        // 
        $data = Bids::where(['id'=>$id])->with('peer')->first();

        return response()->json(['success'=>true, 'data'=>$data], 200);
    }

    public function cancelBid($id){
        // 
        $data = Bids::where(['id'=>$id])->first();
        if(!$data){
            return response()->json(['success'=>false, 'message'=>"Bid not found"]);
        }
        if($data->status != 'pending'){
            return response()->json(['success'=>false, 'message'=>"Only Pending Bid can be cancelled"]);
        }

        $data->status = 'failed';
        $data->save();

        return response()->json(['success'=>true, 'message'=>"Canlled Successfully"], 200);
    }

    public function asks($status){
        // 
        if($status == 'all'){
            $data = Asks::latest()->paginate(20);
        }else{
            $data = Asks::where(['status'=>$status])->latest()->paginate(20);
        }

        return response()->json(['success'=>true, 'data'=>$data], 200);
    }

    public function askDetails($id){
        // 
        $data = Asks::where(['id'=>$id])->with('peer')->first();

        return response()->json(['success'=>true, 'data'=>$data], 200);
    }

    public function cancelAsk($id){
        // 
        $data = Asks::where(['id'=>$id])->with('peer')->first();
        if(!$data){
            return response()->json(['success'=>false, 'message'=>"Ask not found"]);
        }
        if($data->status != 'pending'){
            return response()->json(['success'=>false, 'message'=>"Only Pending Ask can be cancelled"]);
        }
        if($data->amount != $data->amount_to_pair){
            return response()->json(['success'=>false, 'message'=>"You can not cancel a Partly Paired Ask"]);
        }

        // find transaction with same trx to know the balance source
        $tr = Transaction::where(['user_id'=>$data->user_id, 'trx'=>$data->trx])->first();
        if(!$tr){
            return response()->json(['success'=>false, 'message'=>"Transaction record not found"]);
        }
        if($tr->status == 'failed'){
            return response()->json(['success'=>false, 'message'=>"Transaction Already reversed"]);
        }
        // reverse money
        $user = User::find($data->user_id);
        if($tr->wallet == 'balance'){
            $curentBal = $user->balance;
            $newBal = $user->balance + $data->amount;
            $user->balance += $data->amount;
        }elseif($tr->wallet == 'earning'){
            $curentBal = $user->earning;
            $newBal = $user->earning + $data->amount;
            $user->earning += $data->amount;
        }
        $user->save();
        // record reverser
        Transaction::create([
            'user_id' => $data->user_id,
            'wallet' => $tr->wallet,
            'trx_type' => "withdrawal_reversal",
            'ask_id' => $data->id,
            'amount' => $data->amount,
            'bal_before' => $curentBal,
            'bal_after' => $newBal,
            'type' => "credit",
            'trx' => "R".$data->trx,
            'status' => 'success',
        ]);
        $tr->status = 'failed';
        $tr->reversed = 'Yes';
        $tr->save();

        $data->status = 'failed';
        $data->save();

        return response()->json(['success'=>true, 'message'=>"Cancelled Successfully"], 200);
    }

    public function plans(){
        // 
        $data = Plan::select('id','name','minimum','maximum','interest','interest_type')->get();

        return response()->json(['success'=>true, 'data'=>$data], 200);
    }

    public function peers($status){
        // 
        if($status == 'all'){
            $data = Peer::latest()->with(['ask','bid','askUser','bidUser'])->paginate(20);
        }else{
            $data = Peer::where(['status'=>$status])->latest()->with(['ask','bid','askUser','bidUser'])->paginate(20);
        }

        return response()->json(['success'=>true, 'data'=>$data], 200);
    }

    public function unpairPeering($peer_id){
        // 
        $peer = Peer::find($peer_id);
        if(!$peer){
            return response()->json(['success'=>false, 'message'=>"Pairing not found."]);
        }

        if(!in_array($peer->status, ['awaiting_payment', 'payment_declined'])){
            return response()->json(['success'=>false, 'message'=>"Only unpaid or declined pairings can be unpaired."]);
        }

        $ask = Asks::find($peer->ask_id);
        $bid = Bids::find($peer->bid_id);

        if (!$ask || !$bid) {
            return response()->json([
                'success' => false,
                'message' => !$ask ? "Ask request not found." : "Bid request not found."
            ], 404);
        }

        // Unpair
        $peer->status = 'unpaired';
        $peer->save();

        // Refund Ask
        $ask->amount_to_pair += $peer->pair_amount;
        $ask->paired_amount = max(0, $ask->paired_amount - $peer->pair_amount);
        $ask->status = ($ask->amount_to_pair >= $ask->amount) ? 'pending' : 'partly_paired';
        $ask->save();

        // Refund Bid
        $bid->amount_to_pair += $peer->pair_amount;
        $bid->paired_amount = max(0, $bid->paired_amount - $peer->pair_amount);
        $bid->status = ($bid->amount_to_pair >= $bid->amount) ? 'pending' : 'partly_paired';
        $bid->save();

        return response()->json(['success'=>true, 'message'=>"Unpaired Successfully"], 200);
    }

    public function investments($status){
        // 
        if($status == 'all'){
            $data = Investment::latest()->paginate(20);
        }else{
            $data = Investment::where(['status'=>$status])->latest()->paginate(20);
        }

        return response()->json(['success'=>true, 'data'=>$data], 200);
    }

    public function investmentDetails($id){
        // 
        $data = Investment::where(['id'=>$id])->with('bid')->first();
        if(!$data){
            return response()->json(['success'=>false, 'message'=>"Energy Node not found"]);
        }

        return response()->json(['success'=>true, 'data'=>$data], 200);
    }

    public function adminConfirmPayment(Request $request, $peer_id){
        //
        $validator = Validator::make($request->all(), [
            'status' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => 'Incomplete request', 'error' => $validator->errors()]);
        }

        $state = ['approved','declined'];
        if(!in_array($request->status,$state)){
            return response()->json(['success'=>false, 'message'=>"Status can only be approved or declined"]);
        }

        if($request->status == 'declined'){
            $validator = Validator::make($request->all(), [
                'reason' => 'required|string',
            ]);
    
            if ($validator->fails()) {
                return response()->json(['success' => false, 'message' => 'Incomplete request', 'error' => $validator->errors()]);
            }
        }

        $peer = Peer::find($peer_id);
        if(!$peer){
            return response()->json(['success'=>false, 'message'=>"Peer not found"]);
        }

        if($peer->status == 'payment_confirmed'){
            return response()->json(['success'=>false, 'message'=>"Payment already confirmed"]);
        }

        $ask = Asks::find($peer->ask_id);
        if(!$ask){
            return response()->json(['success'=>false, 'message'=>"Ask not found on pairing"]);
        }

        $bid = Bids::find($peer->bid_id);
        if(!$bid){
            return response()->json(['success'=>false, 'message'=>"Bid not found on this pairing"]);
        }

        // confirm payment
        $peer->confirmed_at = Carbon::now();
        $peer->payment_status = $request->status;
        $peer->reason = $request->reason;
        if($request->status == 'approved'){
            $peer->status = 'payment_confirmed';
            $mg = "Confirmed";
        }else{
            $peer->status = 'payment_declined';
            $mg = "Declined";
        }
        $peer->save();

        // telegram notfication to bid user
        $bidUser = User::find($peer->bid_user_id);
        $message = "Your Bid pairing with Reference ".$peer->reference." as been ".$request->status;
        if($request->status == 'declined'){
            $note = "Reason: ".$request->reason;
        }else{
            $note = "";
        }
        if($bidUser->telegram_id != NULL){
            $bidUser->notify(new TelegramMessageNotification($message,$note));
        }
        // done sending telegram notifincation to bider

        // unpair and reverse ask fund back to asker, also cancle transaction record for it.

        // sum all asks peers that are successful & check if total equals total expected
        $sumPaidPairedAsk = Peer::where(['ask_id'=>$ask->id, 'status'=>'payment_confirmed'])->sum('pair_amount');
        if($sumPaidPairedAsk == $ask->amount){
            $ask->status = 'success';
            $ask->save();

            $t = Transaction::where(['ask_id'=>$ask->id])->first();
            if($t){
                $t->status = 'success';
                $t->save();
            }
        }

        // sum all bids peers that are successful & check if total equals total expected
        $sumPaidPairedBid = Peer::where(['bid_id'=>$bid->id, 'status'=>'payment_confirmed'])->sum('pair_amount');
        if($sumPaidPairedBid == $bid->amount){
            $bid->status = 'success';
            $bid->save();

            // get plan details
            $plan = Plan::find($bid->plan_id);
            if($plan->time == 'hours'){
                $returnDate = Carbon::now()->addHours($plan->duration);
            }elseif($plan->time == 'days'){
                $returnDate = Carbon::now()->addDays($plan->duration);
            }

            if($plan->interest_type == 'percent'){
                $expectedProfit = ($bid->amount * $plan->interest) / 100;
            }else{
                $expectedProfit = $plan->interest;
            }

            $expectedReturn = $bid->amount + $expectedProfit;

            $trx = Carbon::now()->format('YmdHi') . rand();

            // trigger investment job
            $in = Investment::create([
                'user_id' => $peer->bid_user_id,
                'bid_id' => $bid->id,
                'plan_id' => $bid->plan_id,
                'amount' => $bid->amount,
                'expected_profit' => $expectedProfit,
                'expected_return' => $expectedReturn,
                'return_date' => $returnDate,
                'reference' => $trx,
                'status' => 'running',
            ]);

            $bid->invest_id = $in->id;
            $bid->save();

            // telegram notfication to bid user
            $message = "Your Bid payment is completed and your energy node is now activated.";
            $note = "Your expected return date and time is ".$in;
            if($bidUser->telegram_id != NULL){
                $bidUser->notify(new TelegramMessageNotification($message,$note));
            }
            // done sending telegram notifincation to bider

            // credit referral
            $referral = User::where(['ref_code'=>$bidUser->referral])->first();
            if($referral){
                $earning = ($bid->amount * 10) / 100;
                $trx = Carbon::now()->format('YmdHi') . rand();

                Transaction::create([
                    'user_id' => $referral->id,
                    'wallet' => "earning",
                    'trx_type' => "referral_earning",
                    'bid_id' => $bid->id,
                    'amount' => $earning,
                    'bal_before' => $referral->earning,
                    'bal_after' => $referral->earning + $earning,
                    'type' => "credit",
                    'trx' => $trx,
                    'status' => 'success',
                ]);

                $referral->earning += $earning;
                $referral->save();
                // telegram notfication to bid user
                $message = "You just earned ".$earning." USDT on ".$bidUser->username."'s Bid.";
                $note = "Refer mode users and earn more on there Bids";
                if($referral->telegram_id != NULL){
                    $referral->notify(new TelegramMessageNotification($message,$note));
                }
                // done sending telegram notifincation to bider
            }
        }

        return response()->json(['success'=>true, 'message'=>"Payment ".$mg], 200);
    }

    public function botCast(Request $request){
        $validator = Validator::make($request->all(), [
            'to' => 'required|string',
            'message' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => 'Incomplete request', 'error' => $validator->errors()]);
        }
        
        // $message = $request->message;
        $message = str_replace('\n', "\n", $request->message); // handle escaped input

        if($request->to == 'all'){
            $data = User::select('id','telegram_id')->get();
            foreach($data AS $d){
                if($d->telegram_id != NULL){
                    $d->notify(new TelegramBotCastNotification($message));
                }
            }
        }else{
            $data = User::find($request->to);
            if($data->telegram_id != NULL){
                $data->notify(new TelegramBotCastNotification($message));
            }
        }

        return response()->json(['success'=>true, 'data'=>$data], 200);
    }

}
