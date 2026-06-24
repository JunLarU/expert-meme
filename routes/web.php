<?php

use App\Controllers\Admin\Clients;
use App\Controllers\Admin\Dashboard;
use App\Controllers\Admin\Jumbotron;
use App\Controllers\Admin\Projects;
use App\Controllers\Api\ApiTokenController;
use App\Controllers\Home;
use App\Controllers\Proyectos;
use App\Middlewares\AuthMiddleware;
use Whis\Auth\Auth;
use Whis\Routing\Route;
use Whis\Storage\Storage;

Auth::Routes();

CONTROLLER(Home::class, '', [
    'get'  => [
        ''          => 'home',
        'nosotros'  => 'nosotros',
        'proyectos' => 'proyectos',
        'servicios' => 'servicios',
        'contacto'  => 'contacto',
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
    GET('/jumbotron', [Jumbotron::class, 'index']);
    GET('/jumbotron/crear', [Jumbotron::class, 'create']);
    GET('/jumbotron/{id:\d+}', [Jumbotron::class, 'edit']);
    GET('/jumbotron/{id:\d+}/editar', [Jumbotron::class, 'edit']);
    GET('/jumbotron/{id:\d+}/eliminar', [Jumbotron::class, 'delete']);

    POST('/jumbotron', [Jumbotron::class, 'store']);
    POST('/jumbotron/{id:\d+}/actualizar', [Jumbotron::class, 'update']);
    POST('/jumbotron/{id:\d+}/eliminar', [Jumbotron::class, 'destroy']);

    // Dentro del GROUP('/admin', ...):
    GET('/clientes', [Clients::class, 'index']);
    GET('/clientes/crear', [Clients::class, 'create']);
    GET('/clientes/{id:\d+}', [Clients::class, 'edit']);
    GET('/clientes/{id:\d+}/editar', [Clients::class, 'edit']);
    GET('/clientes/{id:\d+}/eliminar', [Clients::class, 'delete']);

    POST('/clientes', [Clients::class, 'store']);
    POST('/clientes/{id:\d+}/actualizar', [Clients::class, 'update']);
    POST('/clientes/{id:\d+}/eliminar', [Clients::class, 'destroy']);

    GET('/proyectos', [Projects::class, 'index']);
    GET('/proyectos/crear', [Projects::class, 'create']);
    GET('/proyectos/{id:\d+}', [Projects::class, 'edit']);
    GET('/proyectos/{id:\d+}/editar', [Projects::class, 'edit']);
    GET('/proyectos/{id:\d+}/eliminar', [Projects::class, 'delete']);

    POST('/proyectos', [Projects::class, 'store']);
    POST('/proyectos/{id:\d+}/actualizar', [Projects::class, 'update']);
    POST('/proyectos/{id:\d+}/eliminar', [Projects::class, 'destroy']);
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

// GROUP('/site-api', function () {
//     /*
//     |--------------------------------------------------------------------------
//     | Endpoints públicos de lectura
//     |--------------------------------------------------------------------------
//     |
//     | No necesitan CSRF porque son GET.
//     | Aun así, solo debes devolver datos públicos.
//     |
//     */

//     GET('/projects', [SiteProjectController::class, 'index']);
//     GET('/projects/{id:\d+}', [SiteProjectController::class, 'show']);
//     GET('/latest-projects', [SiteProjectController::class, 'latest']);
//     GET('/map/projects', [SiteProjectController::class, 'map']);

//     /*
//     |--------------------------------------------------------------------------
//     | Endpoints de escritura desde el propio sitio
//     |--------------------------------------------------------------------------
//     |
//     | Requieren:
//     | - Same-origin
//     | - CSRF
//     |
//     */

//     POST('/contact/send', [SiteContactController::class, 'send']);

// }, [SiteApiMiddleware::class]);

Route::get('/form', [Home::class, 'store']);
//  Route::get('/{id:\d+}', function (int $id) {
//      return json(['id' => $id]);
//  });

Storage::Routes();
