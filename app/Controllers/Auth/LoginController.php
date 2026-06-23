<?php

namespace App\Controllers\Auth;

use App\Models\User;
use Whis\Cryptic\Hasher;
use Whis\Http\Controller;
use Whis\Http\Request;
use Whis\Http\Response;

class LoginController extends Controller
{
    public function create()
    {
        if (!isGuest()) {
            return redirect('/');
        }

        return view('auth/login');
    }

    public function store(Request $request, Hasher $hasher)
    {
        /*
         * El CSRF lo valida el middleware antes de llegar aquí.
         */
        $data = $request->validate([
            'email'    => 'required|email',
            'password' => 'required',
        ], false, [
            'email' => [
                'required' => 'Completa tu correo electrónico.',
                'email'    => 'Escribe un correo electrónico válido.',
            ],
            'password' => [
                'required' => 'Completa tu contraseña.',
            ],
        ]);

        $user = User::firstWhere('email', $data['email']);

        if (is_null($user) || !$hasher->verify($data['password'], $user->password)) {
            if ($this->expectsJson($request)) {
                return Response::json([
                    'ok'      => false,
                    'message' => "Las credenciales no coinciden.",
                    'error'   => "Las credenciales no coinciden.",
                    'errors'  => [
                        'email' => "Las credenciales no coinciden.",
                    ],
                ])->setStatus(422);
            }

            return back()->withErrors([
                'email' => [
                    'email' => "Las credenciales no coinciden.",
                ],
            ]);
        }

        $user->login();

        if ($this->expectsJson($request)) {
            return Response::json([
                'ok'       => true,
                'message'  => 'Sesión iniciada correctamente.',
                'redirect' => '/',
            ]);
        }

        return redirect('/');
    }

    public function destroy()
    {
        if (isGuest()) {
            return redirect('/');
        }

        auth()->logout();

        return redirect('/');
    }
}