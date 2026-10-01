<?php

namespace App\Services;

use App\Models\Actividad;
use App\Models\Usuario;
use App\Support\NegocioActual;
use App\Support\Texto;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/** Registro de actividad: quién hizo qué, cuándo y desde qué equipo. */
class Bitacora
{
    public static function registrar(string $tipo, string $detalle, ?Usuario $quien = null): void
    {
        $negocioId = app(NegocioActual::class)->id();
        if (! $negocioId) {
            return;
        }
        $u = $quien ?? Auth::user();
        $equipo = request()?->cookie('equipo');
        Actividad::create([
            'negocio_id' => $negocioId,
            'uid' => Texto::nuevoUid(),
            'fecha' => today(),
            'tipo' => $tipo,
            'detalle' => Str::limit($detalle, 237),
            'usuario_id' => $u?->id,
            'vendedor' => $u?->nombre,
            'equipo' => $equipo ? Str::limit($equipo, 27) : null,
            'ocurrido_at' => now(),
        ]);
    }
}
