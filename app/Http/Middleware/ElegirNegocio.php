<?php

namespace App\Http\Middleware;

use App\Models\Negocio;
use App\Support\NegocioActual;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Elige el negocio de esta petición: el del usuario que entró; si nadie entró,
 * el que este equipo recuerda (/n/{slug}) o el único que exista.
 */
class ElegirNegocio
{
    public function handle(Request $request, Closure $next): Response
    {
        $actual = app(NegocioActual::class);
        $u = Auth::user();
        if ($u) {
            if (! $u->activo) {
                Auth::logout();
                $request->session()->invalidate();

                return redirect()->route('login');
            }
            $actual->set(Negocio::with('modulos')->find($u->negocio_id));
        } else {
            $slug = $request->cookie('negocio');
            $neg = $slug ? Negocio::where('slug', $slug)->first() : null;
            if (! $neg && Negocio::count() === 1) {
                $neg = Negocio::first();
            }
            $actual->set($neg);
        }

        return $next($request);
    }
}
