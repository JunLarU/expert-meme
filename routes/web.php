<?php

use App\Controllers\Api\ApiTokenController;
use App\Controllers\Admin\Dashboard;
use App\Controllers\Home;
use App\Controllers\Proyectos;
use App\Middlewares\AuthMiddleware;
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


GROUP('/admin', function () {
    GET('', [Dashboard::class, 'index']);

    /*
    |--------------------------------------------------------------------------
    | Admin: API Tokens
    |--------------------------------------------------------------------------
    |
    | Estas rutas son las del panel que hicimos:
    | - listado
    | - vista
    | - edición
    | - creación por ajax-form
    | - actualización por ajax-form
    | - revocación
    | - eliminación
    |
    */

    GET('/api-tokens', [ApiTokenController::class, 'index']);
    GET('/api-tokens/{id:\d+}', [ApiTokenController::class, 'show']);
    GET('/api-tokens/{id:\d+}/editar', [ApiTokenController::class, 'edit']);

    POST('/api-tokens', [ApiTokenController::class, 'store']);
    POST('/api-tokens/{id:\d+}/actualizar', [ApiTokenController::class, 'update']);
    POST('/api-tokens/{id:\d+}/revocar', [ApiTokenController::class, 'revoke']);
    POST('/api-tokens/{id:\d+}/eliminar', [ApiTokenController::class, 'destroy']);
}, [AuthMiddleware::class]);

GROUP('/site-api', function () {
    /*
    |--------------------------------------------------------------------------
    | Endpoints públicos de lectura
    |--------------------------------------------------------------------------
    |
    | No necesitan CSRF porque son GET.
    | Aun así, solo debes devolver datos públicos.
    |
    */

    GET('/projects', [SiteProjectController::class, 'index']);
    GET('/projects/{id:\d+}', [SiteProjectController::class, 'show']);
    GET('/latest-projects', [SiteProjectController::class, 'latest']);
    GET('/map/projects', [SiteProjectController::class, 'map']);

    /*
    |--------------------------------------------------------------------------
    | Endpoints de escritura desde el propio sitio
    |--------------------------------------------------------------------------
    |
    | Requieren:
    | - Same-origin
    | - CSRF
    |
    */

    POST('/contact/send', [SiteContactController::class, 'send']);

}, [SiteApiMiddleware::class]);

Route::get('/form',[Home::class,'store']);
//  Route::get('/{id:\d+}', function (int $id) {
//      return json(['id' => $id]);
//  });

Storage::Routes();