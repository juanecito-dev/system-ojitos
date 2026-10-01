<?php

namespace App\Services;

use App\Models\CajaMovimiento;
use App\Models\Dia;
use App\Models\TurnoCaja;
use App\Models\Venta;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Cuentas de un día: cada persona abre su turno con su sencillo y lo cierra contando su efectivo.
 * Debe haber = sencillo + ventas en efectivo + ingresos − gastos − retiros (todo en efectivo y dentro de su turno).
 *
 * «ventas» son las ventas del día que siguen valiendo. Para el efectivo cuentan también las anuladas
 * después de cuadrar su caja (devuelta_en_caja): ese dinero sí entró, y su devolución sale como retiro
 * de la caja del día en que se anuló.
 */
class CajaDia
{
    public Collection $ventas;

    /** ventas cuyo dinero entró a la caja de este día (las que valen + las anuladas después de cuadrar) */
    public Collection $ventasCaja;

    public Collection $movs;

    public Collection $turnos;

    /** turnos que estuvieron abiertos en algún momento de este día (también el que se abrió ayer y pasó la medianoche) */
    public Collection $cubren;

    public ?Dia $dia;

    public function __construct(public string $fecha, ?int $soloUsuario = null)
    {
        $this->ventasCaja = Venta::conAnuladas()->with('items')->where('fecha', $fecha)
            ->where(fn ($q) => $q->whereNull('anulada_at')->orWhere('devuelta_en_caja', true))
            ->when($soloUsuario, fn ($q) => $q->where('usuario_id', $soloUsuario))->orderBy('vendida_at')->get();
        $this->ventas = $this->ventasCaja->whereNull('anulada_at')->values();
        $this->movs = CajaMovimiento::where('fecha', $fecha)->orderBy('ocurrido_at')->get();
        $this->turnos = TurnoCaja::where('fecha', $fecha)->orderBy('abre_at')->get();
        $d = Carbon::parse($fecha);
        $this->cubren = TurnoCaja::where('abre_at', '<=', $d->copy()->endOfDay())
            ->where(fn ($q) => $q->whereNull('cierra_at')->orWhere('cierra_at', '>=', $d->copy()->startOfDay()))->get();
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

    /**
     * Cuadre de un turno, por su horario (de la apertura al cierre, aunque pase la medianoche):
     * lo que vendió y movió esa persona mientras su caja estuvo abierta.
     */
    public function turno(TurnoCaja $t): array
    {
        $hasta = $t->cierra_at ?? now();
        $V = Venta::conAnuladas()->where('usuario_id', $t->usuario_id)->whereBetween('vendida_at', [$t->abre_at, $hasta])
            ->where(fn ($q) => $q->whereNull('anulada_at')->orWhere('devuelta_en_caja', true))->get();
        $M = CajaMovimiento::where('usuario_id', $t->usuario_id)->whereBetween('ocurrido_at', [$t->abre_at, $hasta])->get()
            ->filter(fn ($m) => self::efectivo($m));
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

            return ! $this->cubren->contains(fn ($t) => $t->usuario_id === $x->usuario_id && self::enTurno($x, $t, $campo));
        };
        $V = $this->ventasCaja->filter(fn ($v) => self::efectivo($v) && $fuera($v, 'vendida_at'));
        $M = $this->movs->filter(fn ($m) => self::efectivo($m) && $fuera($m, 'ocurrido_at'));
        $total = (int) $V->sum('total') + (int) $M->sum(fn ($m) => $m->tipo === 'ingreso' ? $m->monto : -$m->monto);

        return ['total' => $total, 'quien' => $V->pluck('vendedor')->merge($M->pluck('vendedor'))->map(fn ($n) => $n ?: 'Sin usuario')->unique()->values()->all()];
    }

    /** caja general de la forma antigua (un solo sencillo para todos) */
    public function legado(): int
    {
        $V = $this->ventasCaja->filter(fn ($v) => self::efectivo($v) && ! $v->usuario_id);
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
        $ventasEfe = (int) $this->ventasCaja->filter(fn ($v) => self::efectivo($v))->sum('total');
        $esperado = $this->turnos->isNotEmpty()
            ? $this->turnos->sum(fn ($t) => $this->turno($t)['esperado']) + ($this->dia?->caja_inicial !== null ? $this->legado() : 0)
            : (int) ($this->dia?->caja_inicial ?? 0) + $ventasEfe + $efe('ingreso') - $efe('gasto') - $efe('retiro');

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
