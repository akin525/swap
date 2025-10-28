<?php


use App\Models\User;
// use App\Models\BusinessTeamInvite;
// use App\Models\Customer;
// use App\Models\Business;
use Carbon\Carbon;
use Illuminate\Support\Str;


if (!function_exists('generateRefCode')) {

    function generateRefCode($name){
        $rend = strtoupper(substr($name, 0, 6)).random_int(0000, 9999);
            $check = User::where('ref_code', $rend)->first();

        if($check == true){
            $rend = generateRefCode($name);
        }
        return $rend;
    }
}

// if (!function_exists('generateSubscriptionCode')) {

//     function generateSubscriptionCode(){
//         $rend = "SUB_".strtolower(Str::random(15));
//             $check = Subscription::where('subscription_code', $rend)->first();

//         if($check == true){
//             $rend = generateSubscriptionCode();
//         }
//         return $rend;
//     }
// }

// if (!function_exists('generateSubToken')) {

//     function generateSubToken(){
//         $rend = strtolower(Str::random(25)).".".strtolower(Str::random(38))."-".Str::random(6)."_".Str::random(8)."-".strtolower(Str::random(18));
//             $check = Subscription::where('token', $rend)->first();

//         if($check == true){
//             $rend = generateSubToken();
//         }
//         return $rend;
//     }
// }

// function generateSettlementBatch()
// {
//     $a = uniqid() . hexdec(uniqid()) . rand();
//     $b = str_shuffle($a);
//     $c = substr($b, 0, 13);
//     return $c;
// }

// function generatePayoutBatch()
// {
//     $a = uniqid() . hexdec(uniqid()) . rand();
//     $b = str_shuffle($a);
//     $c = substr($b, 0, 13);
//     return $c;
// }

