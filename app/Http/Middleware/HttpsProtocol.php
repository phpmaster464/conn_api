<?php
namespace App\Http\Middleware;

use Closure;

class HttpsProtocol {

    public function handle($request, Closure $next)
    {
    	$debug = config('app.debug');

        if (!$request->secure() && !$debug) {
            //return redirect()->secure($request->getRequestUri());
        }

        return $next($request); 
    }
}