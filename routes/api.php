<?php

use App\Models\User;
use Whis\Routing\Route;

Route::get('/user/{user}', fn (User $user) => json($user->toArray()));