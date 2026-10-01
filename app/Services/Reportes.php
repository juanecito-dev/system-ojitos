<?php

namespace App\Services;

use App\Models\CajaMovimiento;
use App\Models\Cliente;
use App\Models\ClienteMovimiento;
use App\Models\Pedido;
use App\Models\TurnoCaja;
use App\Models\VentaAnulada;
use App\Support\Dinero;
use App\Support\NegocioActual;
use App\Support\Texto;
use App\Support\Valida;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Reportes (repCalc del sistema anterior): todas las cuentas de un periodo, calculadas en el servidor
 * directamente de las ventas, la caja, las compras y el inventario (sin los resúmenes diarios de antes).
 * Ganancia = vendido − costo de lo vendido − gastos − pérdidas de inventario.
 */
class Reportes
{
    public const RANGOS = ['hoy' => 'Hoy', '7' => '7 días', '30' => '30 días', 'mes' => 'Este mes', 'mespas' => 'Mes pasado', 'anio' => 'Este año', 'custom' => 'Elegir fechas'];

    /** documentos redactados que se venden en la caja: los productos con estos roles (no los ejemplares adicionales) */
    public const DOCUMENTOS = ['redaccion_pagina', 'redaccion_documento', 'redaccion_cv'];

    public static function dias(string $a, string $b): int
    {
        return (int) Carbon::parse($a)->diffInDays(Carbon::parse($b)) + 1;
    }

    /** [desde, hasta] del periodo elegido */
    public static function rango(string $r, ?string $desde = null, ?string $hasta = null): array
    {
        $t = today();

        return match ($r) {
            'hoy' => [$t->toDateString(), $t->toDateString()],
            '7' => [$t->copy()->subDays(6)->toDateString(), $t->toDateString()],
            '30' => [$t->copy()->subDays(29)->toDateString(), $t->toDateString()],
            'mespas' => [$t->copy()->subMonthNoOverflow()->startOfMonth()->toDateString(), $t->copy()->subMonthNoOverflow()->endOfMonth()->toDateString()],
            'anio' => [$t->copy()->startOfYear()->toDateString(), $t->toDateString()],
            'custom' => (function () use ($desde, $hasta, $t) {
                $ok = fn ($d) => Valida::fecha($d) && $d <= $t->toDateString();
                $a = $ok($desde) ? $desde : $t->copy()->subDays(6)->toDateString();
                $b = $ok($hasta) ? $hasta : $t->toDateString();

                return $a <= $b ? [$a, $b] : [$b, $a];
            })(),
            default => [$t->copy()->startOfMonth()->toDateString(), $t->toDateString()],
        };
    }

    /**
     * Periodo con el que se compara. «anterior»: el mes anterior hasta el mismo día, o los días justo antes
     * (en periodos largos, el año pasado). «anio»: las mismas fechas del año pasado.
     */
    public static function previo(string $a, string $b, string $r, string $modo = 'anterior'): array
    {
        $A = Carbon::parse($a);
        $B = Carbon::parse($b);
        if ($modo === 'anio' || $r === 'anio' || self::dias($a, $b) > 92) {
            return [$A->copy()->subYearNoOverflow()->toDateString(), $B->copy()->subYearNoOverflow()->toDateString()];
        }
        if ($r === 'mes') {
            $pa = $A->copy()->subMonthNoOverflow()->startOfMonth();
            $pb = $pa->copy()->addDays(self::dias($a, $b) - 1);

            return [$pa->toDateString(), $pb->min($pa->copy()->endOfMonth())->toDateString()];
        }
        if ($r === 'mespas') {
            return [$A->copy()->subMonthNoOverflow()->startOfMonth()->toDateString(), $A->copy()->subMonthNoOverflow()->endOfMonth()->toDateString()];
        }
        $n = self::dias($a, $b);

        return [$A->copy()->subDays($n)->toDateString(), $A->copy()->subDay()->toDateString()];
    }

    private function nid(): int
    {
        return app(NegocioActual::class)->obligatorio()->id;
    }

    private static function vend(): array
    {
        return ['v' => 0, 'n' => 0, 'desc' => 0, 'anul' => 0, 'anulM' => 0, 'dif' => 0];
    }

