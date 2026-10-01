<?php

namespace App\Http\Controllers;

use App\Models\Negocio;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;

class SesionController extends Controller
{
    /** /n/ojitos: este equipo recuerda en qué negocio se trabaja */
    public function recordarNegocio(string $slug)
    {
        $neg = Negocio::where('slug', $slug)->where('activo', true)->firstOrFail();
        Cookie::queue(Cookie::forever('negocio', $neg->slug));

        return redirect()->route('login');
    }

    public function salir(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
