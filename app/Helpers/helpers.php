<?php

use App\Models\User;


if (!function_exists('getRefSide')) {

    function getRefSide($id){
        $user = User::find($id);

        // Check for automatic placement
        if ($user->ref_default_placement === 'auto') {

            // Get the most recent user referred by this user
            $latestReferral = User::where('referral', $user->ref_code)->latest()->first();

            if ($latestReferral) {
                // If last referral went to left, put this one on right (and vice versa)
                return $latestReferral->ref_side === 'left' ? 'right' : 'left';
            }

            // Default to 'left' if no referrals exist yet
            return 'left';
        }

        // Return the manually set default placement
        return $user->ref_default_placement;
    }
}

function generateUniqueSolanaStyleAddress($user, $length = 44)
{
    $prefix = 'AGP';
    $base58Chars = '123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz';

    do {
        $randomPart = '';
        for ($i = 0; $i < $length - strlen($prefix); $i++) {
            $randomPart .= $base58Chars[random_int(0, strlen($base58Chars) - 1)];
        }

        $address = $prefix . $randomPart;

        // Check uniqueness in the database
        $exists = User::where('arg_address', $address)->exists();

    } while ($exists);

    // Assign and save
    $user->arg_address = $address;
    $user->save();

    return $address;
}

function send_smsTermi($number, $body){

    $curl = curl_init();

    curl_setopt_array($curl, array(
        CURLOPT_URL => 'https://api.ng.termii.com/api/sms/send',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS =>'{
          "api_key":"TLINFJUEoeSEwhHJhKdJbMHMkupMoyLVvuFrlNiFLwNuVjDingptxuQpGRLxzY",
          "to":"'.$number.'",
          "from":"CASHONRAILS",
          "sms":"'.$body.'",
          "type":"plain",
          "channel":"dnd"
        }',
        CURLOPT_HTTPHEADER => array(
          'Content-Type: application/json'
        ),
    ));

    $response = curl_exec($curl);

    curl_close($curl);

    return $response;
}

if (!function_exists('send_smsGiftBills')) {

    function send_smsGiftBills($to, $message)
    {
        $api = "https://giftbills.com/api/sms/username=cashonrails&password=CashdrealRails24&route=2&sender=CASHONRAILS&recipient={{number}}&message={{message}}";
        $sendtext = urlencode($message);
        $appi = $api;
        $appi = str_replace("{{number}}", $to, $appi);
        $appi = str_replace("{{message}}", $sendtext, $appi);
    }
}

require __DIR__ . '/generation.php';
// require __DIR__ . '/mail.php';
