<?php

namespace App\Services;

use App\Models\Producto;
use App\Models\ProductoInsumo;
use App\Models\StockBase;
use App\Models\StockMovimiento;
use App\Models\TomaConteo;
use App\Models\Usuario;
use App\Support\Dinero;
use App\Support\NegocioActual;
use App\Support\Texto;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Inventario (renderInv, openStockMove, kardex y la toma de inventario del sistema anterior).
 * El stock sale de Stock::todos(): último conteo + entradas − salidas − lo vendido después.
 */
class Inventario
{
    public const MOTIVOS = ['Merma o dañado', 'Robo o pérdida', 'Uso interno', 'Error de conteo'];

    /** días que se promedian para saber cuánto sale por día */
    public const DIAS_VELOCIDAD = 14;

    private ?array $stock = null;

    public function __construct(private Stock $st) {}

    public function stock(): array
    {
        return $this->stock ??= $this->st->todos();
    }

    private function negocioId(): int
    {
        return app(NegocioActual::class)->obligatorio()->id;
    }

    /** cuánto sale de cada producto por día: ventas, insumos gastados y salidas que no son compras (últimas 2 semanas) */
    public function velocidad(): array
    {
        $desde = today()->subDays(self::DIAS_VELOCIDAD - 1);
        $v = [];
        $sumar = function ($filas) use (&$v) {
            foreach ($filas as $id => $n) {
                $v[$id] = ($v[$id] ?? 0) + (float) $n;
            }
        };
        $ventas = fn () => DB::table('venta_items as i')->join('ventas as v', 'v.id', '=', 'i.venta_id')->whereNull('v.anulada_at')
            ->where('v.negocio_id', $this->negocioId())->where('i.tercero', false)->where('v.vendida_at', '>=', $desde);
        $sumar($ventas()->whereNotNull('i.producto_id')->groupBy('i.producto_id')->selectRaw('i.producto_id AS id, SUM(i.cantidad) AS n')->pluck('n', 'id'));
        $sumar($ventas()->join('producto_insumos as pi', 'pi.producto_id', '=', 'i.producto_id')->groupBy('pi.insumo_id')
            ->selectRaw('pi.insumo_id AS id, SUM(i.cantidad * pi.cantidad) AS n')->pluck('n', 'id'));
        $sumar(StockMovimiento::where('tipo', 'salida')->where('ocurrido_at', '>=', $desde)->where(fn ($q) => $q->whereNull('nota')->orWhere('nota', 'not like', 'Compra%'))
            ->groupBy('producto_id')->selectRaw('producto_id AS id, SUM(cantidad) AS n')->pluck('n', 'id'));

        return array_map(fn ($n) => $n / self::DIAS_VELOCIDAD, $v);
    }

    /** días que alcanza el stock al ritmo de las últimas 2 semanas (null si no se mueve) */
    public static function diasAlcanza(?float $stock, ?float $porDia): ?int
    {
        return $stock !== null && $porDia > 0 ? (int) floor(max(0, $stock) / $porDia) : null;
    }

    public function vendidoHoy(): array
    {
        return DB::table('venta_items as i')->join('ventas as v', 'v.id', '=', 'i.venta_id')->whereNull('v.anulada_at')
            ->where('v.negocio_id', $this->negocioId())->where('v.fecha', today()->toDateString())->whereNotNull('i.producto_id')
            ->groupBy('i.producto_id')->selectRaw('i.producto_id AS id, SUM(i.cantidad) AS n')->pluck('n', 'id')->map(fn ($n) => (float) $n)->all();
    }

    /** faltantes al contar en el mes, a precio de costo (sin los errores de conteo) */
    public function perdidasMes(?string $mes = null): float
    {
        $desde = Carbon::parse(($mes ?? today()->format('Y-m')).'-01')->startOfDay();

        return $this->perdidas($desde, $desde->copy()->endOfMonth());
    }

