<?php

use App\Models\User;
use Whis\Routing\Route;
use App\Controllers\Api\TokenController;
use App\Controllers\Api\ProjectApiController;
use App\Middlewares\ApiAbilityMiddleware;
use App\Middlewares\ApiTokenMiddleware;

// Route::get('/user/{user}', fn (User $user) => json($user->toArray()));