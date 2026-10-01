<?php

namespace App\Services;

use App\Models\CajaMovimiento;
use App\Models\Dia;
use App\Models\TurnoCaja;
use App\Models\Venta;
use Illuminate\Support\Collection;

/**
 * Cuentas de un día: cada persona abre su turno con su sencillo y lo cierra contando su efectivo.
 * Debe haber = sencillo + ventas en efectivo + ingresos − gastos − retiros (todo en efectivo y dentro de su turno).
 */
class CajaDia
{
    public Collection $ventas;

    public Collection $movs;

    public Collection $turnos;

    public ?Dia $dia;

    public function __construct(public string $fecha, ?int $soloUsuario = null)
    {
        $this->ventas = Venta::with('items')->where('fecha', $fecha)
            ->when($soloUsuario, fn ($q) => $q->where('usuario_id', $soloUsuario))->orderBy('vendida_at')->get();
        $this->movs = CajaMovimiento::where('fecha', $fecha)->orderBy('ocurrido_at')->get();
        $this->turnos = TurnoCaja::where('fecha', $fecha)->orderBy('abre_at')->get();
        $this->dia = Dia::where('fecha', $fecha)->first();
    }

    private static function efectivo($x): bool
    {
        return ($x->metodo ?: 'efectivo') === 'efectivo';
    }

    private static function enTurno($x, TurnoCaja $t, string $campo): bool
    {
        $at = $x->{$campo};

        return $at->gte($t->abre_at) && (! $t->cierra_at || $at->lte($t->cierra_at));
    }

    public function turno(TurnoCaja $t): array
    {
        $V = $this->ventas->filter(fn ($v) => $v->usuario_id === $t->usuario_id && self::enTurno($v, $t, 'vendida_at'));
        $M = $this->movs->filter(fn ($m) => $m->usuario_id === $t->usuario_id && self::enTurno($m, $t, 'ocurrido_at') && self::efectivo($m));
        $sum = fn ($tp) => (int) $M->where('tipo', $tp)->sum('monto');
        $ventas = (int) $V->filter(fn ($v) => self::efectivo($v))->sum('total');
        $ing = $sum('ingreso');
        $gas = $sum('gasto');
        $ret = $sum('retiro');

        return ['ventas' => $ventas, 'n' => $V->count(), 'ing' => $ing, 'gas' => $gas, 'ret' => $ret,
            'esperado' => $t->inicial + $ventas + $ing - $gas - $ret];
    }

    /** efectivo registrado fuera de todo turno (se vendió sin abrir caja) */
    public function sinTurno(): array
    {
        if ($this->turnos->isEmpty() && $this->dia?->caja_inicial !== null) {
            return ['total' => 0, 'quien' => []];
        }
        $fuera = function ($x, $campo) {
            if (! $x->usuario_id) {
                return $this->turnos->isEmpty();
            }

            return ! $this->turnos->contains(fn ($t) => $t->usuario_id === $x->usuario_id && self::enTurno($x, $t, $campo));
        };
        $V = $this->ventas->filter(fn ($v) => self::efectivo($v) && $fuera($v, 'vendida_at'));
        $M = $this->movs->filter(fn ($m) => self::efectivo($m) && $fuera($m, 'ocurrido_at'));
        $total = (int) $V->sum('total') + (int) $M->sum(fn ($m) => $m->tipo === 'ingreso' ? $m->monto : -$m->monto);

        return ['total' => $total, 'quien' => $V->pluck('vendedor')->merge($M->pluck('vendedor'))->map(fn ($n) => $n ?: 'Sin usuario')->unique()->values()->all()];
    }

    /** caja general de la forma antigua (un solo sencillo para todos) */
    public function legado(): int
    {
        $V = $this->ventas->filter(fn ($v) => self::efectivo($v) && ! $v->usuario_id);
        $M = $this->movs->filter(fn ($m) => ! $m->usuario_id && self::efectivo($m));
        $s = fn ($tp) => (int) $M->where('tipo', $tp)->sum('monto');

        return (int) ($this->dia?->caja_inicial ?? 0) + (int) $V->sum('total') + $s('ingreso') - $s('gasto') - $s('retiro');
    }

    /** resumen como dayStats(): total propio, tasas, métodos, gastos, efectivo esperado */
    public function resumen(): array
    {
        $V = $this->ventas;
        $met = [];
        foreach ($V as $v) {
            $met[$v->metodo ?: 'efectivo'] = ($met[$v->metodo ?: 'efectivo'] ?? 0) + $v->total;
        }
        $efe = fn ($tp) => (int) $this->movs->where('tipo', $tp)->filter(fn ($m) => self::efectivo($m))->sum('monto');
        $esperado = $this->turnos->isNotEmpty()
            ? $this->turnos->sum(fn ($t) => $this->turno($t)['esperado']) + ($this->dia?->caja_inicial !== null ? $this->legado() : 0)
            : (int) ($this->dia?->caja_inicial ?? 0) + ($met['efectivo'] ?? 0) + $efe('ingreso') - $efe('gasto') - $efe('retiro');

        return [
            'total' => (int) $V->sum(fn ($v) => $v->propio()),
            'tasas' => (int) $V->sum(fn ($v) => $v->terceros()),
            'n' => $V->count(),
            'metodos' => $met,
            'gastos' => (int) $this->movs->where('tipo', 'gasto')->sum('monto'),
            'esperado' => $esperado,
            'menores' => $V->filter(fn ($v) => $v->vaAlCierre()),
            'pendientes' => $V->filter(fn ($v) => $v->faltaComprobante()),
        ];
    }
}
