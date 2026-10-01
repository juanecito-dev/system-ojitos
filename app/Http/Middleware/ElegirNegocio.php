<?php

namespace App\Http\Middleware;

use App\Models\Negocio;
use App\Support\NegocioActual;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Elige el negocio de esta petición: el del usuario que entró; si nadie entró, el que este
 * equipo recuerda (/n/{slug}) o, en una instalación de un solo negocio, ese negocio.
 * Un negocio suspendido (activo = false) no se usa: quien estaba adentro sale.
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
            $neg = Negocio::with('modulos')->find($u->negocio_id);
            if (! $neg?->activo) {
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return redirect()->route('login')->with('toast', Negocio::SUSPENDIDO);
            }
            $actual->set($neg);
        } else {
            $slug = $request->cookie('negocio');
            $neg = $slug ? Negocio::where('slug', $slug)->where('activo', true)->first() : null;
            if (! $neg && config('ojitos.negocio_unico') && Negocio::count() === 1) {
                $neg = Negocio::where('activo', true)->first();
            }
            $actual->set($neg);
        }

        return $next($request);
    }
}
