<?php

namespace App\Middlewares;

use Whis\Auth\Auth;
use Whis\Http\Middleware;
use Whis\Http\Request;
use Whis\Http\Response;
use Closure;

class AuthMiddleware implements Middleware {
    public function handle(Request $request, Closure $next): Response {
        if (Auth::isGuest()) {
            return redirect('/login');
        }

        return $next($request);
    }
}