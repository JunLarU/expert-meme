<?php

use Whis\Routing\Route;

function GET(string|array $uri, \Closure|array|null $action = null): Route|array
{
    return Route::get($uri, $action);
}

function POST(string|array $uri, \Closure|array|null $action = null): Route|array
{
    return Route::post($uri, $action);
}

function PUT(string|array $uri, \Closure|array|null $action = null): Route|array
{
    return Route::put($uri, $action);
}

function PATCH(string|array $uri, \Closure|array|null $action = null): Route|array
{
    return Route::patch($uri, $action);
}

function DELETE(string|array $uri, \Closure|array|null $action = null): Route|array
{
    return Route::delete($uri, $action);
}

function GROUP(string $prefix, \Closure $callback, array|string $middlewares = []): void
{
    Route::group($prefix, $callback, $middlewares);
}