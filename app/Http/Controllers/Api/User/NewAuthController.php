<?php

namespace App\Http\Controllers\Api\User;

use App\Http\Controllers\Controller;
use App\Models\Country;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Tymon\JWTAuth\Facades\JWTAuth;

class NewAuthController extends Controller
{

    public function __construct()
    {
        $this->middleware('auth:api', ['except' => [
            'register', 'verifyPhone', 'resendPhoneCode',
            'addEmail', 'verifyEmail', 'resendEmailVerification',
            'createPassword', 'verifyBvn', 'verifyBvnCode',
            'verifyFace', 'signin', 'login'
        ]]);
    }

    /**
     * Register a new user with phone number
     * Corresponds to the first screen showing "Welcome to Swappay" with phone number input
     */
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'phone' => 'required|string|unique:users',
            'country_code' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'error' => $validator->errors()], 422);
        }

        // Generate 6-digit verification code
        $verificationCode = rand(100000, 999999);

        // Store user with pending verification
        $user = User::create([
            'phone' => $request->phone,
            'country_code' => $request->country_code,
            'phone_verification_code' => $verificationCode,
            'phone_verification_sent_at' => now(),
            'status' => 'phone_verification_pending',
        ]);

        // Send SMS with verification code
        // $this->sendSms($request->country_code . $request->phone_number, "Your Swappay verification code is: {$verificationCode}");

        return response()->json([
            'success' => true,
            'message' => 'Verification code sent to your phone number',
            'user_id' => $user->id,
            'code' => $verificationCode, // Remove in production
        ]);
    }

    /**
     * Verify phone number with OTP code
     * Corresponds to the OTP verification screen
     */
    public function verifyPhone(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|exists:users,id',
            'verification_code' => 'required|string|size:6',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'error' => $validator->errors()]);
        }

        $user = User::find($request->user_id);

        // Check if code is valid and not expired (45 seconds as shown in UI)
        if ($user->phone_verification_code !== $request->verification_code) {
            return response()->json(['success' => false, 'error' => 'Invalid verification code']);
        }

        if (Carbon::parse($user->phone_verification_sent_at)->addSeconds(450)->isPast()) {
            return response()->json(['success' => false, 'error' => 'Verification code has expired']);
        }

        // Update user status
        $user->update([
            'phone_verified_at' => now(),
            'status' => 'email_verification_pending',
            'phone_verification_code' => null
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Phone number verified successfully',
            'user_id' => $user->id,
            'next_step' => 'email_verification'
        ]);
    }

    /**
     * Resend phone verification code
     * Corresponds to "Resend it" option on OTP screen
     */
    public function resendPhoneCode(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|exists:users,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'error' => $validator->errors()]);
        }

        $user = User::find($request->user_id);

        if ($user->phone_verified_at) {
            return response()->json(['success' => false, 'message' => 'Phone already verified']);
        }

        // Generate new verification code
        $verificationCode = rand(100000, 999999);

        $user->update([
            'phone_verification_code' => $verificationCode,
            'phone_verification_sent_at' => now(),
        ]);

        // Send SMS with verification code
        // $this->sendSms($user->country_code . $user->phone_number, "Your Swappay verification code is: {$verificationCode}");

        return response()->json([
            'success' => true,
            'message' => 'Verification code resent to your phone number',
            'code' => $verificationCode, // Remove in production
        ]);
    }

    /**
     * Add email address for verification
     * Corresponds to "Let's verify your email" screen
     */
    public function addEmail(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|exists:users,id',
            'email' => 'required|string|email|max:255|unique:users',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'error' => $validator->errors()]);
        }

        $user = User::find($request->user_id);

        // Generate email verification token
        $token = Str::random(60);

        $user->update([
            'email' => $request->email,
            'email_code' => $token,
//            'email_verification_sent_at' => now(),
        ]);

        // Send verification email
        // Mail::to($user->email)->send(new EmailVerification($user, $token));

        return response()->json([
            'success' => true,
            'message' => 'Verification email sent',
            'user_id' => $user->id,
            'next_step' => 'verify_email',
            'token' => $token, // Remove in production
        ]);
    }

    /**
     * Verify email with token from email link
     * Corresponds to "Email verified" success screen
     */
    public function verifyEmail(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'token' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'error' => $validator->errors()], 422);
        }

        $user = User::where('email_code', $request->token)->first();

        if (!$user) {
            return response()->json(['success' => false, 'error' => 'Invalid verification token']);
        }

        if (Carbon::parse($user->email_verification_sent_at)->addDay()->isPast()) {
            return response()->json(['success' => false, 'error' => 'Verification token has expired']);
        }

        $user->update([
            'email_verified_at' => now(),
            'status' => 'password_creation_pending',
            'email_code' => null
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Email verified successfully',
            'user_id' => $user->id,
            'next_step' => 'create_password'
        ]);
    }

    /**
     * Resend email verification
     * Corresponds to "Resend it" option on email verification screen
     */
    public function resendEmailVerification(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|exists:users,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'error' => $validator->errors()]);
        }

        $user = User::find($request->user_id);

        if ($user->email_verified_at) {
            return response()->json(['success' => false, 'message' => 'Email already verified']);
        }

        // Generate new token
        $token = Str::random(60);

        $user->update([
            'email_verification_token' => $token,
            'email_verification_sent_at' => now(),
        ]);

        // Send verification email
        // Mail::to($user->email)->send(new EmailVerification($user, $token));

        return response()->json([
            'success' => true,
            'message' => 'Verification email resent',
            'token' => $token, // Remove in production
        ]);
    }

    /**
     * Create password
     * Corresponds to "Create your password" screen
     */
    public function createPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|exists:users,id',
            'password' => 'required|string|min:8|regex:/^.*(?=.{3,})(?=.*[a-zA-Z])(?=.*[0-9]).*$/',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'error' => $validator->errors()]);
        }

        $user = User::find($request->user_id);

        $user->update([
            'password' => Hash::make($request->password),
            'status' => 'bvn_verification_pending'
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Password created successfully',
            'user_id' => $user->id,
            'next_step' => 'verify_bvn'
        ]);
    }

    /**
     * Verify BVN and date of birth
     * Corresponds to "Enter BVN and date of birth" screen
     */
    public function verifyBvn(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|exists:users,id',
            'bvn' => 'required|string|size:11',
            'date_of_birth' => 'required|date_format:d/m/Y',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'error' => $validator->errors()]);
        }

        $user = User::find($request->user_id);

        // Generate verification code for BVN phone
        $verificationCode = rand(100000, 999999);

        $user->update([
            'bvn' => $request->bvn,
            'dob' => $request->date_of_birth,
            'bvn_verification_code' => $verificationCode,
            'bvn_verification_sent_at' => now(),
        ]);

        // In a real implementation, the BVN service would send the SMS
        // to the phone number registered with the BVN

        return response()->json([
            'success' => true,
            'message' => 'BVN verification code sent',
            'user_id' => $user->id,
            'next_step' => 'verify_bvn_code',
            'code' => $verificationCode, // Remove in production
        ]);
    }

    /**
     * Verify BVN with SMS code
     * Corresponds to "We just sent an SMS" screen for BVN verification
     */
    public function verifyBvnCode(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|exists:users,id',
            'verification_code' => 'required|string|size:6',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'error' => $validator->errors()]);
        }

        $user = User::find($request->user_id);

        if ($user->bvn_verification_code !== $request->verification_code) {
            return response()->json(['success' => false, 'error' => 'Invalid verification code']);
        }

        if (Carbon::parse($user->bvn_verification_sent_at)->addSeconds(4000)->isPast()) {
            return response()->json(['success' => false, 'error' => 'Verification code has expired']);
        }

        // Update user details from BVN service (in a real implementation)
        $user->update([
//            'bvn_verified_at' => now(),
            'status' => 'user_info_pending',
            'bvn_verification_code' => null
        ]);

        return response()->json([
            'success' => true,
            'message' => 'BVN verified successfully',
            'user_id' => $user->id,
            'next_step' => 'add_user_info',
        ]);
    }

    /**
     * Add user information (first name, last name)
     * Corresponds to "Your details" screen
     */
    public function addUserInfo(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|exists:users,id',
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'referral_code' => 'nullable|string',
//            'marketing_consent' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'error' => $validator->errors()]);
        }

        $user = User::find($request->user_id);

        $userRefCode =$this->generateUniqueRefCode();

    $user->update([
        'firstname' => $request->first_name,
        'lastname' => $request->last_name,
        'ref_code' => $userRefCode,
        'referral' => $request->referral_code,
//            'marketing_consent' => $request->marketing_consent ?? false,
        'status' => 'active'
    ]);

        $wallets = $this->createDefaultWallets($user->id);

        $token = JWTAuth::fromUser($user);

        return response()->json([
            'success' => true,
            'message' => 'Registration completed successfully',
            'user' => $user,
            'wallet'=>$wallets,
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => auth()->factory()->getTTL() * 60
        ]);
}

    private function createDefaultWallets(int $userId): array
    {

        $currencies = [
            ['code' => 'NGN', 'single' => '30000', 'cumulative' => '100000'],
            ['code' => 'GHS', 'single' => '30000', 'cumulative' => '100000'],
            ['code' => 'ZAR', 'single' => '30000', 'cumulative' => '100000'],
            ['code' => 'USD', 'single' => '30000', 'cumulative' => '100000'],
        ];

        $result = [];
        foreach ($currencies as $c) {
            $wallet = Wallet::firstOrCreate(
                [
                    'user_id'  => $userId,
                    'currency' => $c['code'],
                ],
                [
                    'balance'                   => '0',        // varchar per your schema
                    'cashback'                  => '0',        // varchar per your schema
                    'transfer_single_limit'     => $c['single'],
                    'transfer_cumulative_limit' => $c['cumulative'],
                    'status'                    => 1,          // active
                ]
            );

            $result[] = [
                'id'        => $wallet->id,
                'currency'  => $wallet->currency,
                'balance'   => $wallet->balance,
                'cashback'  => $wallet->cashback,
                'limits'    => [
                    'single'     => $wallet->transfer_single_limit,
                    'cumulative' => $wallet->transfer_cumulative_limit,
                ],
                'status'    => $wallet->status,
            ];
        }

        return $result;
    }

    /**
     * Generate a unique referral code (8-char alphanumeric).
     */
    private function generateUniqueRefCode(): string
    {
        do {
            $refCode = strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 8));
        } while (User::where('ref_code', $refCode)->exists());

        return $refCode;
    }



    /**
     * Verify face with facial recognition
     * Corresponds to "Verify your BVN" screen with facial recognition
     */
    public function verifyFace(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|exists:users,id',
            'face_image' => 'required|image|max:5120', // 5MB max
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'error' => $validator->errors()]);
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
            'success' => true,
            'message' => 'Registration completed successfully',
            'user' => $user,
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => auth()->factory()->getTTL() * 60
        ]);
    }

    /**
     * Sign in with email/phone and password
     * Corresponds to the "Sign in" screen
     */
    public function signin(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'login' => 'required|string', // Can be email or phone
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'error' => $validator->errors()]);
        }

        // Determine if login is email or phone
        $loginType = filter_var($request->login, FILTER_VALIDATE_EMAIL) ? 'email' : 'phone_number';

        $credentials = [
            $loginType => $request->login,
            'password' => $request->password
        ];

        if (!$token = auth()->attempt($credentials)) {
            return response()->json(['success' => false, 'error' => 'Invalid email/phone or password']);
        }

        // Check if user is active
        $user = auth()->user();
        if ($user->status !== 'active') {
            auth()->logout();
            return response()->json(['success' => false, 'error' => 'Account is not active']);
        }

        return $this->respondWithToken($token);
    }

    /**
     * Get authenticated user
     */
    public function me()
    {
        return response()->json(['success' => true, 'user' => auth()->user()]);
    }

    /**
     * Log out user
     */
    public function logout()
    {
        auth()->logout();

        return response()->json(['success' => true, 'message' => 'Successfully logged out']);
    }

    /**
     * Refresh JWT token
     */
    public function refresh()
    {
        return $this->respondWithToken(auth()->refresh());
    }

    /**
     * Format token response
     */
    protected function respondWithToken($token)
    {
        return response()->json([
            'success' => true,
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => auth()->factory()->getTTL() * 60,
            'user' => auth()->user()
        ]);
    }

    /**
     * Alternative login method with device name (for Sanctum)
     */
    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|string',
            'password' => 'required|string',
            'device_name' => 'required|string'
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => 'Incomplete request', 'error' => $validator->errors()], 401);
        }

        $user = User::where('email', $request->email)
            ->orWhere('phone', $request->phone)
            ->first();

        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Invalid Login Credentials']);
        }

        if ($user->status != "active") {
            return response()->json(['success' => false, 'message' => 'Inactive/Blocked Account']);
        }

        if ($user->flac > 2) {
            return response()->json(['success' => false, 'status' => 'login_locked', 'message' => 'Account Locked Temporarily']);
        }

        if (!$user || !Hash::check($request->password, $user->password)) {
            // record failed attempt count (flac = failed login attempt count)
            $user->flac += 1;
            $user->save();
            if ($user->flac > 2) {
                return response()->json(['success' => false, 'status' => 'login_locked', 'message' => 'Account Locked Temporarily']);
            } else {
                return response()->json(['success' => false, 'status' => 'invalid_credentials', 'message' => 'Invalid Login Credentials']);
            }
        }

        // delete user tokens
        $user->tokens()->delete();

        $user->flac = 0;
        $user->save();

        $token = $user->createToken($request->device_name)->plainTextToken;

        $siteBot = env('TELEGRAM_BOT_NAME');

        return response()->json([
            'success' => true,
            'token' => $token,
            'data' => [
                'token' => $token,
                'user' => $user->makeHidden([
                    'password', 'two_factor_secret', 'two_factor_recovery_codes',
                    'email_verified_at', 'email_code', 'telegram_otp', 'flac'
                ])
            ],
            'siteBot' => $siteBot
        ], 200);

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
