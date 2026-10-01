<?php

namespace App\Services;

use App\Models\CajaMovimiento;
use App\Models\TurnoCaja;
use App\Models\Usuario;
use App\Models\Venta;
use App\Support\Texto;
use Carbon\Carbon;

/**
 * Una caja cerrada no se toca. Si hay que quitar dinero que ya entró en un cuadre cerrado
 * (una venta anulada, un pago de fiado o a un proveedor que se borra), el cuadre de ese turno
 * queda como estaba y se anota el movimiento contrario en la caja de hoy.
 */
class CuadreCaja
{
    /** ¿Ese dinero ya entró en un cuadre cerrado? (su turno se cerró, o es de un día pasado sin turno abierto) */
    public static function cuadrado(?int $usuarioId, Carbon $at, string $fecha): bool
    {
        $t = TurnoCaja::where('usuario_id', $usuarioId)->where('abre_at', '<=', $at)
            ->where(fn ($q) => $q->whereNull('cierra_at')->orWhere('cierra_at', '>=', $at))
            ->orderByDesc('abre_at')->first();
        if ($t) {
            return $t->cierra_at !== null;
        }

        return $fecha < today()->toDateString();
    }

    /** Quita un movimiento de caja: si su turno sigue abierto, se marca como borrado; si ya se cuadró, se anota el contrario hoy. */
    public function quitar(CajaMovimiento $m, Usuario $por, string $motivo = ''): void
    {
        if (! self::cuadrado($m->usuario_id, $m->ocurrido_at, $m->fecha->toDateString())) {
            $m->delete();

            return;
        }
        CajaMovimiento::create([
            'uid' => Texto::nuevoUid(), 'fecha' => today()->toDateString(), 'tipo' => $m->tipo === 'ingreso' ? 'retiro' : 'ingreso',
            'concepto' => 'Corrección: '.$m->concepto, 'nota' => trim('Del '.$m->fecha->format('d/m').' (su caja ya estaba cerrada). '.$motivo),
            'monto' => $m->monto, 'metodo' => $m->metodo ?: 'efectivo', 'referencia' => $m->uid,
            'usuario_id' => $por->id, 'vendedor' => $por->nombre, 'ocurrido_at' => now(),
        ]);
    }

    /**
     * Una venta que se anula después de cuadrar su caja: su dinero se queda en ese cuadre y la devolución
     * sale de la caja de hoy (si fue al fiado no hubo dinero que devolver).
     *
     * @return bool si se anotó la devolución
     */
    public function devolverVenta(Venta $v, Usuario $por): bool
    {
        if (! self::cuadrado($v->usuario_id, $v->vendida_at, $v->fecha->toDateString())) {
            return false;
        }
        if ($v->metodo !== 'fiado' && $v->total > 0) {
            CajaMovimiento::create([
                'uid' => Texto::nuevoUid(), 'fecha' => today()->toDateString(), 'tipo' => 'retiro', 'concepto' => 'Devolución de venta anulada',
                'nota' => trim(($v->numero ?? '').' del '.$v->fecha->format('d/m')), 'monto' => $v->total, 'metodo' => $v->metodo ?: 'efectivo',
                'referencia' => $v->uid, 'usuario_id' => $por->id, 'vendedor' => $por->nombre, 'ocurrido_at' => now(),
            ]);
        }

        return true;
    }
}
