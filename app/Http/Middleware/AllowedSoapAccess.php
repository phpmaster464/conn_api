<?php

namespace App\Http\Middleware;

use Closure, SoapFault, Log;
use Illuminate\Contracts\Auth\Guard;

class AllowedSoapAccess
{
    /**
     * Create a new filter instance.
     *
     * @param  Guard  $auth
     * @return void
     */


    private $allow_ips = [];

    public function __construct()
    {
        $this->allow_ips = ['101.189.98.151', '13.211.253.185', '54.206.22.161', '123.51.118.166', '123.51.107.206', '13.211.26.155', '10.0.0.138', '10.0.0.4', '10.0.0.2', '10.0.0.93'];
    }

    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
       $debug = config('app.debug');
       //if(!$debug && !in_array($request->ip(), $this->allow_ips) ) { Log::info($request->ip()); return redirect( '/' ); }
       return $next($request);
    }
}
