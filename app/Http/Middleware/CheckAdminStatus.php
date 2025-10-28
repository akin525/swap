<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CheckAdminStatus
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
       if (auth()->guard('admin')->user()){
            if (auth()->guard('admin')->user()->status == 0) {
                return response()->json(['success' => false, 'message' => 'Your Account is currently Inactive. Kindly Contact Super Admin!']);
            }
            if (auth()->guard('admin')->user()->status == 2) {
                return response()->json(['success' => false, 'message' => 'Your account has been blocked. Kindly Contact Super Admin!']);
            }

            return $next($request);

        }else{
            return response()->json(['success' => false, 'message' => 'Unauthorized. Login Required.']);
        }
    }
}
