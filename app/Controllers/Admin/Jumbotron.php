<?php

namespace App\Controllers\Admin;

use Whis\Http\Controller;

class Jumbotron extends Controller
{
    public function index()
    {
        /*
         * Si este controlador ya está protegido con AuthMiddleware,
         * esta validación es opcional.
         *
         * Pero si la dejas, debe redirigir SOLO a invitados.
         */
        if (isGuest()) {
            return redirect('/login');
        }

        $user = auth();

        return view('pages/admin/jumbotron', 'Jumbotron', [
            'stats' => [
                'jumbotron_published' => 0,
                'projects_total'      => 0,
                'clients_active'      => 0,
                'messages_new'        => 0,
                'map_projects'        => 0,
                'map_offices'         => 0,
                'map_workshops'       => 0,
            ],

            'user' => $user,

            'recentProjects'  => [],
            'recentMessages'  => [],
            'jumbotronSlides' => [],
            'auditLogs'       => [],
            'mapStates'       => [],
        ], 'layouts/admin/layout');
    }
}