    public function perdidas(Carbon $desde, Carbon $hasta): float
    {
        return (float) StockMovimiento::with('producto')->where('tipo', 'conteo')->where('diferencia', '<', 0)
            ->whereBetween('ocurrido_at', [$desde, $hasta])
            ->where(fn ($q) => $q->whereNull('motivo')->orWhere('motivo', '!=', 'Error de conteo'))->get()
            ->sum(fn ($m) => -$m->diferencia * ($m->costo ?? $m->producto?->costo ?? 0));
    }

    /** mercadería a precio de costo */
    public function valorCosto(Collection $productos): float
    {
        $st = $this->stock();

        return (float) $productos->sum(fn ($p) => isset($st[$p->id]) ? max(0, $st[$p->id]) * ($p->costo ?? 0) : 0);
    }

    // ---------------------------------------------------------------- movimientos

    /** lo que llegó: suma al stock y, si trae costo, recalcula el costo promedio */
    public function entrada(Producto $p, float $cant, ?string $nota, ?float $costoU, Usuario $u): float
    {
        if (! ($cant > 0)) {
            throw new ErrorNegocio('Escribe cuántos entraron.');
        }

        return DB::transaction(function () use ($p, $cant, $nota, $costoU, $u) {
            $actual = max(0, $this->st->de($p->id) ?? 0);
            if (! StockBase::where('producto_id', $p->id)->exists()) {
                StockBase::create(['producto_id' => $p->id, 'cantidad' => 0, 'desde' => now()->subSecond()]);
                $actual = 0;
            }
            if ($costoU > 0) {
                $nuevo = $actual > 0 && $p->costo ? ($actual * $p->costo + $cant * $costoU) / ($actual + $cant) : $costoU;
                $p->update(['costo' => round($nuevo, 4)]);
            }
            StockMovimiento::create([
                'uid' => Texto::nuevoUid(), 'producto_id' => $p->id, 'producto_uid' => $p->uid, 'nombre' => $p->nombre, 'tipo' => 'entrada', 'cantidad' => $cant,
                'costo' => $costoU > 0 ? $costoU : null, 'nota' => $nota ? mb_substr($nota, 0, 250) : null,
                'usuario_id' => $u->id, 'vendedor' => $u->nombre, 'ocurrido_at' => $this->st->ahoraPara($p->id),
            ]);
            $this->stock = null;

            return $this->st->de($p->id) ?? 0;
        });
    }

    /** lo que hay de verdad: reemplaza el stock y, si hay diferencia, anota el motivo */
    public function contar(Producto $p, float $cant, ?string $motivo, Usuario $u, ?float $sistema = null): void
    {
        if ($cant < 0) {
            throw new ErrorNegocio('Escribe cuántos hay (puede ser 0).');
        }
        $antes = $sistema ?? $this->st->de($p->id);
        $dif = $antes === null ? null : round($cant - $antes, 3);
        if ($dif && ! in_array($motivo, [...self::MOTIVOS, 'Toma de inventario'], true)) {
            throw new ErrorNegocio('Elige el motivo de la diferencia.');
        }
        DB::transaction(function () use ($p, $cant, $motivo, $u, $dif) {
            StockMovimiento::create([
                'uid' => Texto::nuevoUid(), 'producto_id' => $p->id, 'producto_uid' => $p->uid, 'nombre' => $p->nombre, 'tipo' => 'conteo', 'cantidad' => $cant,
                'diferencia' => $dif, 'motivo' => $dif ? $motivo : null, 'costo' => $p->costo ?: null,
                'usuario_id' => $u->id, 'vendedor' => $u->nombre, 'ocurrido_at' => now(),
            ]);
            $this->st->fijar($p->id, $cant);
        });
        $this->stock = null;
        if ($dif && $motivo !== 'Toma de inventario') {
            Bitacora::registrar('stock', 'Contó '.$p->nombre.': '.($dif < 0 ? 'faltan ' : 'sobran ').Stock::formato(abs($dif)).' ('.$motivo.')');
        }
    }

