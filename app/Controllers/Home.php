<?php

namespace App\Controllers;

use Whis\Http\Controller;
use Whis\Http\Request;
use Whis\Http\Response;

class Home extends Controller
{
    public function create()
    {
        if (isGuest()) {
            return view('home', ['user' => 'Guest']);
        }

        return view('home', ['user' => auth()->name]);
    }

    public function store(Request $request)
    {
        /*
         * El CSRF NO se valida aquí.
         * Lo debe validar tu middleware CSRF.
         *
         * El form nuevo debe mandar:
         * - _token
         * - _csrf_key si tu framework lo usa
         * - header X-CSRF-Token
         * - header X-CSRF-Key si existe
         *
         * Eso ya lo hace tu JS cuando usas:
         * data-csrf="true"
         * data-csrf-field="_token"
         */

        $request->validate([
            'email' => 'required',
            'name'  => 'required',
            'files' => 'required',
        ]);

        $files = $request->file('files', [
            'type' => 'filetype:png/jpeg/jpg/pdf',
            'size' => 'filesize:1000000',
        ]);

        foreach ($files as $file) {
            $file->store();
        }

        if ($this->expectsJson()) {
            return Response::json([
                'ok'      => true,
                'message' => 'Formulario enviado correctamente.',
            ]);
        }

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