    /** todas las cuentas de un periodo */
    public function calcular(string $a, string $b): array
    {
        $nid = $this->nid();
        $t0 = Carbon::parse($a)->startOfDay();
        $t1 = Carbon::parse($b)->endOfDay();
        $R = ['a' => $a, 'b' => $b, 'ventas' => 0, 'bruto' => 0, 'n' => 0, 'tasas' => 0, 'desc' => 0, 'fiado' => 0, 'cogs' => 0.0, 'cub' => 0, 'gastos' => 0, 'gasC' => [],
            'ingresos' => 0, 'retiros' => 0, 'pagProv' => 0, 'pagCaja' => 0, 'cobFiado' => 0, 'perd' => 0.0, 'met' => [], 'grupos' => [], 'prods' => [], 'vend' => [],
            'clientes' => [], 'heat' => [], 'porDia' => [], 'porMes' => [], 'docs' => [], 'anul' => [], 'sinCobrar' => 0, 'sinCobrarS' => 0, 'conCliente' => 0, 'dias' => 0];
        $mes = function (string $f) use (&$R): string {
            $k = substr($f, 0, 7);
            $R['porMes'][$k] ??= ['ventas' => 0, 'cogs' => 0.0, 'gastos' => 0, 'n' => 0];

            return $k;
        };

        // ventas (lo propio: sin las tasas pagadas a terceros)
        $terc = DB::table('venta_items as i')->join('ventas as v', 'v.id', '=', 'i.venta_id')->whereNull('v.anulada_at')->where('v.negocio_id', $nid)
            ->whereBetween('v.fecha', [$a, $b])->where('i.tercero', true)->groupBy('i.venta_id')->selectRaw('i.venta_id AS id, SUM(i.subtotal) AS s')->pluck('s', 'id');
        $V = DB::table('ventas')->where('negocio_id', $nid)->whereNull('anulada_at')->whereBetween('fecha', [$a, $b])
            ->get(['id', 'fecha', 'total', 'descuento', 'metodo', 'cliente_id', 'vendedor', 'vendida_at']);
        foreach ($V as $v) {
            $f = substr((string) $v->fecha, 0, 10);
            $tc = (int) ($terc[$v->id] ?? 0);
            $p = (int) $v->total - $tc;
            $m = $v->metodo ?: 'efectivo';
            $R['ventas'] += $p;
            $R['n']++;
            $R['tasas'] += $tc;
            $R['desc'] += (int) $v->descuento;
            $R['fiado'] += $m === 'fiado' ? $p : 0;
            $R['met'][$m] = ($R['met'][$m] ?? 0) + $p;
            if ($v->cliente_id) {
                $R['conCliente'] += $p;
                $R['clientes'][$v->cliente_id] ??= ['n' => 0, 'v' => 0];
                $R['clientes'][$v->cliente_id]['n']++;
                $R['clientes'][$v->cliente_id]['v'] += $p;
            }
            $vk = $v->vendedor ?: 'Sin usuario';
            $R['vend'][$vk] ??= self::vend();
            $R['vend'][$vk]['v'] += $p;
            $R['vend'][$vk]['n']++;
            $R['vend'][$vk]['desc'] += (int) $v->descuento;
            $t = Carbon::parse($v->vendida_at);
            $hk = $t->dayOfWeek.'-'.$t->hour;
            $R['heat'][$hk] = ($R['heat'][$hk] ?? 0) + $p;
            $R['porDia'][$f] = ($R['porDia'][$f] ?? 0) + $p;
            $k = $mes($f);
            $R['porMes'][$k]['ventas'] += $p;
            $R['porMes'][$k]['n']++;
        }
        $R['dias'] = count(array_filter($R['porDia']));

        // lo vendido por producto y grupo, con su costo cuando se conoce
        $I = DB::table('venta_items as i')->join('ventas as v', 'v.id', '=', 'i.venta_id')->whereNull('v.anulada_at')->leftJoin('productos as p', 'p.id', '=', 'i.producto_id')
            ->where('v.negocio_id', $nid)->whereBetween('v.fecha', [$a, $b])->where('i.tercero', false)
            ->groupBy('i.nombre', 'i.producto_uid', 'p.grupo', 'p.rol', DB::raw('substr(v.fecha, 1, 7)'))
            ->selectRaw('i.nombre, i.producto_uid AS uid, p.grupo, p.rol, substr(v.fecha, 1, 7) AS mes, COUNT(*) AS lineas, SUM(i.cantidad) AS cant, SUM(i.subtotal) AS v,
                SUM(CASE WHEN i.costo > 0 THEN i.costo * i.cantidad ELSE 0 END) AS c, SUM(CASE WHEN i.costo > 0 THEN i.subtotal ELSE 0 END) AS cub')->get();
        foreach ($I as $x) {
            $g = in_array($x->uid, ['pedido', 'encargo'], true) ? 'Pedidos y encargos' : ($x->grupo ?: 'Otros');
            $R['grupos'][$g] ??= ['v' => 0, 'c' => 0.0, 'cub' => 0];
            $R['grupos'][$g]['v'] += (int) $x->v;
            $R['grupos'][$g]['c'] += (float) $x->c;
            $R['grupos'][$g]['cub'] += (int) $x->cub;
            $R['prods'][$x->nombre] ??= ['cant' => 0.0, 'v' => 0, 'c' => 0.0, 'cub' => 0];
            $R['prods'][$x->nombre]['cant'] += (float) $x->cant;
            $R['prods'][$x->nombre]['v'] += (int) $x->v;
            $R['prods'][$x->nombre]['c'] += (float) $x->c;
            $R['prods'][$x->nombre]['cub'] += (int) $x->cub;
            if (in_array($x->rol, self::DOCUMENTOS, true)) {
                $R['docs'][$x->nombre] ??= ['n' => 0, 'v' => 0];
                $R['docs'][$x->nombre]['n'] += (int) $x->lineas;
                $R['docs'][$x->nombre]['v'] += (int) $x->v;
            }
            $R['bruto'] += (int) $x->v;
            $R['cogs'] += (float) $x->c;
            $R['cub'] += (int) $x->cub;
            $R['porMes'][$x->mes] ??= ['ventas' => 0, 'cogs' => 0.0, 'gastos' => 0, 'n' => 0];
            $R['porMes'][$x->mes]['cogs'] += (float) $x->c;
        }

        // caja: gastos, otros ingresos, retiros y pagos a proveedores sin compra registrada
        foreach (CajaMovimiento::whereBetween('fecha', [$a, $b])->get(['fecha', 'tipo', 'concepto', 'monto', 'referencia']) as $x) {
            if ($x->tipo === 'gasto') {
                $R['gastos'] += $x->monto;
                $R['gasC'][$x->concepto] = ($R['gasC'][$x->concepto] ?? 0) + $x->monto;
                $R['porMes'][$mes($x->fecha->toDateString())]['gastos'] += $x->monto;
            } elseif ($x->tipo === 'ingreso' && $x->concepto !== 'Pago de fiado') {
                $R['ingresos'] += $x->monto;
            } elseif ($x->tipo === 'retiro' && $x->concepto !== 'Pago a proveedor') {
                $R['retiros'] += $x->monto;
            } elseif ($x->tipo === 'retiro' && ! $x->referencia) {
                $R['pagCaja'] += $x->monto;
            }
        }
        foreach (TurnoCaja::whereBetween('fecha', [$a, $b])->whereNotNull('cierra_at')->whereNotNull('contado')->whereNotNull('esperado')->get() as $t) {
            $vk = $t->vendedor ?: 'Sin usuario';
            $R['vend'][$vk] ??= self::vend();
            $R['vend'][$vk]['dif'] += $t->contado - $t->esperado;
        }
        foreach (VentaAnulada::whereBetween('fecha', [$a, $b])->orderByDesc('anulada_at')->get() as $x) {
            $R['anul'][] = $x;
            $vk = $x->vendedor_original ?: 'Sin usuario';
            $R['vend'][$vk] ??= self::vend();
            $R['vend'][$vk]['anul']++;
            $R['vend'][$vk]['anulM'] += (int) $x->total;
        }
        $sc = app(Contadores::class)->sinCobrar($a, $b);
        $R['sinCobrar'] = $sc['copias'];
        $R['sinCobrarS'] = $sc['monto'];
        $R['cobFiado'] = (int) ClienteMovimiento::where('tipo', 'abono')->whereBetween('ocurrido_at', [$t0, $t1])->sum('monto');
        $R['pagProv'] = (int) DB::table('compra_pagos as p')->join('compras as c', 'c.id', '=', 'p.compra_id')->where('c.negocio_id', $nid)->whereNull('p.deleted_at')
            ->whereBetween('p.pagado_at', [$t0, $t1])->sum('p.monto');
        $R['perd'] = app(Inventario::class)->perdidas($t0, $t1);
        ksort($R['porMes']);

        return self::fin($R);
    }

    public static function fin(array $R): array
    {
        $R['ganancia'] = (int) round($R['ventas'] - $R['cogs'] - $R['gastos'] - $R['perd']);
        $R['cobertura'] = $R['bruto'] ? (int) round($R['cub'] / $R['bruto'] * 100) : 0;
        $R['entra'] = $R['ventas'] - $R['fiado'] + $R['cobFiado'] + $R['ingresos'];
        $R['pagTot'] = $R['pagProv'] + $R['pagCaja'];
        $R['sale'] = $R['gastos'] + $R['pagTot'] + $R['retiros'];

        return $R;
    }

    /** clientes nuevos, mejores clientes, cotizaciones y pedidos del periodo */
    public function clientesYPedidos(array $R): array
    {
        $t0 = Carbon::parse($R['a'])->startOfDay();
        $t1 = Carbon::parse($R['b'])->endOfDay();
        $top = collect($R['clientes'])->sortByDesc('v')->take(5);
        $nombres = Cliente::whereIn('id', $top->keys())->pluck('nombre', 'id');
        $cot = Pedido::where('cotizada', true)->whereBetween('fecha', [$R['a'], $R['b']])->get(['etapa']);
        $ent = Pedido::where('directa', false)->whereBetween('entregado_at', [$t0, $t1])->get(['entregado_at', 'aceptado_at', 'creado_at']);

        return [
            'nuevos' => Cliente::whereBetween('created_at', [$t0, $t1])->count(),
            'top' => $top->map(fn ($x, $id) => $x + ['nombre' => $nombres[$id] ?? 'Cliente'])->values(),
            'cot' => $cot->count(), 'acc' => $cot->whereNotIn('etapa', ['cotizado', 'rechazado'])->count(),
            'ent' => $ent->count(),
            'diasEntrega' => $ent->isEmpty() ? 0 : round($ent->avg(fn ($p) => ($p->aceptado_at ?? $p->creado_at)->diffInMinutes($p->entregado_at) / 1440), 1),
        ];
    }

    /** resumen del día para mandarse por WhatsApp al cerrar: ventas, pagos, gastos, cajas, SUNAT y contadores */
    public function resumenDia(string $fecha): string
    {
        $neg = app(NegocioActual::class)->obligatorio();
        $caja = new CajaDia($fecha);
        $r = $caja->resumen();
        $L = ['*Resumen del '.Carbon::parse($fecha)->translatedFormat('D j/m').' · '.$neg->nombre.'*', ''];
        $L[] = 'Vendido: '.Dinero::s($r['total']).' ('.$r['n'].' '.($r['n'] === 1 ? 'venta' : 'ventas').')';
        foreach ($r['metodos'] as $m => $v) {
            $L[] = '  · '.$neg->nombreMetodo($m).': '.Dinero::s($v);
        }
        if ($r['tasas']) {
            $L[] = 'Tasas para terceros: '.Dinero::s($r['tasas']);
        }
        $L[] = 'Gastos: '.Dinero::s($r['gastos']);
        foreach ($caja->turnos as $t) {
            $c = $caja->turno($t);
            $d = $t->cierra_at ? $t->contado - $c['esperado'] : null;
            $L[] = 'Caja de '.Texto::primerNombre($t->vendedor).': '.($t->cierra_at
                ? ($d === 0 ? 'cuadró exacto' : ($d > 0 ? 'sobraron ' : 'faltaron ').Dinero::s(abs($d)))
                : 'abierta, debe haber '.Dinero::s($c['esperado']));
        }
        $cierre = $r['menores']->isNotEmpty() && ! $caja->dia?->cierre ? (int) $r['menores']->sum(fn ($v) => $v->propio()) : 0;
        $grandes = $r['pendientes']->count();
        $L[] = 'SUNAT: '.($cierre || $grandes ? collect([$cierre ? 'boleta de cierre por '.Dinero::s($cierre) : '', $grandes ? $grandes.' '.($grandes === 1 ? 'venta grande' : 'ventas grandes').' sin boleta' : ''])->filter()->join(' y ') : 'todo emitido');
        foreach (app(Contadores::class)->cuadre($fecha) as $g => $o) {
            if ($o['listo']) {
                $L[] = 'Copias '.Contadores::GRUPOS[$g].': '.($o['dif'] > 0 ? Contadores::numero($o['dif']).' sin cobrar (≈ '.Dinero::s($o['monto']).')' : ($o['dif'] === 0 ? 'cuadran' : Contadores::numero(-$o['dif']).' de más'));
            }
        }

        return implode("\n", $L);
    }

    /** las tablas del reporte: productos (por ganancia «g» o por ventas «v»), grupos y vendedores */
    public static function tablas(array $R, string $orden = 'g'): array
    {
        return [
            'prods' => collect($R['prods'])->map(fn ($x, $n) => $x + ['n' => $n, 'gan' => $x['cub'] ? $x['v'] - $x['c'] : null])
                ->sort(fn ($x, $y) => $orden === 'g' ? (($y['gan'] ?? -1e12) <=> ($x['gan'] ?? -1e12)) ?: $y['v'] <=> $x['v'] : $y['v'] <=> $x['v'])->take(15)->values(),
            'grupos' => collect($R['grupos'])->sortByDesc('v'),
            'vendRows' => collect($R['vend'])->filter(fn ($x) => $x['n'] || $x['anul'] || $x['dif'])->sortByDesc('v'),
        ];
    }

    /** % de cambio frente al periodo anterior (null si antes no hubo nada) */
    public static function delta(float|int $cur, float|int|null $prev): ?int
    {
        return $prev ? (int) round(($cur - $prev) / abs($prev) * 100) : null;
    }
}
