<?php

namespace App\Http\Middleware;

use Illuminate\Auth\Middleware\Authenticate as Middleware;
use Illuminate\Http\Request;
use Auth;
use App\Models\User;
use Carbon\Carbon;

class Authenticate extends Middleware
{
    /**
     * Get the path the user should be redirected to when they are not authenticated.
     */



    protected function authenticate($request, array $guards)
    {
    if (empty($guards)) {
    $guards = [null];
    }

    foreach ($guards as $guard) {
        if ($this->auth->guard($guard)->check()) {
        // add the code here
            if (auth()->user()) {
            $user = User::whereId(auth()->user()->id)->update(['online'=> Carbon::now()]);
            }
        return $this->auth->shouldUse($guard);
        }else{
            return response()->json(['message' => 'Unauthorized. Login Required.'], 401);
        }
    }

    $this->unauthenticated($request, $guards);
    }
    
    protected function redirectTo($request)
    {
        if (! $request->expectsJson()) {
            return response()->json(['message' => 'Unauthorized. Login Required.'], 401);
        }
    }
}
