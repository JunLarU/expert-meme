# Documentación de rutas en Whis

Este documento muestra las formas permitidas para declarar rutas en Whis usando:

* `Route::get()`, `Route::post()`, `Route::put()`, `Route::patch()`, `Route::delete()`
* Helpers globales: `GET()`, `POST()`, `PUT()`, `PATCH()`, `DELETE()`
* `Route::group()` y `GROUP()`
* `Route::controller()` y `CONTROLLER()`
* Rutas individuales
* Rutas en lote
* Rutas con middlewares
* Rutas con parámetros
* Rutas con regex
* Rutas con closures
* Rutas hacia controladores
* Grupos anidados
* Controller groups
* Rutas de archivos
* Storage routes

---

# 1. Estructura básica de `routes/web.php`

```php
<?php

use App\Controllers\Home;
use App\Controllers\Dashboard;
use App\Controllers\UserController;
use App\Controllers\AdminController;
use App\Controllers\FileController;

use App\Middlewares\AuthMiddleware;
use App\Middlewares\AdminMiddleware;
use App\Middlewares\GuestMiddleware;
use App\Middlewares\ApiMiddleware;

use Whis\Auth\Auth;
use Whis\Http\Request;
use Whis\Routing\Route;
use Whis\Storage\Storage;

/*
|--------------------------------------------------------------------------
| Rutas de autenticación del framework
|--------------------------------------------------------------------------
*/

Auth::Routes();

/*
|--------------------------------------------------------------------------
| Tus rutas aquí
|--------------------------------------------------------------------------
*/

GET('', [Home::class, 'create']);

POST('', [Home::class, 'store']);

Route::get('/form', function () {
    return view('form');
});

/*
|--------------------------------------------------------------------------
| Storage genérico del framework
|--------------------------------------------------------------------------
|
| Debe ir al final para que no se coma rutas específicas.
|
*/

Storage::Routes();
```

---

# 2. Rutas individuales con `Route::get()`, `Route::post()`, etc.

## GET básico hacia controlador

```php
Route::get('', [Home::class, 'create']);

Route::get('/home', [Home::class, 'home']);

Route::get('/nosotros', [Home::class, 'about']);
```

---

## POST básico hacia controlador

```php
Route::post('', [Home::class, 'store']);

Route::post('/contact/send', [Home::class, 'contactSend']);

Route::post('/newsletter', [Home::class, 'newsletter']);
```

---

## PUT básico

```php
Route::put('/users/{id:\d+}', [UserController::class, 'update']);
```

---

## PATCH básico

```php
Route::patch('/users/{id:\d+}/status', [UserController::class, 'updateStatus']);
```

---

## DELETE básico

```php
Route::delete('/users/{id:\d+}', [UserController::class, 'delete']);
```

---

# 3. Rutas individuales con helpers globales

Los helpers globales son equivalentes a `Route::get()`, `Route::post()`, etc.

```php
GET('', [Home::class, 'create']);

POST('', [Home::class, 'store']);

PUT('/users/{id:\d+}', [UserController::class, 'update']);

PATCH('/users/{id:\d+}/status', [UserController::class, 'updateStatus']);

DELETE('/users/{id:\d+}', [UserController::class, 'delete']);
```

También puedes escribirlos en minúscula porque PHP no distingue mayúsculas/minúsculas en nombres de funciones:

```php
get('', [Home::class, 'create']);

post('', [Home::class, 'store']);

put('/users/{id:\d+}', [UserController::class, 'update']);

patch('/users/{id:\d+}/status', [UserController::class, 'updateStatus']);

delete('/users/{id:\d+}', [UserController::class, 'delete']);
```

---

# 4. Rutas con closures

Las rutas con closures se usan para respuestas rápidas, vistas simples, pruebas o endpoints pequeños.

```php
Route::get('/form', function () {
    return view('form');
});

Route::get('/about', function () {
    return view('about', 'Acerca de');
});

Route::get('/ping', function () {
    return json([
        'ok' => true,
    ]);
});
```

Con helpers:

```php
GET('/form', function () {
    return view('form');
});

GET('/ping', function () {
    return json([
        'ok' => true,
    ]);
});
```

---

# 5. Rutas con parámetros normales

```php
Route::get('/users/{id}', [UserController::class, 'show']);

Route::get('/posts/{slug}', function (string $slug) {
    return json([
        'slug' => $slug,
    ]);
});
```

