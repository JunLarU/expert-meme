<?php
// namespace App\Controllers\Auth;

// use App\Models\User;
// use Whis\Cryptic\Hasher;
// use Whis\Http\Controller;
// use Whis\Http\Request;
// use Whis\Http\Response;

// class RegisterController extends Controller
// {
//     public function create()
//     {
//         if (! isGuest()) {
//             return redirect('/');
//         }

//         /*
//          * Ya no generes token manual aquí.
//          * El formulario usa:
//          *
//          * @csrf('register')
//          *
//          * y el middleware CSRF se encarga de validarlo.
//          */
//         return view('auth/register');
//     }

//     public function store(Request $request, Hasher $hasher)
//     {
//         /*
//          * Validación principal del registro.
//          *
//          * filesquantity:,1
//          * - Sin mínimo.
//          * - Máximo 1 archivo.
//          * - 0 archivos es válido.
//          *
//          * filetype:png/jpeg/jpg
//          * - Solo imagen.
//          *
//          * filesize:1mb
//          * - Máximo 1 MB por archivo.
//          */
//         $data = $request->validate([
//             'email'            => 'required|email',
//             'name'             => 'required',
//             'role'             => 'required',
//             'password'         => 'required',
//             'confirm_password' => 'required',

//             // 'files' => [
//             //     'filesquantity:,1',
//             //     'filetype:png/jpeg/jpg',
//             //     'filesize:1mb',
//             // ],
//         ], false, [
//             'email'    => [
//                 'required' => 'Completa tu correo electrónico.',
//                 'email'    => 'Escribe un correo electrónico válido.',
//             ],
//             'password' => [
//                 'required' => 'Completa tu contraseña.',
//             ],
//             'role'     => [
//                 'required' => 'Selecciona un rol.',
//             ],
//             'password' => [
//                 'required' => 'Completa tu contraseña.',
//             ],
//         ]);

//         /*
//          * Email duplicado.
//          */
//         if (User::firstWhere('email', $data['email'])) {
//             return $this->validationError($request, [
//                 'email' => [
//                     'email' => 'Ya existe una cuenta registrada con este correo electrónico.',
//                 ],
//             ]);
//         }

//         /*
//          * Confirmación de contraseña.
//          */
//         if ($data['password'] !== $data['confirm_password']) {
//             return $this->validationError($request, [
//                 'confirm_password' => [
//                     'confirm_password' => 'Las contraseñas no coinciden.',
//                 ],
//             ]);
//         }

//         /*
//          * Archivos validados.
//          */
//         // $files = $request->file('files');

//         // if ($files instanceof File) {
//         //     $files = [$files];
//         // }

//         // if (!is_array($files)) {
//         //     $files = [];
//         // }

//         // $profilePicture = null;

//         // foreach ($files as $file) {
//         //     if (!$file instanceof File) {
//         //         continue;
//         //     }

//         //     if ($file->hasUploadError()) {
//         //         continue;
//         //     }

//         //     $profilePicture = $file->store(
//         //         "private",
//         //         false,
//         //         "storage/uploads",
//         //         false,
//         //         "storage/uploads"
//         //     );

//         //     /*
//         //      * Solo permitimos 1 archivo.
//         //      */
//         //     break;
//         // }

//         /*
//          * Payload limpio para BD.
//          * No mandes confirm_password ni files a User::create().
//          */
//         $payload = [
//             'email'    => $data['email'],
//             'name'     => $data['name'],
//             'role'     => $data['role'],
//             'password' => $hasher->hash($data['password']),
//         ];

//         /*
//          * Ajusta este nombre según tu columna real.
//          *
//          * Si tu columna se llama foto, déjalo así.
//          * Si se llama avatar, profile_picture, image, etc.,
//          * cambia 'foto' por el nombre correcto.
//          */
//         // if ($profilePicture !== null) {
//         //     $payload['foto'] = $profilePicture;
//         // }

//         User::create($payload);

//         $user = User::firstWhere('email', $payload['email']);

//         if ($user) {
//             $user->login();
//         }

//         if ($this->expectsJson($request)) {
//             return Response::json([
//                 'ok'       => true,
//                 'message'  => 'Cuenta creada correctamente.',
//                 'redirect' => '/',
//                 'user'     => [
//                     'email' => $payload['email'],
//                     'name'  => $payload['name'],
//                     'role'  => $payload['role'],
//                     // 'foto'  => $profilePicture,
//                 ],
//             ]);
//         }

//         return redirect('/');
//     }
// }

namespace App\Controllers\Auth;

use App\Models\User;
use Whis\Cryptic\Hasher;
use Whis\Http\Controller;
use Whis\Http\Request;
use Whis\Http\Response;

class RegisterController extends Controller
{
    public function create()
    {
        if (!isGuest()) {
            return redirect('/');
        }

        return view('auth/register');
    }

