<?php
namespace App\Controllers;

use Whis\Http\Controller;
use Whis\Http\Request;
use Whis\Http\Response;

class Home extends Controller
{
    public function home()
    {

        return view('pages/main/home', "Inicio");
    }
    public function contactSend(Request $request)
    {
        $data = $request->validate([
            'name'    => 'required',
            'email'   => 'required|email',
            'message' => 'required',
            'asunto' => 'required',
        ],true, [
            'name' => [
                'required' => 'El nombre es obligatorio.',
            ],
            'email' => [
                'required' => 'El correo electrónico es obligatorio.',
                'email' => 'El correo electrónico no es válido.',
            ],
            'message' => [
                'required' => 'El mensaje es obligatorio.',
            ],
            'asunto' => [
                'required' => 'El asunto es obligatorio.',
            ],
        ]);

        return Response::json([
            'ok'      => true,
            'message' => 'Tu mensaje fue enviado correctamente.',
        ]);
    }

    public function nosotros()
    {
        return view('pages/main/nosotros', "Nosotros");
    }

    public function servicios()
    {
        return view('pages/main/servicios', "Servicios");
    }

     public function proyectos()
    {
        return view('pages/main/proyectos', "Proyectos");
    }

    public function contacto()
    {
        return view('pages/main/contacto', "Contacto");
    }

    public function store()
    {
        return view('pages/main/form', "Formulario");
    } 
}