Ejemplos de URLs válidas:

```txt
/users/10
/users/juan
/posts/mi-primer-post
```

---

# 6. Rutas con parámetros usando regex

```php
Route::get('/users/{id:\d+}', [UserController::class, 'show']);
```

Acepta:

```txt
/users/10
/users/999
```

Rechaza:

```txt
/users/juan
/users/abc
```

---

## Regex para año y mes

```php
Route::get('/reports/{year:\d{4}}/{month:\d{1,2}}', function (int $year, int $month) {
    return json([
        'year' => $year,
        'month' => $month,
    ]);
});
```

Acepta:

```txt
/reports/2026/6
/reports/2026/12
```

---

## Regex para slugs

```php
Route::get('/blog/{slug:[a-z0-9\-]+}', function (string $slug) {
    return json([
        'slug' => $slug,
    ]);
});
```

Acepta:

```txt
/blog/hola-mundo
/blog/post-2026
```

---

## Regex catch-all controlado

```php
Route::get('/pages/{slug:.*}', function (string $slug) {
    return json([
        'page' => $slug,
    ]);
});
```

Acepta:

```txt
/pages/nosotros
/pages/docs/instalacion
/pages/docs/framework/routing
```

> Las rutas catch-all deben ir cerca del final para no interceptar rutas más específicas.

---

# 7. Query variables

Las query variables no se declaran en la ruta.

URL:

```txt
/search?q=whis&page=2
```

Ruta:

```php
Route::get('/search', function (Request $request) {
    return json([
        'q' => $request->query('q'),
        'page' => $request->query('page'),
    ]);
});
```

---

# 8. Middlewares en rutas individuales

## Middleware usando encadenamiento

```php
Route::get('/profile', [UserController::class, 'profile'])
    ->middleware([AuthMiddleware::class]);
```

---

## Varios middlewares

```php
Route::get('/admin', [AdminController::class, 'dashboard'])
    ->middleware([AuthMiddleware::class, AdminMiddleware::class]);
```

---

## Middleware con helper global

```php
GET('/profile', [UserController::class, 'profile'], [AuthMiddleware::class]);

POST('/profile/update', [UserController::class, 'update'], [AuthMiddleware::class]);

GET('/admin', [AdminController::class, 'dashboard'], [
    AuthMiddleware::class,
    AdminMiddleware::class,
]);
```

---

# 9. Rutas en lote con array asociativo

## GET en lote

```php
Route::get([
    '/form' => function () {
        return view('form', 'Formulario');
    },

    '/about' => function () {
        return view('about', 'Acerca de');
    },

    '/nosotros' => [Home::class, 'about'],
]);
```

---

## POST en lote

```php
Route::post([
    '/newsletter' => [Home::class, 'newsletter'],

    '/contact/send' => [Home::class, 'contactSend'],
]);
```

---

## PUT en lote

```php
Route::put([
    '/users/{id:\d+}' => [UserController::class, 'update'],

    '/profile' => [UserController::class, 'updateProfile'],
]);
```

---

## PATCH en lote

```php
Route::patch([
    '/users/{id:\d+}/status' => [UserController::class, 'updateStatus'],

    '/settings/theme' => [UserController::class, 'updateTheme'],
]);
```

---

## DELETE en lote

```php
Route::delete([
    '/users/{id:\d+}' => [UserController::class, 'delete'],

    '/posts/{id:\d+}' => [UserController::class, 'deletePost'],
]);
```

---

# 10. Rutas en lote usando pares

También se puede declarar una lista alternando `uri`, `action`, `uri`, `action`.

```php
Route::get([
    '/page-one',
    function () {
        return view('page-one', 'Página uno');
    },

    '/page-two',
    function () {
        return view('page-two', 'Página dos');
    },

    '/page-three',
    [Home::class, 'pageThree'],
]);
```

---

# 11. Rutas en lote con middleware

Como `Route::get([...])` devuelve un array de rutas, se pueden recorrer.

```php
$routes = Route::get([
    '/admin/info' => [AdminController::class, 'info'],

    '/admin/help' => [AdminController::class, 'help'],
]);

foreach ($routes as $route) {
    $route->middleware([AuthMiddleware::class, AdminMiddleware::class]);
}
```

Con helper global:

```php
GET([
    '/admin/info' => [AdminController::class, 'info'],

    '/admin/help' => [AdminController::class, 'help'],
], null, [AuthMiddleware::class, AdminMiddleware::class]);
```

