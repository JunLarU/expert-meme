<?php

return [
    'tokens' => [
        /*
        |--------------------------------------------------------------------------
        | Roles que pueden administrar tokens desde /admin/api-tokens
        |--------------------------------------------------------------------------
        */

        'admin_roles' => [
            'admin',
            'super_admin',
        ],

        /*
        |--------------------------------------------------------------------------
        | Límite de tokens activos por usuario
        |--------------------------------------------------------------------------
        */

        'max_tokens_per_user' => 50,

        /*
        |--------------------------------------------------------------------------
        | Seguridad de abilities
        |--------------------------------------------------------------------------
        */

        'allow_wildcard' => true,
        'allow_custom_abilities' => true,
        'require_abilities' => true,
        'max_abilities' => 50,

        /*
        |--------------------------------------------------------------------------
        | Presets visibles en el panel
        |--------------------------------------------------------------------------
        */

        'abilities' => [
            '*'              => 'Acceso total',
            'tokens:read'    => 'Ver tokens',
            'tokens:create'  => 'Crear tokens',
            'tokens:update'  => 'Editar tokens',
            'tokens:delete'  => 'Eliminar tokens',
            'projects:read'  => 'Ver proyectos',
            'projects:write' => 'Modificar proyectos',
            'messages:read'  => 'Ver mensajes',
            'clients:read'   => 'Ver clientes',
        ],

        /*
        |--------------------------------------------------------------------------
        | Abilities marcadas por default al crear token
        |--------------------------------------------------------------------------
        | No uses "*" como default.
        */

        'default_abilities' => [
            'projects:read',
        ],
    ],
];