<?php
namespace App\Controllers;

use Whis\Http\Controller;
use Whis\Http\Request;
use Whis\Http\Response;

class Proyectos extends Controller
{
    public function entry()
    {

        return view('pages/project/entry', "Proyecto");
    }
}