---

# 12. `Route::group()` con callback

`Route::group()` sirve para agrupar cualquier tipo de ruta.

Puede contener:

* Rutas con closure
* Rutas hacia controladores
* Rutas en lote
* Otros grupos
* `Route::controller()`
* Middlewares globales
* Middlewares individuales

---

## Grupo simple con prefijo

```php
Route::group('/dashboard', function () {
    Route::get('', [Dashboard::class, 'create']);

    Route::get('/users', [Dashboard::class, 'users']);

    Route::post('/users', [Dashboard::class, 'storeUser']);
});
```

Resultado:

```txt
GET  /dashboard
GET  /dashboard/users
POST /dashboard/users
```

---

## Grupo con middleware

```php
Route::group('/dashboard', function () {
    Route::get('', [Dashboard::class, 'create']);

    Route::get('/users', [Dashboard::class, 'users']);

    Route::post('/users', [Dashboard::class, 'storeUser']);
}, [AuthMiddleware::class]);
```

Todas las rutas tienen `AuthMiddleware`.

---

## Grupo con varios middlewares

```php
Route::group('/admin', function () {
    Route::get('', [AdminController::class, 'dashboard']);

    Route::get('/users', [AdminController::class, 'users']);

    Route::post('/users', [AdminController::class, 'storeUser']);
}, [AuthMiddleware::class, AdminMiddleware::class]);
```

---

## Grupo usando helpers

```php
GROUP('/dashboard', function () {
    GET('', [Dashboard::class, 'create']);

    GET('/users', [Dashboard::class, 'users']);

    POST('/users', [Dashboard::class, 'storeUser']);
}, [AuthMiddleware::class]);
```

---

## Grupo con closure

```php
Route::group('/web', function () {
    GET('/form', function () {
        return view('form');
    });

    GET('/about', function () {
        return view('about', 'Acerca de');
    });
});
```

---

## Grupo con rutas de distintos controladores

```php
Route::group('/dashboard', function () {
    GET('', [Dashboard::class, 'create']);

    GET('/profile', [UserController::class, 'profile']);

    GET('/admin', [AdminController::class, 'dashboard'])
        ->middleware([AdminMiddleware::class]);

    POST('/profile/update', [UserController::class, 'update']);
}, [AuthMiddleware::class]);
```

---

# 13. Grupos anidados

```php
Route::group('/dashboard', function () {
    GET('', [Dashboard::class, 'create']);

    GET('/profile', [Dashboard::class, 'profile']);

    Route::group('/admin', function () {
        GET('', [AdminController::class, 'dashboard']);

        GET('/users', [AdminController::class, 'users']);

        GET('/users/{id:\d+}', [AdminController::class, 'showUser']);

        POST('/users/{id:\d+}/delete', [AdminController::class, 'deleteUser']);
    }, [AdminMiddleware::class]);
}, [AuthMiddleware::class]);
```

Resultado:

```txt
GET  /dashboard
GET  /dashboard/profile
GET  /dashboard/admin
GET  /dashboard/admin/users
GET  /dashboard/admin/users/{id}
POST /dashboard/admin/users/{id}/delete
```

Middlewares:

```txt
/dashboard/*       => AuthMiddleware
/dashboard/admin/* => AuthMiddleware + AdminMiddleware
```

---

# 14. Grupo sin prefijo, solo middleware

Útil cuando quieres proteger varias rutas sin cambiar sus URLs.

```php
Route::group('', function () {
    GET('/orders', [UserController::class, 'orders']);

    GET('/orders/{id:\d+}', [UserController::class, 'showOrder']);

    POST('/orders/{id:\d+}/cancel', [UserController::class, 'cancelOrder']);
}, [AuthMiddleware::class]);
```

---

# 15. `Route::group()` con array declarativo

Además del callback, `group()` puede aceptar un array.

```php
Route::group('/dashboard', [
    'get' => [
        '' => [Dashboard::class, 'create'],

        '/profile' => [UserController::class, 'profile'],

        '/stats' => [Dashboard::class, 'stats'],

        '/form' => function () {
            return view('form');
        },
    ],

    'post' => [
        '/profile/update' => [UserController::class, 'update'],
    ],
], [AuthMiddleware::class]);
```

Resultado:

