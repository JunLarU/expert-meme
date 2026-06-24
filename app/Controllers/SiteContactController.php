<?php

namespace App\Controllers\SiteApi;

use Whis\Http\Controller;
use Whis\Http\Request;
use Whis\Http\Response;

class SiteContactController extends Controller
{
    public function send(Request $request): Response
    {
        $data = [
            'name'    => trim((string) $request->data('name')),
            'email'   => trim((string) $request->data('email')),
            'phone'   => trim((string) $request->data('phone')),
            'message' => trim((string) $request->data('message')),
        ];

        $errors = $this->validateContact($data);

        if ($errors) {
            return Response::json([
                'ok'      => false,
                'message' => 'Revisa los campos marcados.',
                'errors'  => $errors,
            ])->setStatus(422);
        }

        /*
         * Aquí puedes:
         * - guardar en BD
         * - mandar correo
         * - crear lead
         * - notificar por Slack/WhatsApp/etc.
         */

        return Response::json([
            'ok'      => true,
            'message' => 'Mensaje enviado correctamente.',
        ]);
    }

    private function validateContact(array $data): array
    {
        $errors = [];

        if ($data['name'] === '') {
            $errors['name'] = 'Escribe tu nombre.';
        } elseif (mb_strlen($data['name']) < 3) {
            $errors['name'] = 'El nombre debe tener al menos 3 caracteres.';
        }

        if ($data['email'] === '') {
            $errors['email'] = 'Escribe tu correo.';
        } elseif (! filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Escribe un correo válido.';
        }

        if ($data['message'] === '') {
            $errors['message'] = 'Escribe tu mensaje.';
        } elseif (mb_strlen($data['message']) < 10) {
            $errors['message'] = 'El mensaje debe tener al menos 10 caracteres.';
        }

        return $errors;
    }
}