    /**
     * Kárdex: los movimientos entre dos fechas, del más reciente al más antiguo, con el saldo después de cada uno.
     * [[t, que, d, abs, vend, saldo]]
     */
    public function kardex(Producto $p, string $desde, string $hasta): array
    {
        $t0 = Carbon::parse($desde)->startOfDay();
        $t1 = Carbon::parse($hasta)->endOfDay();
        $base = StockBase::where('producto_id', $p->id)->first();
        $inicio = collect([$base?->desde, StockMovimiento::where('producto_id', $p->id)->min('ocurrido_at')])->filter()->map(fn ($t) => Carbon::parse($t))->min();
        if (! $inicio) {
            return [];
        }
        $rows = [];
        $ventas = fn () => DB::table('venta_items as i')->join('ventas as v', 'v.id', '=', 'i.venta_id')->whereNull('v.anulada_at')
            ->where('v.negocio_id', $this->negocioId())->where('i.tercero', false)
            ->where('v.vendida_at', '>=', $t0->copy()->max($inicio));
        foreach ($ventas()->where('i.producto_id', $p->id)->get(['v.vendida_at', 'v.numero', 'v.vendedor', 'i.cantidad']) as $r) {
            $rows[] = ['t' => Carbon::parse($r->vendida_at), 'que' => 'Venta'.($r->numero ? ' '.$r->numero : ''), 'd' => -(float) $r->cantidad, 'abs' => null, 'vend' => $r->vendedor];
        }
        foreach ($ventas()->join('producto_insumos as pi', 'pi.producto_id', '=', 'i.producto_id')->where('pi.insumo_id', $p->id)
            ->get(['v.vendida_at', 'v.vendedor', 'i.cantidad', 'i.nombre', 'pi.cantidad as q']) as $r) {
            $rows[] = ['t' => Carbon::parse($r->vendida_at), 'que' => 'Gastado en '.Stock::formato((float) $r->cantidad).' '.$r->nombre, 'd' => -round($r->q * $r->cantidad, 3), 'abs' => null, 'vend' => $r->vendedor];
        }
        foreach (StockMovimiento::where('producto_id', $p->id)->where('ocurrido_at', '>=', $t0)->get() as $m) {
            $rows[] = match ($m->tipo) {
                'entrada' => ['t' => $m->ocurrido_at, 'que' => trim(($m->compra_uid ? 'Compra ' : 'Entrada ').($m->nota ?? '').($m->costo ? ' a '.Dinero::sCosto($m->costo) : '')), 'd' => $m->cantidad, 'abs' => null, 'vend' => $m->vendedor],
                'salida' => ['t' => $m->ocurrido_at, 'que' => $m->nota ?: 'Salida', 'd' => -$m->cantidad, 'abs' => null, 'vend' => $m->vendedor],
                default => ['t' => $m->ocurrido_at, 'que' => 'Conteo'.($m->motivo ? ': '.$m->motivo : ''), 'd' => (float) ($m->diferencia ?? 0), 'abs' => $m->cantidad, 'vend' => $m->vendedor],
            };
        }
        usort($rows, fn ($a, $b) => $b['t'] <=> $a['t']);
        $run = $this->stock()[$p->id] ?? 0;
        foreach ($rows as &$r) {
            $r['saldo'] = $run;
            $run = $r['abs'] !== null ? $r['abs'] - $r['d'] : $run - $r['d'];
        }
        unset($r);

        return array_values(array_filter($rows, fn ($r) => $r['t']->lte($t1)));
    }

    // ---------------------------------------------------------------- insumos

