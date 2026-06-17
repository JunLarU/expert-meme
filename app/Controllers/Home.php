<?php

namespace App\Controllers;

use Whis\Http\Controller;
use Whis\Http\Request;
use Whis\Http\Response;
use Whis\Storage\File;

class Home extends Controller
{
    public function create()
    {
        return view('home',"Inicio");
        //return view('home',"Inicio", ['user' => auth()->email]);
    }
    public function contactSend(Request $request)
    {
        $data = $request->validate([
            'name'    => 'required',
            'email'   => 'required|email',
            'message' => 'required',
            'subject' => 'required',
        ]);

        /*
         * Aquí puedes enviar correo, guardar en BD, mandar notificación, etc.
         */

        if ($this->expectsJson($request)) {
            return Response::json([
                'ok'      => true,
                'message' => 'Tu mensaje fue enviado correctamente.',
            ]);
        }

        return redirect('/');
    }

    // public function store(Request $request)
    // {
    //     /*
    //      * Validación completa.
    //      *
    //      * filesquantity:,2  => mínimo libre, máximo 2 archivos.
    //      * Por eso 0 archivos también es válido.
    //      */
    //     $request->validate([
    //         'email' => 'required',
    //         'name'  => 'required',

    //         'files' => [
    //             'filesquantity:,1',
    //             'filetype:png/jpeg/jpg',
    //             'filesize:1mb',
    //         ],
    //     ]);

    //     /*
    //      * Obtener archivos ya validados.
    //      */
    //     $files = $request->file('files');

    //     if ($files instanceof File) {
    //         $files = [$files];
    //     }

    //     if (!is_array($files)) {
    //         $files = [];
    //     }

    //     $storedFiles = [];

    //     foreach ($files as $file) {
    //         if (!$file instanceof File) {
    //             continue;
    //         }

    //         /*
    //          * Si hubo error de subida, no intentes almacenarlo.
    //          * Normalmente filesize/filetype ya deberían haberlo detectado,
    //          * pero esto evita guardar archivos corruptos.
    //          */
    //         if ($file->hasUploadError()) {
    //             continue;
    //         }

    //         $storedFiles[] = $file->store(
    //             "profile_pictures",
    //             false,
    //             "storage/uploads",
    //             false,
    //             "storage/uploads"
    //         );
    //     }

    //     if ($this->expectsJson($request)) {
    //         return Response::json([
    //             'ok'      => true,
    //             'message' => 'Formulario enviado correctamente.',
    //             'files'   => $storedFiles,
    //         ]);
    //     }

    //     return redirect('/');
    // }
}