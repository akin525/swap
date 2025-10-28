<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\GeneralSettings;
use App\Models\Admin;
use App\Models\AdminLogin;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;


class AuthController extends Controller
{
    /**
     * Show the application dashboard.
     *
     * @return \Illuminate\Http\Response
     */

    //Login
    public function login(Request $request)
    {
        $admin = Admin::where(['email' => $request->email, 'status' => 1])->first();

        if (! $admin || ! Hash::check($request->password, $admin->password)) {
            return response()->json(['success'=>false, 'status'=>'invalid_credentials', 'message'=>'Wrong Login Details']);
        }

        $admin_ip = request()->ip();

        $baseUrl = "http://www.geoplugin.net/";
        $endpoint = "json.gp?ip=" . $admin_ip."";
        $httpVerb = "GET";
        $contentType = "application/json"; //e.g charset=utf-8
        $headers = array (
            "Content-Type: $contentType",

        );

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_URL, $baseUrl.$endpoint);
        curl_setopt($ch, CURLOPT_HTTPGET, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $content = json_decode(curl_exec( $ch ),true);
        $err     = curl_errno( $ch );
        $errmsg  = curl_error( $ch );
        curl_close($ch);

        $conti = $content['geoplugin_continentName'];
        $country = $content['geoplugin_countryName'];
        $city = $content['geoplugin_city'];

        $ul['admin_id'] = $admin->id;
        $ul['admin_ip'] =  request()->ip();
        if($city){
        $ul['location'] = ''.$conti.', '.$country.' , '.$city.'';
        }
        else{
        $ul['location'] = 'Unknown';
        }
        $ul['details'] = $_SERVER['HTTP_USER_AGENT'];
        AdminLogin::create($ul);

        // delete admin tokens
        $admin->tokens()->delete();

        $token = $admin->createToken($request->device_name)->plainTextToken;

        return response()->json(['success'=>true, 'status'=>'ok', 'token'=>$token, 'accessToken'=>$token, 'admin' => $admin], 200);
    }
    
    public function changePassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'current_password' => 'required|string',
            'password' => 'required|string|min:5|confirmed'
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => 'Incomplete request', 'error' => $validator->errors()], 401);
        }

        $current_password = $request->current_password;
        $new_password = $request->password;
        if (Hash::check($current_password, auth()->guard('admin')->user()->password)) {
            Admin::where(['id'=>auth()->guard('admin')->user()->id])->update(['password' => Hash::make($new_password), 'pass_changed' => Carbon::now()]);

            return response()->json(['success'=>true, 'status'=>'ok', 'message'=>'Password Changed Successfully.'], 200);
        } else {
            return response()->json(['success' => false, 'status'=>'no_match', 'message' => 'Current Password Does Not Match.'], 400);
        }
    }
}