    /** material que se usa en los servicios y no se vende suelto (no sale en el punto de venta) */
    public function crearInsumo(array $d, Usuario $u): Producto
    {
        $n = trim((string) ($d['nombre'] ?? ''));
        $s = is_numeric(str_replace(',', '.', (string) ($d['stock'] ?? ''))) ? (float) str_replace(',', '.', $d['stock']) : null;
        $f = (float) ($d['f'] ?? 0);
        $un = trim((string) ($d['un'] ?? ''));
        $c = Dinero::aCosto($d['costo'] ?? '');
        $min = is_numeric(str_replace(',', '.', (string) ($d['min'] ?? ''))) ? (float) str_replace(',', '.', $d['min']) : null;
        if ($n === '') {
            throw new ErrorNegocio('Escribe el nombre.');
        }
        if ($s === null || $s < 0) {
            throw new ErrorNegocio('Escribe cuánto tienes ahora (puede ser 0).');
        }
        $porCompra = $un !== '' && $f > 1;
        $p = Producto::create([
            'uid' => 'i_'.Texto::nuevoUid(), 'grupo' => 'Insumos', 'nombre' => $n, 'color' => 't-otro', 'oculto' => true,
            'unidad' => trim((string) ($d['um'] ?? '')) ?: null, 'costo' => $c > 0 ? round($c / ($porCompra ? $f : 1), 4) : null,
            'stock_minimo' => $min !== null && $min >= 0 ? $min : null, 'orden' => (int) Producto::max('orden') + 1,
            'extra' => $porCompra ? ['uc' => ['n' => $un, 'f' => $f]] : null,
        ]);
        $this->st->fijar($p->id, $s);
        Bitacora::registrar('producto', 'Agregó el insumo '.$p->nombre);

        return $p;
    }

    /** qué gasta un servicio por cada 1 vendido: [[insumo_id, cantidad]] */
    public function guardarInsumos(Producto $p, array $filas): int
    {
        $ok = collect($filas)->filter(fn ($x) => ($x[0] ?? null) && ($x[1] ?? 0) > 0 && (int) $x[0] !== $p->id)
            ->unique(fn ($x) => (int) $x[0])->filter(fn ($x) => Producto::whereKey((int) $x[0])->exists());
        DB::transaction(function () use ($p, $ok) {
            ProductoInsumo::where('producto_id', $p->id)->delete();
            foreach ($ok as [$id, $q]) {
                ProductoInsumo::create(['producto_id' => $p->id, 'insumo_id' => (int) $id, 'cantidad' => round((float) $q, 10)]);
            }
        });
        Bitacora::registrar('producto', 'Insumos de '.$p->nombre.': '.($ok->isEmpty() ? 'ninguno' : $ok->count()));

        return $ok->count();
    }

    // ---------------------------------------------------------------- toma de inventario

    public function tomaFijar(int $productoId, ?float $cant, Usuario $u): void
    {
        if ($cant === null) {
            TomaConteo::where('producto_id', $productoId)->delete();

            return;
        }
        if ($cant < 0 || ! StockBase::where('producto_id', $productoId)->exists()) {
            return;
        }
        TomaConteo::updateOrCreate(['producto_id' => $productoId], ['cantidad' => $cant, 'usuario_id' => $u->id, 'vendedor' => $u->nombre]);
    }

    /** con el lector: cada lectura suma 1 */
    public function tomaSumar(int $productoId, Usuario $u): float
    {
        $n = (TomaConteo::where('producto_id', $productoId)->value('cantidad') ?? 0) + 1;
        $this->tomaFijar($productoId, $n, $u);

        return $n;
    }

    /** aplica lo contado: [productos, con diferencia, faltantes a costo] */
    public function tomaAplicar(Usuario $u): array
    {
        $T = TomaConteo::get();
        $st = $this->stock();
        $P = Producto::withTrashed()->whereIn('id', $T->pluck('producto_id'))->get()->keyBy('id');
        $r = ['n' => 0, 'dif' => 0, 'perdida' => 0.0];
        DB::transaction(function () use ($T, $st, $P, $u, &$r) {
            foreach ($T as $t) {
                $p = $P[$t->producto_id] ?? null;
                if (! $p || ! array_key_exists($p->id, $st)) {
                    continue;
                }
                $d = round($t->cantidad - $st[$p->id], 3);
                $this->contar($p, $t->cantidad, $d ? 'Toma de inventario' : null, $u, $st[$p->id]);
                $r['n']++;
                $r['dif'] += $d ? 1 : 0;
                $r['perdida'] += $d < 0 ? -$d * ($p->costo ?? 0) : 0;
            }
            TomaConteo::query()->delete();
        });
        Bitacora::registrar('stock', 'Aplicó la toma de inventario: '.$r['n'].' productos, '.$r['dif'].' con diferencia'.($r['perdida'] ? ', faltantes por '.Dinero::s($r['perdida']) : ''));

        return $r;
    }
}
