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
        if (! isGuest()) {
            return redirect('/');
        }

        /*
         * Ya no necesitas generar manualmente el token aquí
         * si estás usando @csrf o @csrf('login') y tu middleware CSRF.
         */
        return view('auth/login');
    }

    public function store(Request $request, Hasher $hasher)
    {
        /*
         * Se conserva validate().
         * El CSRF lo debe validar el middleware antes de llegar aquí.
         */
        $data = $request->validate([
            'email'    => 'required|email',
            'password' => 'required',
        ]);

        $user = User::firstWhere('email', $data['email']);

        if (is_null($user) || ! $hasher->verify($data['password'], $user->password)) {
            if ($this->expectsJson()) {
                return Response::json([
                    'ok' => false,
                    'error' => "Credentials don't match",
                    'errors' => [
                        'email' => "Credentials don't match",
                    ],
                ])->setStatus(422);
            }

            return back()->withErrors([
                'email' => [
                    'email' => "Credentials don't match",
                ],
            ]);
        }

        $user->login();

        if ($this->expectsJson()) {
            return Response::json([
                'ok' => true,
                'message' => 'Sesión iniciada correctamente.',
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

    private function expectsJson(): bool
    {
        $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
        $requestedWith = $_SERVER['HTTP_X_REQUESTED_WITH'] ?? '';

        return str_contains($accept, 'application/json')
            || strtolower($requestedWith) === 'xmlhttprequest';
    }
}