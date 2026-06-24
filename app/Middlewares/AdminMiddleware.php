<?php

namespace App\Middlewares;
use Whis\Http\Middleware;
use Whis\Http\Request;
use Whis\Http\Response;
use Closure;

class AdminMiddleware implements Middleware {
    public function handle(Request $request, Closure $next): Response {
        //code here

        return $next($request);
    }
}