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
        ]);

        $user = User::firstWhere('email', $data['email']);

        if (is_null($user) || !$hasher->verify($data['password'], $user->password)) {
            if ($this->expectsJson($request)) {
                return Response::json([
                    'ok'      => false,
                    'message' => "Credentials don't match",
                    'error'   => "Credentials don't match",
                    'errors'  => [
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