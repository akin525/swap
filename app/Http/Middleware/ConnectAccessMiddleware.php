<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ConnectAccessMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $input = $request->all();

        $key = $request->bearerToken();

        if (!isset($key)) {
            return response()->json(['status' => false, 'message' => 'Connect Access Authorization is required'], 401);
        }

        // verifier
        $verifier = config('ck.auth_key');
        //check if verifier matches
        if($key != $verifier){
            return response()->json(['status' => false, 'message' => 'Invalid Authorization'], 401);
        }

        return $next($request);
    }
}
