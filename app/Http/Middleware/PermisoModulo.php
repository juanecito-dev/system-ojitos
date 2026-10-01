<?php

namespace App\Http\Middleware;

use App\Support\Acceso;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Solo deja entrar a un módulo si el rol lo permite y el negocio lo usa. */
class PermisoModulo
{
    public function handle(Request $request, Closure $next, string $modulo): Response
    {
        if (! Acceso::modulo($modulo)) {
            return redirect()->route('inicio')->with('toast', 'Tu usuario no puede entrar a ese módulo.');
        }

        return $next($request);
    }
}
