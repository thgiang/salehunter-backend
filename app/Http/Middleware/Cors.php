<?php

namespace App\Http\Middleware;

use Closure;

class Cors
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        $allowedOrigins = [
            'http://localhost:3000',
            'http://127.0.0.1:3000',
'http://salehunter.net',
'http://192.168.1.65',
            'https://salehunter.net'
        ];

        if($request->server('HTTP_ORIGIN')){
            if (in_array($request->server('HTTP_ORIGIN'), $allowedOrigins)) {
                return $next($request)
                    ->header('Access-Control-Allow-Origin', $request->server('HTTP_ORIGIN'))
                    ->header('Access-Control-Allow-Credentials', 'true')
                    ->header('Access-Control-Allow-Methods', 'POST, GET, OPTIONS, PUT, DELETE, HEAD')
                    ->header('Access-Control-Allow-Headers', 'Origin, Content-Type, Authorization, X-Requested-With, X-Auth-Token, phone, ftoken, fullname');
            }
        }

        return $next($request);
    }
}
