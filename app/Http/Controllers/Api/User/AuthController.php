<?php

namespace App\Http\Controllers\Api\User;

use App\Notifications\TelegramNotification;
use App\Notifications\TelegramMessageNotification;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserLogin;
use App\Models\RegEmail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Tymon\JWTAuth\Facades\JWTAuth;

class AuthController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api', ['except' => [
            'register', 'verifyPhone', 'resendPhoneCode',
            'createPassword', 'verifyEmail', 'resendEmailVerification',
             'verifyFace', 'login'
        ]]);
    }
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'phone_number' => 'required|string|unique:users',
            'country_code' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 422);
        }

        // Generate verification code
        $verificationCode = rand(100000, 999999);

        // Store user with pending verification
        $user = User::create([
            'phone_number' => $request->phone_number,
            'country_code' => $request->country_code,
            'phone_verification_code' => $verificationCode,
            'phone_verification_sent_at' => now(),
            'status' => 'phone_verification_pending',
        ]);

        // Send SMS with verification code
        // You'll need to implement an SMS service integration here
        // $this->sendSms($request->country_code . $request->phone_number, "Your verification code is: {$verificationCode}");

        return response()->json([
            'success'=>true,
            'message' => 'Verification code sent to your phone number',
            'user_id' => $user->id,
            'code'=>$verificationCode,
        ]);
    }

    public function verifyPhone(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|exists:users,id',
            'verification_code' => 'required|string|size:6',
        ]);

        if ($validator->fails()) {
            return response()->json(['success'=>false, 'error' => $validator->errors()]);
        }

        $user = User::find($request->user_id);

        // Check if code is valid and not expired (10 minutes)
        if ($user->phone_verification_code !== $request->verification_code) {
            return response()->json(['success'=>false, 'error' => 'Invalid verification code']);
        }

        if (Carbon::parse($user->phone_verification_sent_at)->addMinutes(10)->isPast()) {
            return response()->json(['success'=> false,'error' => 'Verification code has expired']);
        }

        // Update user status
        $user->update([
            'phone_verified_at' => now(),
            'status' => 'email_verification_pending',
            'phone_verification_code' => null
        ]);

        return response()->json([
            'success'=>true,
            'message' => 'Phone number verified successfully',
            'user_id' => $user->id,
            'next_step' => 'email_verification'
        ]);
    }

    public function resendPhoneCode(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|exists:users,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['success'=>false, 'error' => $validator->errors()]);
        }

        $user = User::find($request->user_id);

        if ($user->phone_verified_at) {
            return response()->json(['success'=>false,'message' => 'Phone already verified']);
        }

        // Generate new verification code
        $verificationCode = rand(100000, 999999);

        $user->update([
            'phone_verification_code' => $verificationCode,
            'phone_verification_sent_at' => now(),
        ]);

        // Send SMS with verification code
        // $this->sendSms($user->country_code . $user->phone_number, "Your verification code is: {$verificationCode}");

        return response()->json([
            'success'=>true,
            'message' => 'Verification code resent to your phone number'
        ]);
    }

    public function addEmail(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|exists:users,id',
            'email' => 'required|string|email|max:255|unique:users',
            'is_primary_contact' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['success'=>false, 'error' => $validator->errors()]);
        }

        $user = User::find($request->user_id);

        // Generate email verification token
        $token = Str::random(60);

        $user->update([
            'email' => $request->email,
            'is_primary_contact' => $request->is_primary_contact ?? false,
            'email_verification_token' => $token,
            'email_verification_sent_at' => now(),
        ]);

        // Send verification email