```txt
GET  /dashboard
GET  /dashboard/profile
GET  /dashboard/stats
GET  /dashboard/form
POST /dashboard/profile/update
```

---

## Grupo declarativo con middleware individual por ruta

```php
Route::group('/dashboard', [
    'get' => [
        '' => [Dashboard::class, 'create'],

        '/admin' => [
            'controller' => AdminController::class,
            'method' => 'dashboard',
            'middlewares' => [AdminMiddleware::class],
        ],

        '/profile' => [
            'action' => [UserController::class, 'profile'],
            'middlewares' => [AuthMiddleware::class],
        ],
    ],

    'post' => [
        '/admin/save' => [
            'controller' => AdminController::class,
            'method' => 'save',
            'middlewares' => [AdminMiddleware::class],
        ],
    ],
], [AuthMiddleware::class]);
```

---

## Grupo declarativo usando `action`

```php
Route::group('/account', [
    'get' => [
        '' => [
            'action' => [UserController::class, 'account'],
        ],

        '/settings' => [
            'action' => [UserController::class, 'settings'],
            'middlewares' => [AuthMiddleware::class],
        ],
    ],
]);
```

---

## Grupo declarativo usando `controller` + `method`

```php
Route::group('/account', [
    'get' => [
        '' => [
            'controller' => UserController::class,
            'method' => 'account',
        ],

        '/settings' => [
            'controller' => UserController::class,
            'method' => 'settings',
            'middlewares' => [AuthMiddleware::class],
        ],
    ],

    'post' => [
        '/settings' => [
            'controller' => UserController::class,
            'method' => 'updateSettings',
        ],
    ],
]);
```

---

## Grupo declarativo con closures

```php
Route::group('/web', [
    'get' => [
        '/form' => function () {
            return view('form');
        },

        '/about' => function () {
            return view('about', 'Acerca de');
        },

        '/ping' => function () {
            return json([
                'ok' => true,
            ]);
        },
    ],
]);
```

---

# 16. `GROUP()` con array declarativo

El helper `GROUP()` funciona igual que `Route::group()`.

```php
GROUP('/dashboard', [
    'get' => [
        '' => [Dashboard::class, 'create'],

        '/profile' => [UserController::class, 'profile'],

        '/form' => function () {
            return view('form');
        },
    ],

    'post' => [
        '/profile/update' => [UserController::class, 'update'],
    ],
], [AuthMiddleware::class]);
```

---

# 17. Grupos declarativos anidados

```php
Route::group('/dashboard', [
    'get' => [
        '' => [Dashboard::class, 'create'],

        '/profile' => [Dashboard::class, 'profile'],
    ],

    'groups' => [
        '/admin' => [
            'middlewares' => [AdminMiddleware::class],

            'routes' => [
                'get' => [
                    '' => [AdminController::class, 'dashboard'],

                    '/users' => [AdminController::class, 'users'],

                    '/users/{id:\d+}' => [AdminController::class, 'showUser'],
                ],

                'post' => [
                    '/users/{id:\d+}/delete' => [
                        'controller' => AdminController::class,
                        'method' => 'deleteUser',
                    ],
                ],
            ],
        ],
    ],
], [AuthMiddleware::class]);
```

Resultado:

```txt
GET  /dashboard
GET  /dashboard/profile
GET  /dashboard/admin
GET  /dashboard/admin/users
GET  /dashboard/admin/users/{id}
POST /dashboard/admin/users/{id}/delete
```

---

# 18. `Route::controller()`

`Route::controller()` agrupa varias rutas que apuntan a métodos de un mismo controlador.

No debe usarse para closures. Para closures usa `Route::group()` o `Route::get()`.

---

## Controller con callback y helpers

```php
Route::controller(Home::class, function () {
    GET('', 'create');

    GET('/home', 'home');

    GET('/nosotros', 'about');

    GET('/servicios', 'services');

    GET('/contacto', 'contact');

    POST('/contacto/enviar', 'contactSend');
});
```

También puede escribirse en minúsculas:

```php
Route::controller(Home::class, function () {
    get('', 'create');

    get('/home', 'home');

    get('/nosotros', 'about');

    get('/servicios', 'services');

    get('/contacto', 'contact');

    post('/contacto/enviar', 'contactSend');
});
```

Equivale a:

```php
Route::get('', [Home::class, 'create']);

Route::get('/home', [Home::class, 'home']);

Route::get('/nosotros', [Home::class, 'about']);

Route::get('/servicios', [Home::class, 'services']);

Route::get('/contacto', [Home::class, 'contact']);

Route::post('/contacto/enviar', [Home::class, 'contactSend']);
```

---

## Controller con prefijo

```php
Route::controller(Dashboard::class, '/dashboard', function () {
    get('', 'create');

    get('/profile', 'profile');

    get('/users', 'users');

    get('/users/{id:\d+}', 'showUser');

    post('/users', 'storeUser');
});
```

Resultado:

```txt
GET  /dashboard
GET  /dashboard/profile
GET  /dashboard/users
GET  /dashboard/users/{id}
POST /dashboard/users
```

---

## Controller con prefijo y middleware global

```php
Route::controller(Dashboard::class, '/dashboard', function () {
    get('', 'create');

    get('/profile', 'profile');

    get('/users', 'users');

    get('/users/{id:\d+}', 'showUser');

    post('/users', 'storeUser');
}, [AuthMiddleware::class]);
```

Todas las rutas tienen `AuthMiddleware`.

---

## Controller con middleware individual

```php
Route::controller(Dashboard::class, '/dashboard', function () {
    get('', 'create');

    get('/profile', 'profile');

    get('/admin-only', 'adminOnly', [AdminMiddleware::class]);

    post('/admin/save', 'adminSave', [AdminMiddleware::class]);
}, [AuthMiddleware::class]);
```

Resultado:

```txt
/dashboard/admin-only => AuthMiddleware + AdminMiddleware
/dashboard/admin/save => AuthMiddleware + AdminMiddleware
```

---

## Controller con variable `$route`

```php
Route::controller(Dashboard::class, '/dashboard', function ($route) {
    $route->get('', 'create');

    $route->get('/profile', 'profile');

    $route->get('/users', 'users');

    $route->get('/users/{id:\d+}', 'showUser');

    $route->post('/users', 'storeUser');

    $route->get('/admin-only', 'adminOnly', [AdminMiddleware::class]);

    $route->post('/admin/save', 'adminSave', [AdminMiddleware::class]);
}, [AuthMiddleware::class]);
```

---

## Controller con array declarativo

```php
Route::controller(Dashboard::class, '/dashboard', [
    'get' => [
        '' => 'create',

        '/profile' => 'profile',

        '/users' => 'users',

        '/users/{id:\d+}' => 'showUser',

        '/reports/{year:\d{4}}/{month:\d{1,2}}' => 'reports',

        '/admin-only' => [
            'method' => 'adminOnly',
            'middlewares' => [AdminMiddleware::class],
        ],
    ],

    'post' => [
        '/users' => 'storeUser',

        '/admin/save' => [
            'method' => 'adminSave',
            'middlewares' => [AdminMiddleware::class],
        ],
    ],
], [AuthMiddleware::class]);
```

---

## Controller sin prefijo usando array

```php
Route::controller(Home::class, [
    'get' => [
        '' => 'create',

        '/home' => 'home',

        '/nosotros' => 'about',

        '/servicios' => 'services',

        '/contacto' => 'contact',
    ],

    'post' => [
        '/contacto/enviar' => 'contactSend',
    ],
]);
```

---

# 19. `CONTROLLER()` helper

El helper `CONTROLLER()` funciona igual que `Route::controller()`.

---

## Controller helper con callback

```php
CONTROLLER(Home::class, function () {
    get('', 'create');

    get('/home', 'home');

    get('/nosotros', 'about');

    post('/contacto/enviar', 'contactSend');
});
```

---

## Controller helper con prefijo

```php
CONTROLLER(Dashboard::class, '/dashboard', function () {
    get('', 'create');

    get('/profile', 'profile');

    get('/users', 'users');

    post('/users', 'storeUser');
}, [AuthMiddleware::class]);
```

---

## Controller helper con `$route`

```php
CONTROLLER(Dashboard::class, '/dashboard', function ($route) {
    $route->get('', 'create');

    $route->get('/profile', 'profile');

    $route->post('/users', 'storeUser');
}, [AuthMiddleware::class]);
```

---

## Controller helper con array

```php
CONTROLLER(Dashboard::class, '/dashboard', [
    'get' => [
        '' => 'create',

        '/profile' => 'profile',

        '/users' => 'users',
    ],

    'post' => [
        '/users' => 'storeUser',
    ],
], [AuthMiddleware::class]);
```

