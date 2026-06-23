<?php
use App\Controllers\Home;
use App\Controllers\Proyectos;
use App\Models\User;
use Whis\Auth\Auth;
use Whis\Http\Response;
use Whis\Routing\Route;
use Whis\Storage\Storage;


Auth::Routes();


CONTROLLER(Home::class,'',[
    'get' => [
        '' => 'home',
        'nosotros' => 'nosotros',
        'proyectos' => 'proyectos',
        'servicios' => 'servicios',
        'contacto' => 'contacto',
    ],
    'post' => [
        'contact/send' => 'contactSend',
    ],

]);
CONTROLLER(Proyectos::class, 'proyecto', [
    'get' => [
        '{id:.+}' => 'entry',
    ],
]);
Route::get('/form',[Home::class,'store']);
//  Route::get('/{id:\d+}', function (int $id) {
//      return json(['id' => $id]);
//  });

Storage::Routes();