<?php

namespace App\Http\Middleware;

use Closure;
use App\Models\GeneralSettings;
use App\Models\User;
use Illuminate\Http\Request;

class CheckStatus
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        $gnl = GeneralSettings::first();
        if($gnl->maintain ==1){
            return response()->json(['success' => false, 'message' => 'Currently Under Maintenance. Try Again Later!']);
        }

       if (auth()->user()){
            if (auth()->user()->status != "active") {
                return response()->json(['success' => false, 'message' => 'Your Account is currently Inactive/Blocked. Kindly Contact Support!']);
            }
            return $next($request);
            // if(auth()->user()->telegram_verified == 1)
            // {
            //     return $next($request);
            // }else{
            //     return response()->json(['success' => true, 'message' => 'Telegram Id Verification Required.']);
            // }

        }else{
            return response()->json(['success' => false, 'message' => 'Unauthorized. Login Required.'], 401);
        }
    }
}
