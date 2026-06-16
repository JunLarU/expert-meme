<?php

namespace App\Middlewares\extraDirectories;
use Whis\Http\Middleware;
use Whis\Http\Request;
use Whis\Http\Response;
use Closure;

class MiddlewareName implements Middleware {
    public function handle(Request $request, Closure $next): Response {
        //code here

        return $next($request);
    }
}