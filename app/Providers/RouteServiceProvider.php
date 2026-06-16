<?php

namespace App\Providers;

use Whis\App;
use Whis\Providers\ServiceProvider;
use Whis\Routing\Route;

class RouteServiceProvider implements ServiceProvider
{
    public function registerServices(){
        Route::load(App::$root . "/routes");
    }
}
