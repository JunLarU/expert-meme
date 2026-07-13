<?php

use App\Controllers\Admin\AssociationCertificationSlides;
use App\Controllers\Admin\Clients;
use App\Controllers\Admin\Dashboard;
use App\Controllers\Admin\Jumbotron;
use App\Controllers\Admin\Messages;
use App\Controllers\Admin\OfficeWorkshops as AdminOfficeWorkshops;
use App\Controllers\Admin\Projects;
use App\Controllers\Admin\Search as AdminSearch;
use App\Controllers\Admin\Users;
use App\Controllers\Admin\ValuationClients;
use App\Controllers\Admin\ValuationMessages;
use App\Controllers\Admin\ValuationUnits;
use App\Controllers\Home;
use App\Controllers\Proyectos;
use App\Middlewares\AuthMiddleware;
use Whis\Auth\Auth;
use Whis\Routing\Route;
use Whis\Storage\Storage;

Auth::Routes();

CONTROLLER(Home::class, '', [
    'get'  => [
        ''                              => 'home',
        'nosotros'                      => 'nosotros',
        'proyectos'                     => 'proyectos',
        'servicio/estructura'           => 'servicios',
        'servicio/valuacion'            => 'valuacion',
        'contacto'                      => 'contacto',
        'site-api/projects'             => 'projectsJson',
        'site-api/map/projects'         => 'projectsMapJson',
        'site-api/map/office-workshops' => 'officeWorkshopsMapJson',
        //GET('/site-api/search/projects', [Home::class, 'searchProjectsJson']);
        'site-api/search/projects' => 'searchProjectsJson',
    ],
    'post' => [
        'contact/send'           => 'contactSend',
        'valuacion/contact/send' => 'valuationContactSend',
    ],

]);
GET('/proyecto/{id}', [Proyectos::class, 'entry']);

GROUP('/admin', function () {
    GET('', [Dashboard::class, 'index']);
    GET('/buscar', [AdminSearch::class, 'index']);
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

    GET('/mensajes', [Messages::class, 'index']);
    GET('/mensajes/{id:\d+}', [Messages::class, 'show']);
    GET('/mensajes/{id:\d+}/eliminar', [Messages::class, 'delete']);

    POST('/mensajes/{id:\d+}/actualizar', [Messages::class, 'update']);
    POST('/mensajes/{id:\d+}/leer', [Messages::class, 'markRead']);
    POST('/mensajes/{id:\d+}/seguimiento', [Messages::class, 'markInProgress']);
    POST('/mensajes/{id:\d+}/respondido', [Messages::class, 'markAnswered']);
    POST('/mensajes/{id:\d+}/archivar', [Messages::class, 'archive']);
    POST('/mensajes/{id:\d+}/spam', [Messages::class, 'spam']);
    POST('/mensajes/{id:\d+}/eliminar', [Messages::class, 'destroy']);

    GET('/valuacion/mensajes', [ValuationMessages::class, 'index']);
    GET('/valuacion/mensajes/{id:\d+}', [ValuationMessages::class, 'show']);
    GET('/valuacion/mensajes/{id:\d+}/eliminar', [ValuationMessages::class, 'delete']);

    POST('/valuacion/mensajes/{id:\d+}/actualizar', [ValuationMessages::class, 'update']);
    POST('/valuacion/mensajes/{id:\d+}/leer', [ValuationMessages::class, 'markRead']);
    POST('/valuacion/mensajes/{id:\d+}/seguimiento', [ValuationMessages::class, 'markInProgress']);
    POST('/valuacion/mensajes/{id:\d+}/respondido', [ValuationMessages::class, 'markAnswered']);
    POST('/valuacion/mensajes/{id:\d+}/archivar', [ValuationMessages::class, 'archive']);
    POST('/valuacion/mensajes/{id:\d+}/spam', [ValuationMessages::class, 'spam']);
    POST('/valuacion/mensajes/{id:\d+}/eliminar', [ValuationMessages::class, 'destroy']);


    GET('/usuarios', [Users::class, 'index']);
    GET('/usuarios/crear', [Users::class, 'create']);
    GET('/usuarios/{id:\d+}', [Users::class, 'edit']);
    GET('/usuarios/{id:\d+}/editar', [Users::class, 'edit']);
    GET('/usuarios/{id:\d+}/eliminar', [Users::class, 'delete']);

    POST('/usuarios', [Users::class, 'store']);
    POST('/usuarios/{id:\d+}/actualizar', [Users::class, 'update']);
    POST('/usuarios/{id:\d+}/eliminar', [Users::class, 'destroy']);

    GET('/perfil', [Users::class, 'profile']);

    POST('/perfil/actualizar', [Users::class, 'updateProfile']);
    POST('/perfil/password', [Users::class, 'updatePassword']);

    GET('/oficinas-talleres', [AdminOfficeWorkshops::class, 'index']);
    GET('/oficinas-talleres/crear', [AdminOfficeWorkshops::class, 'create']);
    GET('/oficinas-talleres/{id:\d+}', [AdminOfficeWorkshops::class, 'edit']);
    GET('/oficinas-talleres/{id:\d+}/editar', [AdminOfficeWorkshops::class, 'edit']);
    GET('/oficinas-talleres/{id:\d+}/eliminar', [AdminOfficeWorkshops::class, 'delete']);

    POST('/oficinas-talleres', [AdminOfficeWorkshops::class, 'store']);
    POST('/oficinas-talleres/{id:\d+}/actualizar', [AdminOfficeWorkshops::class, 'update']);
    POST('/oficinas-talleres/{id:\d+}/eliminar', [AdminOfficeWorkshops::class, 'destroy']);

    GET('/asociaciones-certificaciones', [AssociationCertificationSlides::class, 'index']);
    GET('/asociaciones-certificaciones/crear', [AssociationCertificationSlides::class, 'create']);
    GET('/asociaciones-certificaciones/{id:\d+}', [AssociationCertificationSlides::class, 'edit']);
    GET('/asociaciones-certificaciones/{id:\d+}/editar', [AssociationCertificationSlides::class, 'edit']);
    GET('/asociaciones-certificaciones/{id:\d+}/eliminar', [AssociationCertificationSlides::class, 'delete']);

    POST('/asociaciones-certificaciones', [AssociationCertificationSlides::class, 'store']);
    POST('/asociaciones-certificaciones/{id:\d+}/actualizar', [AssociationCertificationSlides::class, 'update']);
    POST('/asociaciones-certificaciones/{id:\d+}/eliminar', [AssociationCertificationSlides::class, 'destroy']);
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
Route::controller(ValuationUnits::class, '/admin/valuacion/unidades', function () {
    get('', 'index');
    get('/crear', 'create');
    post('', 'store');
    get('/{id:\\d+}/editar', 'edit');
    post('/{id:\\d+}/actualizar', 'update');
    get('/{id:\\d+}/eliminar', 'delete');
    post('/{id:\\d+}/eliminar', 'destroy');
}, [AuthMiddleware::class]);

Route::controller(ValuationClients::class, '/admin/valuacion/clientes', function () {
    get('', 'index');
    get('/crear', 'create');
    post('', 'store');
    get('/{id:\\d+}/editar', 'edit');
    post('/{id:\\d+}/actualizar', 'update');
    get('/{id:\\d+}/eliminar', 'delete');
    post('/{id:\\d+}/eliminar', 'destroy');
}, [AuthMiddleware::class]);
Route::get('/form', [Home::class, 'store']);
//  Route::get('/{id:\d+}', function (int $id) {
//      return json(['id' => $id]);
//  });

Storage::Routes();
