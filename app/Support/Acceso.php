<?php

namespace App\Support;

use App\Models\Usuario;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

/** Qué módulos puede abrir el usuario que entró (como allowed() del sistema anterior). */
class Acceso
{
    public static function usuario(): ?Usuario
    {
        return Auth::user();
    }

    public static function puede(string $permiso): bool
    {
        return (bool) self::usuario()?->puede($permiso);
    }

    public static function modulo(string $k): bool
    {
        $u = self::usuario();
        if (! $u) {
            return false;
        }
        if ($k === 'inicio') {
            return true;
        }
        if ($k === 'usuarios') {
            return $u->esAdmin();
        }
        $neg = app(NegocioActual::class)->get();
        if ($neg && ! $neg->moduloActivo($k)) {
            return false;
        }

        return $u->puede($k);
    }

    /** Módulos del menú que ya existen en esta versión y el usuario puede abrir */
    public static function menu(): array
    {
        $out = [];
        foreach (Catalogos::MODULOS as $k => [$titulo, $seccion, $desc, $ruta]) {
            if ($ruta && Route::has($ruta) && self::modulo($k)) {
                $out[$k] = ['titulo' => $titulo, 'seccion' => $seccion, 'desc' => $desc, 'ruta' => $ruta];
            }
        }

        return $out;
    }
}
