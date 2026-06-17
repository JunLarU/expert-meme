<?php

namespace App\Controllers\Auth;

use App\Models\User;
use Whis\Cryptic\Hasher;
use Whis\Http\Controller;
use Whis\Http\Request;
use Whis\Http\Response;
use Whis\Storage\File;

class RegisterController extends Controller
{
    public function create()
    {
        if (!isGuest()) {
            return redirect('/');
        }

        /*
         * Ya no generes token manual aquí.
         * El formulario usa:
         *
         * @csrf('register')
         *
         * y el middleware CSRF se encarga de validarlo.
         */
        return view('auth/register');
    }

    public function store(Request $request, Hasher $hasher)
    {
        /*
         * Validación principal del registro.
         *
         * filesquantity:,1
         * - Sin mínimo.
         * - Máximo 1 archivo.
         * - 0 archivos es válido.
         *
         * filetype:png/jpeg/jpg
         * - Solo imagen.
         *
         * filesize:1mb
         * - Máximo 1 MB por archivo.
         */
        $data = $request->validate([
            'email'            => 'required|email',
            'name'             => 'required',
            'role'             => 'required',
            'password'         => 'required',
            'confirm_password' => 'required',

            'files' => [
                'filesquantity:,1',
                'filetype:png/jpeg/jpg',
                'filesize:1mb',
            ],
        ]);

        /*
         * Email duplicado.
         */
        if (User::firstWhere('email', $data['email'])) {
            return $this->validationError($request, [
                'email' => [
                    'email' => 'Email already exists',
                ],
            ]);
        }

        /*
         * Confirmación de contraseña.
         */
        if ($data['password'] !== $data['confirm_password']) {
            return $this->validationError($request, [
                'confirm_password' => [
                    'confirm_password' => "Password doesn't match",
                ],
            ]);
        }

        /*
         * Archivos validados.
         */
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
                "private",
                false,
                "storage/uploads",
                false,
                "storage/uploads"
            );

            /*
             * Solo permitimos 1 archivo.
             */
            break;
        }

        /*
         * Payload limpio para BD.
         * No mandes confirm_password ni files a User::create().
         */
        $payload = [
            'email'    => $data['email'],
            'name'     => $data['name'],
            'role'     => $data['role'],
            'password' => $hasher->hash($data['password']),
        ];

        /*
         * Ajusta este nombre según tu columna real.
         *
         * Si tu columna se llama foto, déjalo así.
         * Si se llama avatar, profile_picture, image, etc.,
         * cambia 'foto' por el nombre correcto.
         */
        if ($profilePicture !== null) {
            $payload['foto'] = $profilePicture;
        }

        User::create($payload);

        $user = User::firstWhere('email', $payload['email']);

        if ($user) {
            $user->login();
        }

        if ($this->expectsJson($request)) {
            return Response::json([
                'ok'       => true,
                'message'  => 'Cuenta creada correctamente.',
                'redirect' => '/',
                'user'     => [
                    'email' => $payload['email'],
                    'name'  => $payload['name'],
                    'role'  => $payload['role'],
                    'foto'  => $profilePicture,
                ],
            ]);
        }

        return redirect('/');
    }
}