---

# 20. `Route::controller()` dentro de `Route::group()`

```php
Route::group('/app', function () {
    Route::controller(Dashboard::class, '/dashboard', function () {
        get('', 'create');

        get('/stats', 'stats');

        post('/stats/save', 'saveStats');
    });

    Route::controller(UserController::class, '/users', [
        'get' => [
            '' => 'index',

            '/{id:\d+}' => 'show',
        ],

        'post' => [
            '' => 'store',
        ],
    ]);
}, [AuthMiddleware::class]);
```

Resultado:

```txt
GET  /app/dashboard
GET  /app/dashboard/stats
POST /app/dashboard/stats/save

GET  /app/users
GET  /app/users/{id}
POST /app/users
```

Todas heredan `AuthMiddleware`.

---

# 21. Controller groups dentro de group declarativo

```php
Route::group('/app', [
    'controllers' => [
        Dashboard::class => [
            'prefix' => '/dashboard',

            'get' => [
                '' => 'create',

                '/stats' => 'stats',

                '/reports' => 'reports',
            ],

            'post' => [
                '/reports/generate' => 'generateReport',
            ],
        ],

        UserController::class => [
            'prefix' => '/users',

            'get' => [
                '' => 'index',

                '/{id:\d+}' => 'show',
            ],

            'post' => [
                '' => 'store',
            ],
        ],
    ],
], [AuthMiddleware::class]);
```

Resultado:

```txt
GET  /app/dashboard
GET  /app/dashboard/stats
GET  /app/dashboard/reports
POST /app/dashboard/reports/generate

GET  /app/users
GET  /app/users/{id}
POST /app/users
```

---

## Controller groups usando `routes`

```php
Route::group('/app', [
    'controllers' => [
        Dashboard::class => [
            'prefix' => '/dashboard',

            'routes' => [
                'get' => [
                    '' => 'create',

                    '/stats' => 'stats',

                    '/reports' => 'reports',
                ],

                'post' => [
                    '/reports/generate' => 'generateReport',
                ],
            ],
        ],
    ],
], [AuthMiddleware::class]);
```

---

## Controller groups con middleware individual

```php
Route::group('/app', [
    'controllers' => [
        Dashboard::class => [
            'prefix' => '/dashboard',

            'get' => [
                '' => 'create',

                '/admin-only' => [
                    'method' => 'adminOnly',
                    'middlewares' => [AdminMiddleware::class],
                ],
            ],

            'post' => [
                '/admin/save' => [
                    'method' => 'adminSave',
                    'middlewares' => [AdminMiddleware::class],
                ],
            ],
        ],
    ],
], [AuthMiddleware::class]);
```

---

# 22. Mezcla completa de group

```php
Route::group('/app', [
    'get' => [
        '/form' => function () {
            return view('form');
        },

        '/home' => [Home::class, 'create'],

        '/about' => [
            'controller' => Home::class,
            'method' => 'about',
        ],
    ],

    'post' => [
        '/contact/send' => [Home::class, 'contactSend'],
    ],

    'controllers' => [
        Dashboard::class => [
            'prefix' => '/dashboard',

            'get' => [
                '' => 'create',

                '/profile' => 'profile',

                '/admin-only' => [
                    'method' => 'adminOnly',
                    'middlewares' => [AdminMiddleware::class],
                ],
            ],

            'post' => [
                '/admin/save' => [
                    'method' => 'adminSave',
                    'middlewares' => [AdminMiddleware::class],
                ],
            ],
        ],

        UserController::class => [
            'prefix' => '/users',

            'get' => [
                '' => 'index',

                '/{id:\d+}' => 'show',
            ],

            'post' => [
                '' => 'store',
            ],
        ],
    ],

    'groups' => [
        '/api' => [
            'middlewares' => [ApiMiddleware::class],

            'routes' => [
                'get' => [
                    '/status' => function () {
                        return json([
                            'ok' => true,
                        ]);
                    },
                ],

                'post' => [
                    '/login' => [UserController::class, 'apiLogin'],
                ],
            ],
        ],
    ],
], [AuthMiddleware::class]);
```

---

# 23. Rutas de archivos por extensión

`Route::file()` permite servir archivos limitando extensiones.

```php
Route::file(
    '/uploads/private',
    [FileController::class, 'privateFile'],
    ['png', 'jpg', 'jpeg', 'webp']
)->middleware([AuthMiddleware::class]);
```

