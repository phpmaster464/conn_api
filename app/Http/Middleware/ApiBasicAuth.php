<?php

namespace App\Http\Middleware;

use Closure, SoapFault;
use Illuminate\Contracts\Auth\Guard;

class ApiBasicAuth
{
    /**
     * The Guard implementation.
     *
     * @var Guard
     */
    protected $auth;

    /**
     * Create a new filter instance.
     *
     * @param  Guard  $auth
     * @return void
     */
    public function __construct(Guard $auth)
    {
        $this->auth = $auth;
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
        if(isset($_SERVER['PHP_AUTH_USER']) && isset($_SERVER['PHP_AUTH_PW'])) if($this->auth->attempt(['username' => $_SERVER['PHP_AUTH_USER'], 'password' => $_SERVER['PHP_AUTH_PW']])) return $next($request);
        
        $message = 'You are not authorised to view this content';

        //return new SoapFault('Server', $message, null, array('message'=>$message), 'NotAuthorisedException');
         return $next($request);
    }
}