    public function store(Request $request, Hasher $hasher)
    {
        /*
         * IMPORTANTE:
         * El segundo parámetro debe ser TRUE.
         *
         * Así, si falla la validación, se lanza ValidationException.
         * App.php la convierte automáticamente en JSON 422 cuando
         * ajax-form.js envía Accept: application/json / X-Requested-With.
         */
        $data = $request->validate([
            'email'            => 'required|email',
            'name'             => 'required',
            'role'             => 'required',
            'password'         => 'required',
            'confirm_password' => 'required',

            /*
             * Activa esto después si vuelves a usar archivos.
             *
             * 'files' => [
             *     'filesquantity:,1',
             *     'filetype:png/jpeg/jpg',
             *     'filesize:1mb',
             * ],
             */
        ], true, [
            'email' => [
                'required' => 'Completa tu correo electrónico.',
                'email'    => 'Escribe un correo electrónico válido.',
            ],
            'name' => [
                'required' => 'Completa el nombre.',
            ],
            'role' => [
                'required' => 'Selecciona un rol.',
            ],
            'password' => [
                'required' => 'Completa tu contraseña.',
            ],
            'confirm_password' => [
                'required' => 'Confirma tu contraseña.',
            ],

            /*
             * Mensajes para archivos, si luego activas la subida.
             *
             * 'files' => [
             *     'filesquantity' => 'Solo puedes subir máximo 1 archivo.',
             *     'filetype'      => 'Solo se permiten imágenes PNG, JPG o JPEG.',
             *     'filesize'      => 'La imagen debe pesar máximo 1 MB.',
             * ],
             */
        ]);

        /*
         * Normalización del payload.
         */
        $email = strtolower(trim((string) ($data['email'] ?? '')));
        $name = trim(preg_replace('/\s+/', ' ', (string) ($data['name'] ?? '')));
        $role = trim((string) ($data['role'] ?? ''));
        $password = (string) ($data['password'] ?? '');
        $confirmPassword = (string) ($data['confirm_password'] ?? '');

        /*
         * Validaciones extra que tu Validator actual no trae como reglas nativas.
         * Esto mantiene consistencia con el HTML/ajax-form.
         */
        if (mb_strlen($name) < 3) {
            return $this->validationError($request, [
                'name' => [
                    'min' => 'El nombre debe tener al menos 3 caracteres.',
                ],
            ]);
        }

        if (mb_strlen($name) > 120) {
            return $this->validationError($request, [
                'name' => [
                    'max' => 'El nombre no debe exceder 120 caracteres.',
                ],
            ]);
        }

        if (mb_strlen($email) > 150) {
            return $this->validationError($request, [
                'email' => [
                    'max' => 'El correo electrónico no debe exceder 150 caracteres.',
                ],
            ]);
        }

        $allowedRoles = ['manager', 'admin'];

        if (!in_array($role, $allowedRoles, true)) {
            return $this->validationError($request, [
                'role' => [
                    'role' => 'Selecciona un rol válido.',
                ],
            ]);
        }

        if (mb_strlen($password) < 8) {
            return $this->validationError($request, [
                'password' => [
                    'min' => 'La contraseña debe tener al menos 8 caracteres.',
                ],
            ]);
        }

        if (mb_strlen($confirmPassword) < 8) {
            return $this->validationError($request, [
                'confirm_password' => [
                    'min' => 'La confirmación debe tener al menos 8 caracteres.',
                ],
            ]);
        }

        if ($password !== $confirmPassword) {
            return $this->validationError($request, [
                'confirm_password' => [
                    'confirm_password' => 'Las contraseñas no coinciden.',
                ],
            ]);
        }

        /*
         * Email duplicado.
         */
        if (User::firstWhere('email', $email)) {
            return $this->validationError($request, [
                'email' => [
                    'email' => 'Ya existe una cuenta registrada con este correo electrónico.',
                ],
            ]);
        }

        /*
         * Archivos.
         * Déjalo comentado mientras el formulario no mande archivos.
         */
        /*
        $files = $request->file('files');

        if ($files instanceof File) {
            $files = [$files];
        }

        if (!is_array($files)) {
            $files = [];
        }

        $profilePicture = null;

        foreach ($files as $file) {
            if (!$file instanceof File) {
                continue;
            }

            if ($file->hasUploadError()) {
                continue;
            }

            $profilePicture = $file->store(
                'private',
                false,
                'storage/uploads',
                false,
                'storage/uploads'
            );

            break;
        }
        */

        /*
         * Payload limpio para BD.
         */
        $payload = [
            'email'    => $email,
            'name'     => $name,
            'role'     => $role,
            'password' => $hasher->hash($password),
        ];

        /*
        if ($profilePicture !== null) {
            $payload['foto'] = $profilePicture;
        }
        */

        User::create($payload);

        $user = User::firstWhere('email', $email);

        if ($user) {
            $user->login();
        }

        /*
         * Respuesta compatible con ajax-form.js.
         *
         * ajax-form.js:
         * - interpreta ok: true como éxito
         * - muestra message si no hay redirect
         * - redirige si existe data.redirect
         */
        if ($this->expectsJson($request)) {
            return Response::json([
                'ok'       => true,
                'message'  => 'Cuenta creada correctamente.',
                'redirect' => '/',
                'user'     => [
                    'email' => $email,
                    'name'  => $name,
                    'role'  => $role,
                    // 'foto' => $profilePicture ?? null,
                ],
            ])->setStatus(201);
        }

        return redirect('/');
    }
}