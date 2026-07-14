<?php

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
        /*
         * Si ya existe un Admin, el registro inicial queda bloqueado.
         */
        if ($this->firstAdminExists()) {
            //return redirect('/login');
        }

        /*
         * Si alguien ya está logueado y todavía no existe admin,
         * no dejamos que use esta pantalla.
         */
        if (! isGuest()) {
            return redirect('/admin');
        }

        return view('auth/register');
    }

    public function store(Request $request, Hasher $hasher)
    {
        /*
         * Protección real del endpoint.
         * Aunque alguien mande POST directo, ya no podrá crear otro admin.
         */
        if ($this->firstAdminExists()) {
            if ($this->expectsJson($request)) {
                return Response::json([
                    'ok'       => false,
                    'message'  => 'El registro inicial ya fue completado.',
                    'redirect' => '/login',
                    'errors'   => [
                        'register' => 'Ya existe un administrador registrado.',
                    ],
                ])->setStatus(403);
            }

            return redirect('/login');
        }

        if (! isGuest()) {
            if ($this->expectsJson($request)) {
                return Response::json([
                    'ok'       => false,
                    'message'  => 'Ya tienes una sesión iniciada.',
                    'redirect' => '/admin',
                    'errors'   => [],
                ])->setStatus(403);
            }

            return redirect('/admin');
        }

        /*
         * Ya no pedimos role.
         * Este registro SIEMPRE crea el primer Admin.
         */
        $data = $request->validate([
            'email'            => 'required|email',
            'name'             => 'required',
            'password'         => 'required',
            'confirm_password' => 'required',
        ], true, [
            'email' => [
                'required' => 'Completa tu correo electrónico.',
                'email'    => 'Escribe un correo electrónico válido.',
            ],
            'name' => [
                'required' => 'Completa el nombre.',
            ],
            'password' => [
                'required' => 'Completa tu contraseña.',
            ],
            'confirm_password' => [
                'required' => 'Confirma tu contraseña.',
            ],
        ]);

        $email = strtolower(trim((string) ($data['email'] ?? '')));
        $name = trim(preg_replace('/\s+/', ' ', (string) ($data['name'] ?? '')));
        $password = (string) ($data['password'] ?? '');
        $confirmPassword = (string) ($data['confirm_password'] ?? '');

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

        if (mb_strlen($password) < 8) {
            return $this->validationError($request, [
                'password' => [
                    'min' => 'La contraseña debe tener al menos 8 caracteres.',
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

        if (User::firstWhere('email', $email)) {
            return $this->validationError($request, [
                'email' => [
                    'email' => 'Ya existe una cuenta registrada con este correo electrónico.',
                ],
            ]);
        }

        /*
         * Segunda comprobación justo antes de insertar.
         * Evita que dos requests creen admins al mismo tiempo.
         */
        if ($this->firstAdminExists()) {
            return $this->validationError($request, [
                'register' => [
                    'locked' => 'El registro inicial ya fue completado.',
                ],
            ], 'El registro inicial ya fue completado.', 403);
        }

        $payload = [
            'email'      => $email,
            'name'       => $name,
            'role'       => 'admin',
            'password'   => $hasher->hash($password),
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        User::create($payload);

        $user = User::firstWhere('email', $email);

        if ($user) {
            $user->login();
        }

        if ($this->expectsJson($request)) {
            return Response::json([
                'ok'       => true,
                'message'  => 'Administrador inicial creado correctamente.',
                'redirect' => '/admin',
                'user'     => [
                    'email' => $email,
                    'name'  => $name,
                    'role'  => 'admin',
                ],
            ])->setStatus(201);
        }

        return redirect('/admin');
    }

    private function firstAdminExists(): bool
    {
        return User::firstWhere('role', 'admin') !== null;
    }
}