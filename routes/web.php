<?php
use App\Controllers\Home;
use App\Models\User;
use Whis\Auth\Auth;
use Whis\Http\Response;
use Whis\Routing\Route;
use Whis\Storage\Storage;


Auth::Routes();


CONTROLLER(Home::class,'',[
    'get' => [
        '' => 'create',
    ],

]);
//  Route::post('/form',[Home::class,'store']);
//  Route::get('/{id:\d+}', function (int $id) {
//      return json(['id' => $id]);
//  });

Storage::Routes();