//        Mail::to($user->email)->send(new EmailVerification($user, $token));

        return response()->json([
            'success'=>true,
            'message' => 'Verification email sent',
            'user_id' => $user->id,
            'next_step' => 'verify_email'
        ]);
    }

    public function verifyEmail(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'token' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 422);
        }

        $user = User::where('email_verification_token', $request->token)->first();

        if (!$user) {
            return response()->json(['success'=>false, 'error' => 'Invalid verification token']);
        }

        if (Carbon::parse($user->email_verification_sent_at)->addDay()->isPast()) {
            return response()->json(['success'=>false, 'error' => 'Verification token has expired']);
        }

        $user->update([
            'email_verified_at' => now(),
            'status' => 'password_creation_pending',
            'email_verification_token' => null
        ]);

        return response()->json([
            'success'=>true,
            'message' => 'Email verified successfully',
            'user_id' => $user->id,
            'next_step' => 'create_password'
        ]);
    }

    public function resendEmailVerification(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|exists:users,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['success'=>false, 'error' => $validator->errors()]);
        }

        $user = User::find($request->user_id);

        if ($user->email_verified_at) {
            return response()->json(['success'=>false, 'message' => 'Email already verified']);
        }

        // Generate new token
        $token = Str::random(60);

        $user->update([
            'email_verification_token' => $token,
            'email_verification_sent_at' => now(),
        ]);

        // Send verification email
        Mail::to($user->email)->send(new EmailVerification($user, $token));

        return response()->json([
            'success'=>true,
            'message' => 'Verification email resent'
        ]);
    }

    public function createPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|exists:users,id',
            'password' => 'required|string|min:8|regex:/^.*(?=.{3,})(?=.*[a-zA-Z])(?=.*[0-9]).*$/',
        ]);

        if ($validator->fails()) {
            return response()->json(['success'=>false, 'error' => $validator->errors()]);
        }

        $user = User::find($request->user_id);

        $user->update([
            'password' => Hash::make($request->password),
            'status' => 'bvn_verification_pending'
        ]);

        return response()->json([
            'success'=>true,
            'message' => 'Password created successfully',
            'user_id' => $user->id,
            'next_step' => 'verify_bvn'
        ]);
    }


    public function verifyBvn(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|exists:users,id',
            'bvn' => 'required|string|size:11',
            'date_of_birth' => 'required|date|date_format:d/m/Y',
        ]);

        if ($validator->fails()) {
            return response()->json(['success'=>false, 'error' => $validator->errors()]);
        }

        $user = User::find($request->user_id);

        // Here you would typically validate with a BVN verification service
        // For this example, we'll assume it's successful

        // Generate verification code for BVN phone
        $verificationCode = rand(100000, 999999);

        $user->update([
            'bvn' => $request->bvn,
            'date_of_birth' => $request->date_of_birth,
            'bvn_verification_code' => $verificationCode,
            'bvn_verification_sent_at' => now(),
        ]);

        // In a real implementation, the BVN service would send the SMS
        // to the phone number registered with the BVN

        return response()->json([
            'success'=>true,
            'message' => 'BVN verification code sent',
            'user_id' => $user->id,
            'next_step' => 'verify_bvn_code'
        ]);
    }

    public function verifyBvnCode(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|exists:users,id',
            'verification_code' => 'required|string|size:6',
        ]);

        if ($validator->fails()) {
            return response()->json(['success'=>false, 'error' => $validator->errors()]);
        }

        $user = User::find($request->user_id);

        if ($user->bvn_verification_code !== $request->verification_code) {
            return response()->json(['success'=>false,'error' => 'Invalid verification code']);
        }

        if (Carbon::parse($user->bvn_verification_sent_at)->addMinutes(10)->isPast()) {
            return response()->json(['error' => 'Verification code has expired'], 422);
        }

        // Update user details from BVN service
        $user->update([
//            'first_name' => $user->first_name,
//            'last_name' => $user->last_name,
            'bvn_verified_at' => now(),
            'status' => 'face_verification_pending',
            'bvn_verification_code' => null
        ]);

        return response()->json([
            'success'=>true,
            'message' => 'BVN verified successfully',
            'user_id' => $user->id,
            'next_step' => 'verify_face',
            'first_name' => $user->first_name,
            'last_name' => $user->last_name
        ]);
    }

    public function verifyFace(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|exists:users,id',
            'face_image' => 'required|image|max:5120', // 5MB max
        ]);

        if ($validator->fails()) {
            return response()->json(['success'=>false, 'error' => $validator->errors()]);
        }

        $user = User::find($request->user_id);

        // Store the face image
        $path = $request->file('face_image')->store('face_verifications', 'public');

        // In a real implementation, you would send this to a facial recognition service
        // to compare with the BVN registered face

        // For this example, we'll assume verification is successful
        $user->update([
            'face_verified_at' => now(),
            'face_image_path' => $path,
            'status' => 'active',
        ]);

        // Generate JWT token
        $token = JWTAuth::fromUser($user);

        return response()->json([
            'success'=>true,
            'message' => 'Registration completed successfully',
            'user' => $user,
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => auth()->factory()->getTTL() * 60
        ]);
    }

    public function signin(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'login' => 'required|string', // Can be email or phone
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['success'=>false, 'error' => $validator->errors()]);
        }
        // Determine if login is email or phone
        $loginType = filter_var($request->login, FILTER_VALIDATE_EMAIL) ? 'email' : 'phone_number';

        $credentials = [
            $loginType => $request->login,
            'password' => $request->password
        ];

        if (!$token = auth()->attempt($credentials)) {
            return response()->json(['success'=>false,'error' => 'Unauthorized']);
        }

        return $this->respondWithToken($token);
    }

    public function me()
    {
        return response()->json(auth()->user());
    }

    public function logout()
    {
        auth()->logout();

        return response()->json(['message' => 'Successfully logged out']);
    }

    public function refresh()
    {
        return $this->respondWithToken(auth()->refresh());
    }

    protected function respondWithToken($token)
    {
        return response()->json([
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => auth()->factory()->getTTL() * 6000,
            'user' => auth()->user()
        ]);
    }
    public function login(Request $request){
        $validator = Validator::make($request->all(), [
            'username' => 'required|string',
            'password' => 'required|string',
            'device_name' => 'required|string'
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => 'Incomplete request', 'error' => $validator->errors()], 401);
        }

        $user = User::where('username', $request->username)->first();

        if (!$user) {
            return response()->json(['success'=>false, 'message'=>'Invalid Login Credentials']);
        }

        if ($user->status != "active") {
            return response()->json(['success'=>false, 'message'=>'Inactive/Blocked Account']);
        }

        if ($user->flac > 2) {
            return response()->json(['success'=>false, 'status'=>'login_locked', 'message'=>'Account Locked Temporarily']);
        }

        if (! $user || ! Hash::check($request->password, $user->password)) {
            // record failed attempt count (flac = failed login attempt count)
            $user->flac += 1;
            $user->save();
            if($user->flac > 2){
                return response()->json(['success'=>false, 'status'=>'login_locked', 'message'=>'Account Locked Temporarily']);
            }else{
                return response()->json(['success'=>false, 'status'=>'invalid_credentials', 'message'=>'Invalid Login Credentials']);
            }
        }

        // delete user tokens
        $user->tokens()->delete();

        $user->flac = 0;
        $user->save();

        $token = $user->createToken($request->device_name)->plainTextToken;

        $siteBot = env('TELEGRAM_BOT_NAME');

        return response()->json(['success'=>true, 'token'=>$token, 'data'=>['token'=>$token, 'user' => $user->makeHidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'email_verified_at', 'email_code', 'telegram_otp', 'flac'])], 'siteBot' => $siteBot], 200);
    }

    // register