Acepta:

```txt
/uploads/private/foto.png
/uploads/private/foto.jpg
/uploads/private/folder/foto.jpeg
/uploads/private/folder/foto.webp
```

Rechaza:

```txt
/uploads/private/file.pdf
/uploads/private/file.php
```

---

## Ruta de documentos PDF

```php
Route::file(
    '/uploads/private/documents',
    [FileController::class, 'privateDocument'],
    ['pdf']
)->middleware([AuthMiddleware::class]);
```

---

# 24. Rutas de descarga

```php
Route::download(
    '/download/private',
    [FileController::class, 'downloadPrivateFile'],
    ['png', 'jpg', 'jpeg', 'pdf']
)->middleware([AuthMiddleware::class]);
```

Ejemplo:

```txt
/download/private/documento.pdf
/download/private/foto.png
```

---

# 25. `Storage::Routes()`

`Storage::Routes()` registra rutas genéricas para archivos del framework.

Debe colocarse al final.

```php
Storage::Routes();
```

Ejemplo recomendado:

```php
<?php

use App\Controllers\Home;
use Whis\Auth\Auth;
use Whis\Routing\Route;
use Whis\Storage\Storage;

Auth::Routes();

GET('', [Home::class, 'create']);

POST('', [Home::class, 'store']);

Route::get('/form', function () {
    return view('form');
});

Storage::Routes();
```

---

# 26. Orden recomendado de rutas

El orden importa.

Recomendado:

```php
Auth::Routes();

/*
 * 1. Rutas específicas.
 */
GET('', [Home::class, 'create']);

GET('/about', [Home::class, 'about']);

/*
 * 2. Rutas con parámetros específicos.
 */
GET('/users/{id:\d+}', [UserController::class, 'show']);

/*
 * 3. Grupos normales.
 */
GROUP('/dashboard', function () {
    GET('', [Dashboard::class, 'create']);
}, [AuthMiddleware::class]);

/*
 * 4. Controller groups.
 */
CONTROLLER(Dashboard::class, '/panel', [
    'get' => [
        '' => 'create',
    ],
], [AuthMiddleware::class]);

/*
 * 5. Catch-all controlados.
 */
GET('/pages/{slug:.*}', function (string $slug) {
    return json([
        'page' => $slug,
    ]);
});

/*
 * 6. Storage genérico.
 */
Storage::Routes();
```

---

# 27. Resumen rápido de cuándo usar cada uno

## `Route::get()`, `Route::post()`, etc.

Úsalos para rutas individuales.

```php
Route::get('/about', [Home::class, 'about']);

Route::post('/contact/send', [Home::class, 'contactSend']);
```

---

## `GET()`, `POST()`, etc.

Úsalos como sintaxis corta.

```php
GET('/about', [Home::class, 'about']);

POST('/contact/send', [Home::class, 'contactSend']);
```

---

## `Route::group()`

Úsalo para agrupar rutas variadas.

Permite:

```txt
Closures
Controladores distintos
Rutas en lote
Subgrupos
Controller groups
Middlewares globales
Middlewares individuales
```

Ejemplo:

```php
Route::group('/dashboard', function () {
    GET('', [Dashboard::class, 'create']);

    GET('/profile', [UserController::class, 'profile']);

    GET('/form', function () {
        return view('form');
    });
}, [AuthMiddleware::class]);
```

---

## `GROUP()`

Es el helper de `Route::group()`.

```php
GROUP('/dashboard', function () {
    GET('', [Dashboard::class, 'create']);
}, [AuthMiddleware::class]);
```

---

## `Route::controller()`

Úsalo para agrupar rutas de un mismo controlador.

```php
Route::controller(Dashboard::class, '/dashboard', function () {
    get('', 'create');

    get('/profile', 'profile');

    post('/users', 'storeUser');
}, [AuthMiddleware::class]);
```

---

## `CONTROLLER()`

Es el helper de `Route::controller()`.

```php
CONTROLLER(Dashboard::class, '/dashboard', [
    'get' => [
        '' => 'create',

        '/profile' => 'profile',
    ],

    'post' => [
        '/users' => 'storeUser',
    ],
], [AuthMiddleware::class]);
```

---

# 28. Ejemplo completo final de `routes/web.php`

