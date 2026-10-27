<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;

class InicioController extends Controller
{
    public function __invoke(Request $request): View
    {
        $usuario = $request->user()->load('role');

        return view('inicio', compact('usuario'));
    }
}
