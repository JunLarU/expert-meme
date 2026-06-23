# Whis API Token Auth

Guía completa para implementar, configurar y usar autenticación de APIs por medio de **Bearer Tokens** en Whis.

Este documento explica desde lo básico hasta la función de cada archivo, cómo crear la tabla de tokens mediante migración, cómo proteger rutas con middleware, cómo crear tokens, cómo validar permisos y cómo consumir la API desde clientes externos.

---

## Índice

1. [Objetivo del sistema](#objetivo-del-sistema)
2. [Idea general](#idea-general)
3. [Flujo completo de autenticación](#flujo-completo-de-autenticación)
4. [Estructura de archivos](#estructura-de-archivos)
5. [Base de datos](#base-de-datos)
   - [Migración `api_tokens`](#migración-api_tokens)
   - [Campos de la tabla](#campos-de-la-tabla)
6. [Modelo `ApiToken`](#modelo-apitoken)
7. [Resultado de autenticación `ApiTokenResult`](#resultado-de-autenticación-apitokenresult)
8. [Guard de API `ApiTokenGuard`](#guard-de-api-apitokenguard)
9. [Actualización de `Auth`](#actualización-de-auth)
10. [Helpers de API](#helpers-de-api)
11. [Middleware principal `ApiTokenMiddleware`](#middleware-principal-apitokenmiddleware)
12. [Middleware de permisos `ApiAbilityMiddleware`](#middleware-de-permisos-apiabilitymiddleware)
13. [Controlador de tokens `TokenController`](#controlador-de-tokens-tokencontroller)
14. [Archivo de rutas API](#archivo-de-rutas-api)
15. [Cómo proteger rutas](#cómo-proteger-rutas)
    - [Ruta individual protegida](#ruta-individual-protegida)
    - [Grupo completo protegido](#grupo-completo-protegido)
    - [Rutas con permisos específicos](#rutas-con-permisos-específicos)
16. [Cómo crear un token](#cómo-crear-un-token)
17. [Cómo consumir la API](#cómo-consumir-la-api)
    - [Con `curl`](#con-curl)
    - [Con JavaScript `fetch`](#con-javascript-fetch)
    - [Con Postman o Insomnia](#con-postman-o-insomnia)
18. [Formato recomendado de respuestas JSON](#formato-recomendado-de-respuestas-json)
19. [Códigos de error](#códigos-de-error)
20. [CSRF vs Bearer Token](#csrf-vs-bearer-token)
21. [Configuración de Apache / Laragon](#configuración-de-apache--laragon)
22. [Seguridad recomendada](#seguridad-recomendada)
23. [Ejemplo completo de API](#ejemplo-completo-de-api)
24. [Troubleshooting](#troubleshooting)
25. [Checklist de implementación](#checklist-de-implementación)

---

## Objetivo del sistema

El objetivo es agregar a Whis una capa de autenticación para APIs usando tokens.

La autenticación tradicional del proyecto funciona por sesión, login y cookies. Eso es correcto para páginas web renderizadas desde el servidor.

Pero una API normalmente se consume desde:

- JavaScript.
- Apps móviles.
- Sistemas externos.
- Integraciones.
- Postman / Insomnia.
- Servicios de terceros.
- Paneles desacoplados del backend.

Para ese tipo de consumo no conviene depender de sesión ni de CSRF. Lo correcto es usar un token enviado en cada petición por medio del header:

```http
Authorization: Bearer whis_xxxxxxxxxxxxxxxxxxxxxxxxx
```

Con este sistema puedes hacer rutas como:

```php
Route::get('/api/me', function () {
    return Response::json([
        'ok' => true,
        'user' => auth()?->toArray(),
    ]);
})->middleware(ApiTokenMiddleware::class);
```

O proteger grupos completos:

```php
Route::group('/api', function () {
    Route::get('/me', [UserApiController::class, 'me']);
    Route::get('/projects', [ProjectApiController::class, 'index']);
}, [ApiTokenMiddleware::class]);
```

---

## Idea general

La idea del sistema es:

1. El usuario inicia sesión normalmente en el sitio.
2. Desde una sección protegida por sesión, el usuario crea un token de API.
3. El backend genera un token aleatorio seguro.
4. En la base de datos no se guarda el token real, solo su hash.
5. El token real se muestra una sola vez.
6. El cliente externo guarda ese token.
7. Cada petición a la API manda el token en el header `Authorization`.
8. El middleware valida el token.
9. Si el token es válido, la API continúa.
10. Si el token no existe, está revocado o expiró, se responde JSON con error.

---

## Flujo completo de autenticación

```txt
Cliente externo
    |
    |  GET /api/me
    |  Authorization: Bearer whis_abc123...
    v
ApiTokenMiddleware
    |
    |-- Extrae el Bearer Token
    |-- Calcula hash SHA-256
    |-- Busca token_hash en api_tokens
    |-- Revisa revoked_at
    |-- Revisa expires_at
    |-- Resuelve usuario/tokenable
    |-- Guarda resultado en ApiTokenGuard
    v
Controller / Closure
    |
    |-- auth() devuelve el usuario autenticado por token
    |-- api_token() devuelve el token actual
    |-- api_token_can('permiso') revisa abilities
    v
Response::json(...)
```

---

## Estructura de archivos

Estructura recomendada:

```txt
database/
└── migrations/
    └── 2026_06_22_000001_create_api_tokens.php

app/
├── Controllers/
│   └── Api/
│       └── TokenController.php
├── Middlewares/
│   ├── ApiTokenMiddleware.php
│   └── ApiAbilityMiddleware.php
└── Models/
    └── ApiToken.php

src/
└── Auth/
    ├── Auth.php
    └── Api/
        ├── ApiTokenGuard.php
        └── ApiTokenResult.php

helpers/
└── api_auth.php

routes/
└── api.php
```

> Ajusta las rutas si tu proyecto usa otra organización interna, pero la idea general es mantener el código de API separado del login web normal.

---

## Base de datos

La autenticación por tokens necesita una tabla llamada `api_tokens`.

Esta tabla guarda los tokens emitidos, sus permisos, fecha de expiración, fecha de revocación y relación con el usuario dueño del token.

---

## Migración `api_tokens`

Archivo recomendado:

```txt
database/migrations/2026_06_22_000001_create_api_tokens.php
```

Código:

```php
<?php

use Whis\Database\DB;
use Whis\Database\Migrations\Migration;

return new class () implements Migration {
    public function up()
    {
        try {
            DB::statement("
                CREATE TABLE IF NOT EXISTS api_tokens (
                    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

                    tokenable_type VARCHAR(190) NULL,
                    tokenable_id BIGINT UNSIGNED NULL,

                    name VARCHAR(190) NOT NULL,
                    token_prefix VARCHAR(24) NOT NULL,
                    token_hash CHAR(64) NOT NULL,

                    abilities TEXT NULL,

                    expires_at DATETIME NULL,
                    last_used_at DATETIME NULL,
                    revoked_at DATETIME NULL,

                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,

                    UNIQUE KEY api_tokens_token_hash_unique (token_hash),
                    INDEX api_tokens_token_prefix_index (token_prefix),
                    INDEX api_tokens_tokenable_index (tokenable_type, tokenable_id),
                    INDEX api_tokens_revoked_expires_index (revoked_at, expires_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
        } catch (\PDOException $th) {
            exit;
        }
    }

    public function down()
    {
        try {
            DB::statement('DROP TABLE IF EXISTS api_tokens');
        } catch (\PDOException $th) {
            exit;
        }
    }
};
```

---

## Campos de la tabla

### `id`

Identificador interno del token.

```sql
id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY
```

---

### `tokenable_type`

Clase del modelo dueño del token.

Ejemplo:

```txt
App\Models\User
```

Permite que el token pertenezca a diferentes tipos de modelos, no solo usuarios.

---

### `tokenable_id`

ID del modelo dueño del token.

Ejemplo:

```txt
1
```

Junto con `tokenable_type`, permite saber a quién pertenece el token.

---

### `name`

Nombre visible del token.

Ejemplos:

```txt
Token de Postman
Token para app móvil
Integración con CRM
Panel externo
```

---

### `token_prefix`

Primeros caracteres visibles del token.

Sirve para identificar un token sin guardar ni mostrar el token completo.

Ejemplo:

```txt
whis_R4nd0m
```

---

### `token_hash`

Hash SHA-256 del token real.

El token plano nunca debe guardarse completo en base de datos.

Ejemplo:

```txt
4f6c2e7b...
```

---

### `abilities`

Permisos del token en formato JSON.

Ejemplo:

```json
["projects:read", "projects:write"]
```

También puede usarse:

```json
["*"]
```

Para permitir todo.

---

### `expires_at`

Fecha de expiración del token.

Si es `NULL`, el token no expira automáticamente.

---

### `last_used_at`

Última vez que se usó el token.

Sirve para auditoría, limpieza o panel administrativo.

---

### `revoked_at`

Fecha en la que el token fue revocado.

Si tiene valor, el token ya no debe funcionar.

---

### `created_at`

Fecha de creación.

---

### `updated_at`

Fecha de última modificación.

---

## Modelo `ApiToken`

Archivo:

```txt
app/Models/ApiToken.php
```

Función:

- Representa la tabla `api_tokens`.
- Permite leer/escribir tokens.
- Contiene helpers para abilities.
- Permite saber si un token está expirado o revocado.

Ejemplo recomendado:

```php
<?php

namespace App\Models;

use Whis\Database\Model;

class ApiToken extends Model
{
    protected string $table = 'api_tokens';

    protected string $primaryKey = 'id';

    public function id(): int|string
    {
        return $this->{$this->primaryKey};
    }

    public function abilities(): array
    {
        $abilities = $this->abilities ?? null;

        if (!$abilities) {
            return [];
        }

        $decoded = json_decode((string) $abilities, true);

        return is_array($decoded) ? $decoded : [];
    }

    public function can(string $ability): bool
    {
        $abilities = $this->abilities();

        return in_array('*', $abilities, true)
            || in_array($ability, $abilities, true);
    }

    public function cant(string $ability): bool
    {
        return !$this->can($ability);
    }

    public function isRevoked(): bool
    {
        return !empty($this->revoked_at);
    }

    public function isExpired(): bool
    {
        if (empty($this->expires_at)) {
            return false;
        }

        return strtotime((string) $this->expires_at) <= time();
    }

    public function isValid(): bool
    {
        return !$this->isRevoked() && !$this->isExpired();
    }
}
```

---

## Resultado de autenticación `ApiTokenResult`

Archivo:

```txt
src/Auth/Api/ApiTokenResult.php
```

Función:

Representa el resultado de validar un token.

Contiene:

- El usuario autenticado.
- El modelo `ApiToken`.
- El token plano recibido, si necesitas auditarlo internamente.
- Estado de autenticación.

Ejemplo:

```php
<?php

namespace Whis\Auth\Api;

use App\Models\ApiToken;
use Whis\Auth\Authenticatable;

class ApiTokenResult
{
    public function __construct(
        protected ?Authenticatable $user = null,
        protected ?ApiToken $token = null,
        protected ?string $plainTextToken = null
    ) {
    }

    public function check(): bool
    {
        return $this->user !== null && $this->token !== null;
    }

    public function guest(): bool
    {
        return !$this->check();
    }

    public function user(): ?Authenticatable
    {
        return $this->user;
    }

    public function token(): ?ApiToken
    {
        return $this->token;
    }

    public function plainTextToken(): ?string
    {
        return $this->plainTextToken;
    }
}
```

---

## Guard de API `ApiTokenGuard`

Archivo:

```txt
src/Auth/Api/ApiTokenGuard.php
```

Función:

Es el servicio encargado de:

- Extraer un token desde el request.
- Validar el token.
- Buscar el hash en base de datos.
- Resolver el usuario.
- Guardar el resultado actual.
- Permitir consultar el usuario/token durante la petición.

Ejemplo conceptual:

```php
$result = app(ApiTokenGuard::class)->attempt($request);

if ($result->guest()) {
    return Response::json([
        'ok' => false,
        'message' => 'Token inválido.',
    ])->setStatus(401);
}
```

Responsabilidades recomendadas:

1. Leer header `Authorization`.
2. Validar formato `Bearer token`.
3. Hashear token con SHA-256.
4. Buscar `token_hash`.
5. Verificar que no esté revocado.
6. Verificar que no esté expirado.
7. Resolver usuario dueño del token.
8. Actualizar `last_used_at`.
9. Guardar resultado para `auth()`, `api_token()` y `api_token_can()`.

---

## Actualización de `Auth`

Archivo:

```txt
src/Auth/Auth.php
```

El `Auth` original trabaja con sesión:

```php
Auth::user()
Auth::isGuest()
```

Para APIs, conviene que `Auth::user()` primero revise si hay un usuario autenticado por token en el request actual.

Orden recomendado:

1. Usuario por API token.
2. Usuario por sesión.
3. `null`.

Ejemplo:

```php
public static function user(): ?Authenticatable
{
    $apiUser = app(ApiTokenGuard::class)->user();

    if ($apiUser) {
        return $apiUser;
    }

    return app(Authenticator::class)->resolve();
}
```

Esto permite que en tus controladores de API sigas usando:

```php
auth()
```

Sin preocuparte si el usuario viene de sesión o token.

---

## Helpers de API

Archivo:

```txt
helpers/api_auth.php
```

Helpers sugeridos:

```php
<?php

use App\Models\ApiToken;
use Whis\Auth\Api\ApiTokenGuard;

if (!function_exists('api_token')) {
    function api_token(): ?ApiToken
    {
        return app(ApiTokenGuard::class)->token();
    }
}

if (!function_exists('api_token_can')) {
    function api_token_can(string $ability): bool
    {
        return app(ApiTokenGuard::class)->token()?->can($ability) ?? false;
    }
}

if (!function_exists('api_token_cant')) {
    function api_token_cant(string $ability): bool
    {
        return !api_token_can($ability);
    }
}
```

Uso:

```php
if (api_token_can('projects:write')) {
    // Puede crear o modificar proyectos
}
```

---

## Middleware principal `ApiTokenMiddleware`

Archivo:

```txt
app/Middlewares/ApiTokenMiddleware.php
```

Función:

Protege rutas API revisando que exista un token válido.

Ejemplo conceptual:

```php
<?php

namespace App\Middlewares;

use Closure;
use Whis\Auth\Api\ApiTokenGuard;
use Whis\Http\Middleware;
use Whis\Http\Request;
use Whis\Http\Response;

class ApiTokenMiddleware implements Middleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $result = app(ApiTokenGuard::class)->attempt($request);

        if ($result->guest()) {
            return Response::json([
                'ok' => false,
                'message' => 'Token de API inválido, ausente, expirado o revocado.',
            ])->setStatus(401);
        }

        return $next($request);
    }
}
```

Uso:

```php
Route::get('/api/me', [UserApiController::class, 'me'])
    ->middleware(ApiTokenMiddleware::class);
```

---

## Middleware de permisos `ApiAbilityMiddleware`

Archivo:

```txt
app/Middlewares/ApiAbilityMiddleware.php
```

Función:

Protege rutas que requieren un permiso específico.

Ejemplo:

```php
Route::post('/api/projects', [ProjectApiController::class, 'store'])
    ->middleware([
        ApiTokenMiddleware::class,
        new ApiAbilityMiddleware('projects:write'),
    ]);
```

Ejemplo conceptual:

```php
<?php

namespace App\Middlewares;

use Closure;
use Whis\Http\Middleware;
use Whis\Http\Request;
use Whis\Http\Response;

class ApiAbilityMiddleware implements Middleware
{
    public function __construct(
        protected string $ability
    ) {
    }

    public function handle(Request $request, Closure $next): Response
    {
        if (!api_token_can($this->ability)) {
            return Response::json([
                'ok' => false,
                'message' => 'No tienes permiso para realizar esta acción.',
                'requiredAbility' => $this->ability,
            ])->setStatus(403);
        }

        return $next($request);
    }
}
```

---

## Controlador de tokens `TokenController`

Archivo:

```txt
app/Controllers/Api/TokenController.php
```

Función:

Permite administrar tokens desde una sesión web normal.

Normalmente este controlador debe estar protegido por `AuthMiddleware`, no por `ApiTokenMiddleware`.

Ejemplo de rutas:

```php
Route::group('/account/api-tokens', function () {
    Route::get('', [TokenController::class, 'index']);
    Route::post('', [TokenController::class, 'store']);
    Route::delete('/{id:\d+}', [TokenController::class, 'destroy']);
}, [AuthMiddleware::class]);
```

Acciones recomendadas:

### `index`

Lista tokens del usuario autenticado.

No debe mostrar el token completo.

Debe mostrar:

- ID.
- Nombre.
- Prefix.
- Abilities.
- Expiración.
- Último uso.
- Si está revocado.
- Fecha de creación.

---

### `store`

Crea un token nuevo.

Debe recibir:

```txt
name
abilities
expires_at
```

Ejemplo de abilities:

```json
["projects:read", "projects:write"]
```

Debe responder con el token plano una sola vez:

```json
{
    "ok": true,
    "message": "Token creado correctamente.",
    "token": "whis_xxxxxxxxxxxxxxxxxxxxxxxxx",
    "tokenPrefix": "whis_xxxxx"
}
```

---

### `destroy`

Revoca un token.

No borra necesariamente el registro; solo llena `revoked_at`.

Esto permite auditoría.

---

## Archivo de rutas API

Archivo recomendado:

```txt
routes/api.php
```

Ejemplo:

```php
<?php

use App\Controllers\Api\ProjectApiController;
use App\Middlewares\ApiAbilityMiddleware;
use App\Middlewares\ApiTokenMiddleware;
use Whis\Http\Response;
use Whis\Routing\Route;

Route::group('/api', function () {
    Route::get('/health', function () {
        return Response::json([
            'ok' => true,
            'message' => 'API funcionando correctamente.',
        ]);
    });

    Route::get('/me', function () {
        return Response::json([
            'ok' => true,
            'user' => auth()?->toArray(),
        ]);
    })->middleware(ApiTokenMiddleware::class);

    Route::get('/projects', [ProjectApiController::class, 'index'])
        ->middleware([
            ApiTokenMiddleware::class,
            new ApiAbilityMiddleware('projects:read'),
        ]);

    Route::post('/projects', [ProjectApiController::class, 'store'])
        ->middleware([
            ApiTokenMiddleware::class,
            new ApiAbilityMiddleware('projects:write'),
        ]);
});
```

También puedes proteger todo el grupo:

```php
Route::group('/api', function () {
    Route::get('/me', [UserApiController::class, 'me']);
    Route::get('/projects', [ProjectApiController::class, 'index']);
    Route::post('/projects', [ProjectApiController::class, 'store'])
        ->middleware(new ApiAbilityMiddleware('projects:write'));
}, [ApiTokenMiddleware::class]);
```

---

## Cómo proteger rutas

### Ruta individual protegida

```php
Route::get('/api/me', [UserApiController::class, 'me'])
    ->middleware(ApiTokenMiddleware::class);
```

---

### Grupo completo protegido

```php
Route::group('/api', function () {
    Route::get('/me', [UserApiController::class, 'me']);
    Route::get('/projects', [ProjectApiController::class, 'index']);
}, [ApiTokenMiddleware::class]);
```

---

### Rutas con permisos específicos

```php
Route::get('/api/projects', [ProjectApiController::class, 'index'])
    ->middleware([
        ApiTokenMiddleware::class,
        new ApiAbilityMiddleware('projects:read'),
    ]);
```

```php
Route::post('/api/projects', [ProjectApiController::class, 'store'])
    ->middleware([
        ApiTokenMiddleware::class,
        new ApiAbilityMiddleware('projects:write'),
    ]);
```

Si todo el grupo ya tiene `ApiTokenMiddleware`, solo agrega el permiso:

```php
Route::group('/api', function () {
    Route::post('/projects', [ProjectApiController::class, 'store'])
        ->middleware(new ApiAbilityMiddleware('projects:write'));
}, [ApiTokenMiddleware::class]);
```

---

## Cómo crear un token

Hay varias formas de crear tokens.

La más recomendable es hacerlo desde un controlador web protegido por sesión.

Ejemplo de formulario:

```html
<form method="POST" action="/account/api-tokens">
    <?= csrf_field('api-tokens.create') ?>

    <label>
        Nombre del token
        <input type="text" name="name" placeholder="Token para Postman">
    </label>

    <label>
        Permisos
        <input type="text" name="abilities" value="projects:read,projects:write">
    </label>

    <label>
        Expira en
        <input type="datetime-local" name="expires_at">
    </label>

    <button type="submit">
        Crear token
    </button>
</form>
```

Ejemplo de generación interna:

```php
$plainTextToken = 'whis_' . bin2hex(random_bytes(32));
$tokenHash = hash('sha256', $plainTextToken);
$tokenPrefix = substr($plainTextToken, 0, 16);
```

Luego se guarda:

```php
DB::statement("
    INSERT INTO api_tokens (
        tokenable_type,
        tokenable_id,
        name,
        token_prefix,
        token_hash,
        abilities,
        expires_at,
        created_at
    ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
", [
    get_class(auth()),
    auth()->id(),
    $name,
    $tokenPrefix,
    $tokenHash,
    json_encode($abilities, JSON_UNESCAPED_UNICODE),
    $expiresAt,
]);
```

El valor que se entrega al usuario es:

```php
$plainTextToken
```

Pero en la tabla solo se guarda:

```php
hash('sha256', $plainTextToken)
```

---

## Cómo consumir la API

Para consumir una ruta protegida, siempre manda:

```http
Authorization: Bearer TU_TOKEN
Accept: application/json
```

---

### Con `curl`

```bash
curl -X GET "http://localhost/api/me" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer whis_TU_TOKEN"
```

Ejemplo con POST:

```bash
curl -X POST "http://localhost/api/projects" \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer whis_TU_TOKEN" \
  -d '{
    "title": "Proyecto API",
    "location": "Querétaro",
    "year": 2026
  }'
```

---

### Con JavaScript `fetch`

```js
const token = "whis_TU_TOKEN";

const response = await fetch("http://localhost/api/me", {
  method: "GET",
  headers: {
    "Accept": "application/json",
    "Authorization": `Bearer ${token}`,
  },
});

const data = await response.json();

console.log(data);
```

POST:

```js
const token = "whis_TU_TOKEN";

const response = await fetch("http://localhost/api/projects", {
  method: "POST",
  headers: {
    "Accept": "application/json",
    "Content-Type": "application/json",
    "Authorization": `Bearer ${token}`,
  },
  body: JSON.stringify({
    title: "Proyecto API",
    location: "Querétaro",
    year: 2026,
  }),
});

const data = await response.json();

console.log(data);
```

---

### Con Postman o Insomnia

1. Crea una request.
2. Usa la URL:

```txt
http://localhost/api/me
```

3. En método selecciona:

```txt
GET
```

4. Ve a la pestaña de Authorization.
5. Tipo:

```txt
Bearer Token
```

6. Pega el token:

```txt
whis_TU_TOKEN
```

7. En Headers agrega:

```txt
Accept: application/json
```

8. Envía la petición.

---

## Formato recomendado de respuestas JSON

Usa un formato consistente en todas tus respuestas.

### Respuesta exitosa

```json
{
    "ok": true,
    "message": "Operación realizada correctamente.",
    "data": {}
}
```

### Respuesta con error

```json
{
    "ok": false,
    "message": "No autorizado.",
    "errors": {}
}
```

### Respuesta de validación

```json
{
    "ok": false,
    "message": "Revisa los campos enviados.",
    "errors": {
        "email": [
            "El correo es obligatorio."
        ]
    }
}
```

---

## Códigos de error

### `200 OK`

Petición correcta.

---

### `201 Created`

Recurso creado correctamente.

Ejemplo:

```php
return Response::json([
    'ok' => true,
    'message' => 'Proyecto creado correctamente.',
    'data' => $project,
])->setStatus(201);
```

---

### `400 Bad Request`

La petición está mal formada.

---

### `401 Unauthorized`

No hay token, el token es inválido, expiró o fue revocado.

Ejemplo:

```json
{
    "ok": false,
    "message": "Token de API inválido, ausente, expirado o revocado."
}
```

---

### `403 Forbidden`

El token es válido, pero no tiene permiso para esa acción.

Ejemplo:

```json
{
    "ok": false,
    "message": "No tienes permiso para realizar esta acción.",
    "requiredAbility": "projects:write"
}
```

---

### `404 Not Found`

Ruta o recurso no encontrado.

---

### `419 Page Expired`

Solo debería usarse para CSRF en formularios web.

No debería ser común en APIs con Bearer Token.

---

### `422 Unprocessable Entity`

Error de validación.

---

### `500 Internal Server Error`

Error interno del servidor.

---

## CSRF vs Bearer Token

Tu proyecto ya tiene middleware CSRF para formularios.

Eso está bien para rutas web como:

```php
Route::post('/login', [LoginController::class, 'store'])
    ->middleware(CsrfSaverMiddleware::class);
```

Pero para una API con Bearer Token normalmente no necesitas CSRF.

### Formularios web

Usan:

- Sesión.
- Cookies.
- CSRF token.
- Redirecciones.

Ejemplo:

```php
Route::post('/contact', [ContactController::class, 'store'])
    ->middleware(CsrfSaverMiddleware::class);
```

---

### APIs

Usan:

- `Authorization: Bearer token`.
- JSON.
- Códigos HTTP.
- No redirecciones.
- No CSRF.

Ejemplo:

```php
Route::post('/api/projects', [ProjectApiController::class, 'store'])
    ->middleware(ApiTokenMiddleware::class);
```

---

### Regla práctica

No mezcles `CsrfSaverMiddleware` con `ApiTokenMiddleware` en rutas API, salvo que tengas una razón muy específica.

Para APIs, la seguridad principal debe ser:

```http
Authorization: Bearer token
```

---

## Configuración de Apache / Laragon

En algunos entornos Apache, especialmente con Laragon, el header `Authorization` puede no llegar correctamente a PHP.

Si el middleware siempre responde `401` aunque el token esté bien, agrega esto al `.htaccess`:

```apache
RewriteEngine On

RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]
```

También puedes revisar en PHP:

```php
$request->headers('authorization')
```

O temporalmente:

```php
var_dump($_SERVER['HTTP_AUTHORIZATION'] ?? null);
var_dump($_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? null);
```

---

## Seguridad recomendada

### 1. Nunca guardes el token plano

Correcto:

```php
$hash = hash('sha256', $plainTextToken);
```

Incorrecto:

```php
$token = $plainTextToken;
```

---

### 2. Muestra el token una sola vez

Después de crear un token, muéstralo inmediatamente.

No debe poder consultarse completo después.

---

### 3. Usa HTTPS en producción

Los Bearer Tokens viajan en headers.

En producción siempre usa HTTPS.

---

### 4. Usa expiración cuando sea posible

Ejemplo:

```txt
30 días
90 días
1 año
```

Para tokens críticos, evita tokens sin expiración.

---

### 5. Permite revocar tokens

No borres necesariamente el registro.

Marca:

```sql
revoked_at = NOW()
```

---

### 6. Usa abilities específicas

Mejor:

```json
["projects:read"]
```

Que:

```json
["*"]
```

---

### 7. Registra `last_used_at`

Sirve para saber si un token sigue activo.

---

### 8. No mandes tokens por URL

Incorrecto:

```txt
/api/me?token=whis_xxxxx
```

Correcto:

```http
Authorization: Bearer whis_xxxxx
```

---

### 9. No guardes tokens en repositorios

Nunca subas tokens reales a GitHub o a archivos `.env` compartidos.

---

### 10. Usa nombres claros para tokens

Ejemplos:

```txt
Postman Juan
App móvil producción
CRM integración ventas
Dashboard externo
```

---

## Ejemplo completo de API

### Ruta

```php
<?php

use App\Controllers\Api\ProjectApiController;
use App\Middlewares\ApiAbilityMiddleware;
use App\Middlewares\ApiTokenMiddleware;
use Whis\Routing\Route;

Route::group('/api', function () {
    Route::get('/projects', [ProjectApiController::class, 'index'])
        ->middleware(new ApiAbilityMiddleware('projects:read'));

    Route::post('/projects', [ProjectApiController::class, 'store'])
        ->middleware(new ApiAbilityMiddleware('projects:write'));

    Route::delete('/projects/{id:\d+}', [ProjectApiController::class, 'destroy'])
        ->middleware(new ApiAbilityMiddleware('projects:delete'));
}, [ApiTokenMiddleware::class]);
```

---

### Controller

```php
<?php

namespace App\Controllers\Api;

use Whis\Http\Request;
use Whis\Http\Response;

class ProjectApiController
{
    public function index(): Response
    {
        return Response::json([
            'ok' => true,
            'data' => [
                [
                    'id' => 1,
                    'title' => 'Plataforma metálica de producción',
                    'location' => 'Celaya, Gto.',
                    'year' => 2025,
                ],
            ],
        ]);
    }

    public function store(Request $request): Response
    {
        $title = trim((string) $request->data('title'));

        if ($title === '') {
            return Response::json([
                'ok' => false,
                'message' => 'Revisa los campos enviados.',
                'errors' => [
                    'title' => ['El título es obligatorio.'],
                ],
            ])->setStatus(422);
        }

        return Response::json([
            'ok' => true,
            'message' => 'Proyecto creado correctamente.',
            'data' => [
                'id' => 123,
                'title' => $title,
            ],
        ])->setStatus(201);
    }

    public function destroy(string|int $id): Response
    {
        return Response::json([
            'ok' => true,
            'message' => 'Proyecto eliminado correctamente.',
            'id' => $id,
        ]);
    }
}
```

---

### Consumo

```bash
curl -X GET "http://localhost/api/projects" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer whis_TU_TOKEN"
```

---

## Troubleshooting

### Siempre recibo `401 Unauthorized`

Revisa:

1. Que estés enviando header:

```http
Authorization: Bearer whis_TU_TOKEN
```

2. Que el token no tenga espacios extra.
3. Que el token no esté revocado.
4. Que el token no haya expirado.
5. Que `token_hash` coincida con:

```php
hash('sha256', $plainTextToken)
```

6. Que Apache no esté eliminando el header `Authorization`.

En Laragon agrega:

```apache
RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]
```

---

### Recibo `403 Forbidden`

El token existe y es válido, pero no tiene la ability requerida.

Ejemplo:

La ruta pide:

```php
new ApiAbilityMiddleware('projects:write')
```

Pero el token tiene:

```json
["projects:read"]
```

Solución:

Agrega la ability correcta:

```json
["projects:read", "projects:write"]
```

O temporalmente:

```json
["*"]
```

---

### `auth()` devuelve `null`

Revisa:

1. Que la ruta tenga `ApiTokenMiddleware`.
2. Que `Auth::user()` revise primero `ApiTokenGuard`.
3. Que el token tenga `tokenable_type` correcto.
4. Que el usuario exista en base de datos.
5. Que el modelo del usuario extienda `Authenticatable`.

---

### El token se guarda pero no funciona

Revisa que estés guardando el hash, no el token plano.

Debe ser:

```php
$tokenHash = hash('sha256', $plainTextToken);
```

Y al validar también:

```php
$tokenHash = hash('sha256', $plainTextTokenFromRequest);
```

---

### El header `Authorization` no llega

En Apache/Laragon agrega al `.htaccess`:

```apache
RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]
```

También puedes revisar:

```php
$_SERVER['HTTP_AUTHORIZATION'] ?? null
$_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? null
```

---

### Me responde HTML en lugar de JSON

Agrega en el cliente:

```http
Accept: application/json
```

Y en el backend usa:

```php
Response::json([...])
```

---

### Me da error de CSRF

No uses `CsrfSaverMiddleware` en rutas API protegidas por Bearer Token.

Correcto:

```php
Route::post('/api/projects', [ProjectApiController::class, 'store'])
    ->middleware(ApiTokenMiddleware::class);
```

Incorrecto para API:

```php
Route::post('/api/projects', [ProjectApiController::class, 'store'])
    ->middleware([
        ApiTokenMiddleware::class,
        CsrfSaverMiddleware::class,
    ]);
```

---

## Checklist de implementación

### Base de datos

- [ ] Crear migración `create_api_tokens`.
- [ ] Ejecutar migraciones.
- [ ] Confirmar que existe la tabla `api_tokens`.

---

### Modelos y servicios

- [ ] Crear `App\Models\ApiToken`.
- [ ] Crear `Whis\Auth\Api\ApiTokenResult`.
- [ ] Crear `Whis\Auth\Api\ApiTokenGuard`.
- [ ] Actualizar `Whis\Auth\Auth`.
- [ ] Cargar helper `helpers/api_auth.php`.

---

### Middlewares

- [ ] Crear `App\Middlewares\ApiTokenMiddleware`.
- [ ] Crear `App\Middlewares\ApiAbilityMiddleware`.

---

### Rutas

- [ ] Crear `routes/api.php`.
- [ ] Asegurar que el archivo de rutas se cargue en el bootstrap.
- [ ] Proteger rutas privadas con `ApiTokenMiddleware`.
- [ ] Proteger acciones específicas con `ApiAbilityMiddleware`.

---

### Tokens

- [ ] Crear `TokenController`.
- [ ] Proteger rutas de tokens con `AuthMiddleware`.
- [ ] Mostrar token plano solo una vez.
- [ ] Guardar solo hash SHA-256.
- [ ] Permitir revocar tokens.

---

### Cliente

- [ ] Enviar `Authorization: Bearer TOKEN`.
- [ ] Enviar `Accept: application/json`.
- [ ] Enviar `Content-Type: application/json` en POST/PUT/PATCH.
- [ ] No mandar tokens por URL.
- [ ] No mezclar CSRF con Bearer Token.

---

### Producción

- [ ] Usar HTTPS.
- [ ] Revisar `.htaccess` para `Authorization`.
- [ ] Usar expiración de tokens.
- [ ] Registrar `last_used_at`.
- [ ] Dar permisos mínimos necesarios.
- [ ] Revocar tokens que ya no se usen.

---

## Resumen rápido

Para crear una API protegida:

```php
Route::group('/api', function () {
    Route::get('/me', function () {
        return Response::json([
            'ok' => true,
            'user' => auth()?->toArray(),
        ]);
    });
}, [ApiTokenMiddleware::class]);
```

Para consumirla:

```bash
curl "http://localhost/api/me" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer whis_TU_TOKEN"
```

Para validar permisos:

```php
Route::post('/api/projects', [ProjectApiController::class, 'store'])
    ->middleware([
        ApiTokenMiddleware::class,
        new ApiAbilityMiddleware('projects:write'),
    ]);
```

El sistema final queda separado así:

```txt
Web normal:
    sesión + cookies + csrf

API:
    bearer token + json + status codes
```

Esto mantiene limpio el framework y permite que tus APIs puedan ser consumidas por apps externas, paneles, integraciones y clientes HTTP sin depender del login tradicional.