```php
<?php

use App\Controllers\Home;
use App\Controllers\Dashboard;
use App\Controllers\UserController;
use App\Controllers\AdminController;
use App\Controllers\FileController;

use App\Middlewares\AuthMiddleware;
use App\Middlewares\AdminMiddleware;
use App\Middlewares\GuestMiddleware;
use App\Middlewares\ApiMiddleware;

use Whis\Auth\Auth;
use Whis\Http\Request;
use Whis\Routing\Route;
use Whis\Storage\Storage;

/*
|--------------------------------------------------------------------------
| Auth
|--------------------------------------------------------------------------
*/

Auth::Routes();

/*
|--------------------------------------------------------------------------
| Rutas individuales
|--------------------------------------------------------------------------
*/

GET('', [Home::class, 'create']);

POST('', [Home::class, 'store']);

Route::get('/form', function () {
    return view('form');
});

Route::get('/search', function (Request $request) {
    return json([
        'q' => $request->query('q'),
        'page' => $request->query('page'),
    ]);
});

/*
|--------------------------------------------------------------------------
| Rutas en lote
|--------------------------------------------------------------------------
*/

GET([
    '/about' => [Home::class, 'about'],

    '/services' => [Home::class, 'services'],

    '/contact' => [Home::class, 'contact'],
]);

POST([
    '/newsletter' => [Home::class, 'newsletter'],

    '/contact/send' => [Home::class, 'contactSend'],
]);

/*
|--------------------------------------------------------------------------
| Rutas con parámetros
|--------------------------------------------------------------------------
*/

GET('/users/{id:\d+}', [UserController::class, 'show']);

GET('/reports/{year:\d{4}}/{month:\d{1,2}}', [Dashboard::class, 'reports']);

/*
|--------------------------------------------------------------------------
| Grupo normal
|--------------------------------------------------------------------------
*/

GROUP('/account', function () {
    GET('', [UserController::class, 'account']);

    GET('/settings', [UserController::class, 'settings']);

    POST('/settings', [UserController::class, 'updateSettings']);
}, [AuthMiddleware::class]);

/*
|--------------------------------------------------------------------------
| Controller group
|--------------------------------------------------------------------------
*/

CONTROLLER(Dashboard::class, '/dashboard', function () {
    get('', 'create');

    get('/profile', 'profile');

    get('/users', 'users');

    get('/users/{id:\d+}', 'showUser');

    post('/users', 'storeUser');

    get('/admin-only', 'adminOnly', [AdminMiddleware::class]);
}, [AuthMiddleware::class]);

/*
|--------------------------------------------------------------------------
| Grupo declarativo completo
|--------------------------------------------------------------------------
*/

Route::group('/app', [
    'get' => [
        '/form' => function () {
            return view('form');
        },

        '/home' => [Home::class, 'create'],
    ],

    'post' => [
        '/contact/send' => [Home::class, 'contactSend'],
    ],

    'controllers' => [
        Dashboard::class => [
            'prefix' => '/panel',

            'get' => [
                '' => 'create',

                '/stats' => 'stats',

                '/admin-only' => [
                    'method' => 'adminOnly',
                    'middlewares' => [AdminMiddleware::class],
                ],
            ],

            'post' => [
                '/stats/save' => 'saveStats',
            ],
        ],
    ],

    'groups' => [
        '/api' => [
            'middlewares' => [ApiMiddleware::class],

            'routes' => [
                'get' => [
                    '/status' => function () {
                        return json([
                            'ok' => true,
                        ]);
                    },
                ],
            ],
        ],
    ],
], [AuthMiddleware::class]);

/*
|--------------------------------------------------------------------------
| Archivos privados
|--------------------------------------------------------------------------
*/

Route::file(
    '/uploads/private',
    [FileController::class, 'privateFile'],
    ['png', 'jpg', 'jpeg', 'webp']
)->middleware([AuthMiddleware::class]);

Route::download(
    '/download/private',
    [FileController::class, 'downloadPrivateFile'],
    ['png', 'jpg', 'jpeg', 'pdf']
)->middleware([AuthMiddleware::class]);

/*
|--------------------------------------------------------------------------
| Catch-all controlado
|--------------------------------------------------------------------------
*/

GET('/pages/{slug:.*}', function (string $slug) {
    return json([
        'page' => $slug,
    ]);
});

/*
|--------------------------------------------------------------------------
| Storage al final
|--------------------------------------------------------------------------
*/

Storage::Routes();
```