//    public function register(Request $request)
//    {
//        $validator = Validator::make($request->all(), [
//            'firstname' => 'required|string',
//            'lastname' => 'required|string',
//            'username' => 'required|string|unique:users',
//            'email' => 'required|string|email|unique:users',
//            'password' => 'required|string|min:8',
//            'referral' => 'required|string',
//            'device_name' => 'required|string',
//        ]);
//
//        if($validator->fails()){
//            return response()->json(['success'=>false, 'message'=>implode(",", $validator->errors()->all()), 'error' => $validator->errors()]);
//        }
//
//        //find referral
//        $ref = User::where('ref_code', $request->referral)->first();
//        if(!$ref){
//            // $referral = NULL;
//            return response()->json(['success'=>false, 'message'=>"Invalid Sponsor provided"]);
//        }else{
//            // get last side
//            $refSide = getRefSide($ref->id);
//            $referral = $request->referral;
//        }
//        $ref_code  = generateRefCode($request->firstname);
//
//        try{
//            $user = User::create([
//                'firstname' => $request->firstname,
//                'lastname' => $request->lastname,
//                'username' => $request->username,
//                'email' => $request->email,
//                'password' => Hash::make($request->password),
//                'arg_address' => NULL,
//                'ref_code' => $ref_code,
//                'referral' => $referral,
//                'ref_side' => $refSide,
//            ]);
//
//            $walletAddress = generateUniqueSolanaStyleAddress($user);
//
//            $user_ip = request()->ip();
//                // Use JSON encoded string and converts
//                // it into a PHP variable
//
//            $baseUrl = "http://www.geoplugin.net/";
//            $endpoint = "json.gp?ip=" . $user_ip."";
//            $httpVerb = "GET";
//            $contentType = "application/json"; //e.g charset=utf-8
//            $headers = array (
//                "Content-Type: $contentType",
//
//            );
//
//            $ch = curl_init();
//            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
//            curl_setopt($ch, CURLOPT_URL, $baseUrl.$endpoint);
//            curl_setopt($ch, CURLOPT_HTTPGET, true);
//            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
//            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
//
//            $content = json_decode(curl_exec( $ch ),true);
//            $err     = curl_errno( $ch );
//            $errmsg  = curl_error( $ch );
//            curl_close($ch);
//
//
//                $conti = $content['geoplugin_continentName'];
//                $country = $content['geoplugin_countryName'];
//                $city = $content['geoplugin_city'];
//
//
//            $ul['user_id'] = $user->id;
//            $ul['user_ip'] =  request()->ip();
//            if($city){
//            $ul['location'] = ''.$conti.', '.$country.' , '.$city.'';
//            }
//            else{
//            $ul['location'] = 'Unknown';
//            }
//            $ul['details'] = $_SERVER['HTTP_USER_AGENT'];
//            UserLogin::create($ul);
//
//            $token = $user->createToken($request->device_name)->plainTextToken;
//
//            $siteBot = env('TELEGRAM_BOT_NAME');
//
//            return response()->json(['success'=>true, 'token'=>$token, 'message'=>'Registration Successful', 'data' => ['token'=>$token, 'user' => $user], 'siteBot' => $siteBot], 200);
//        } catch (\Exception $e) {
//            return response()->json(['success' => false, 'message' => $e]);
//        }
//    }

    public function resendCode($email){
        $data = RegEmail::where(['email' => $email])->first();
        if(!$data){
            return response()->json(['success'=>false, 'status'=>'not_found', 'message'=>'Email Not Found']);
        }

        $code  = rand(100000,999999);
        $expiry = Carbon::now()->addMinutes(5);
        $email_code = $code;
        $verifier = uniqid() . hexdec(uniqid()) . rand();

        DB::table('reg_email')->where(['email' => $email])->update([
            'code' => $code,
            'expiry' => $expiry,
            'verifier' => $verifier,
        ]);

        try{
            send_email_verify_email($email, 'Email Verification', $code);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e]);
        }

        return response()->json(['success'=>true, 'status'=>'00', 'message' => 'Verification code sent successfully.'], 200);
    }

    //Forgot Password
    public function reset_password_code(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
        ]);

        if ($validator->fails()) {
            return response()->json(['success'=>false, 'status'=>'validation_error', 'message'=>'Email Required for Password Reset'], 401);
        }

        $user = User::Where(['email' => $request->email])->first();

        if (isset($user)) {
            $token = Str::random(120);
            $code  = rand(100000,999999);
            $expiry = Carbon::now()->addMinutes(5);
            DB::table('password_resets')->insert([
                'email' => $user['email'],
                'token' => $token,
                'code' => $code,
                'expiry' => $expiry,
                'created_at' => now(),
            ]);

            // if telegram id set and verified
            if($user->telegram_id != NULL && $user->telegram_verified == 1){
                // send code to telegram too
                // telegram notfication
                $message = "Password Reset Verification Code.";
                $note = "Kindly use the code ".$code." to complete your password reset. This code expires in 5 minutes";
                $user->notify(new TelegramMessageNotification($message,$note));
            }else{
                return response()->json(['success'=>false, 'message' => 'Unable to send Code. Telegram ID not connected to account yet.'], 200);
            }

            return response()->json(['success'=>true, 'message' => 'Code sent successfully.'], 200);
        }

        return response()->json(['success'=>true, 'message' => 'Code sent successfully.'], 200);
    }

    //Password Reset
    public function reset_password_code_submit(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'code' => 'required|string',
            'password' => 'required|string',
            'confirm_password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['success'=>false, 'message'=>'Email Required for Password Reset'], 401);
        }

        $data = DB::table('password_resets')->where(['email' => $request->email, 'code' => $request->code])->first();
        if (isset($data)) {
            // check if code already expired (5mins)
            $timenow = Carbon::now();
            if($data->expiry < $timenow){
                return response()->json(['success'=>false, 'message'=>'Password Reset Code Expired']);
            }

            if ($request->password == $request->confirm_password) {
                DB::table('users')->where(['email' => $data->email])->update([
                    'password' => bcrypt($request->confirm_password),
                    'flac' => 0
                ]);
                DB::table('password_resets')->where(['email' => $request->email, 'code' => $request->code])->delete();

                // send email for successful reset
                $u = User::where(['email'=>$request->email])->first();
                // password_reset_success_email($request->email, $u->firstname, "Password Reset Successful");
                return response()->json(['success'=>true, 'message' => 'Password reset successfully.'], 200);
            }

            return response()->json(['success'=>false, 'message' => "Password did't match!"], 401);
        }
        return response()->json(['success'=>false, 'message' => 'Invalid token'], 400);
    }

    public function country(){
        $data = Country::select('name','isoName','currencyCode','currencyName','callingCodes')->where(['status' => 'active'])->get();

        return response()->json(['success'=>true, 'status'=>'00', 'message'=>'Successful', 'data' => $data], 200);
    }

    public function state($country_code){
        $data = State::select('id','name')->where(['country_code' => $country_code,'status' => 'active'])->get();

        return response()->json(['success'=>true, 'status'=>'00', 'message'=>'Successful', 'data' => $data], 200);
    }

    public function extras(){
        $data['BusinessType'] = BusinessType::select('id','name')->where(['status' => 'active'])->get();
        $data['Industry'] = Industry::select('id','name')->where(['status' => 'active'])->orderBy('name')->get();
        $data['Category'] = Category::select('id','industry_id','name')->where(['status' => 'active'])->orderBy('name')->get();
        $data['State'] = State::select('id','country_code','name')->get();
        $data['Lga'] = Lga::select('id','state_id','name')->get();

        return response()->json(['success'=>true, 'status'=>'00', 'message'=>'Successful', 'data' => $data], 200);
    }

}
