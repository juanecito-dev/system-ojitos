<?php

namespace App\Services;

use App\Models\ProductoInsumo;
use App\Models\StockBase;
use App\Models\StockConsumo;
use App\Models\StockMovimiento;
use App\Models\StockSaldo;
use App\Models\Usuario;
use App\Models\Venta;
use App\Support\NegocioActual;
use App\Support\Texto;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Stock = último conteo + entradas − salidas posteriores − lo vendido después − lo que gastaron
 * los servicios en insumos (las hojas de cada copia, anotadas en stock_consumos al vender).
 * Las ventas anuladas no cuentan, así que devuelven solas.
 *
 * El resultado se guarda por producto (stock_saldos) y se ajusta con cada venta y movimiento:
 * las pantallas lo leen sin recorrer el historial. recalcular() lo compara con el historial.
 * Puede quedar en negativo: se vendió más de lo contado (falta registrar una compra o un conteo).
 */
class Stock
{
    /** [producto_id => cantidad] de todos los productos con control de stock */
    public function todos(): array
    {
        return StockSaldo::pluck('cantidad', 'producto_id')->map(fn ($n) => round((float) $n, 3))->all();
    }

    public function de(int $productoId): ?float
    {
        $n = StockSaldo::where('producto_id', $productoId)->value('cantidad');

        return $n === null ? null : round((float) $n, 3);
    }

    /** el stock calculado desde el historial: [producto_id => cantidad], para todos o para algunos productos */
    public function calcular(?array $ids = null): array
    {
        $negocioId = app(NegocioActual::class)->obligatorio()->id;
        $st = StockBase::when($ids !== null, fn ($q) => $q->whereIn('producto_id', $ids))->pluck('cantidad', 'producto_id')->map(fn ($n) => (float) $n)->all();
        if (! $st) {
            return [];
        }
        $base = fn ($q, string $col, string $prod) => $q->join('stock_bases as b', 'b.producto_id', '=', $prod)
            ->whereColumn($col, '>', 'b.desde')->when($ids !== null, fn ($x) => $x->whereIn($prod, $ids));

        $mov = $base(DB::table('stock_movimientos as m'), 'm.ocurrido_at', 'm.producto_id')
            ->where('m.negocio_id', $negocioId)->whereIn('m.tipo', ['entrada', 'salida'])->groupBy('m.producto_id')
            ->selectRaw("m.producto_id, SUM(CASE WHEN m.tipo = 'entrada' THEN m.cantidad ELSE -m.cantidad END) AS n")->pluck('n', 'producto_id');
        $vendido = $base(DB::table('venta_items as i')->join('ventas as v', 'v.id', '=', 'i.venta_id')->whereNull('v.anulada_at'), 'v.vendida_at', 'i.producto_id')
            ->where('v.negocio_id', $negocioId)->where('i.tercero', false)->groupBy('i.producto_id')
            ->selectRaw('i.producto_id, SUM(i.cantidad) AS n')->pluck('n', 'producto_id');
        $gastado = $base(DB::table('stock_consumos as c')->join('ventas as v', 'v.id', '=', 'c.venta_id')->whereNull('v.anulada_at'), 'c.ocurrido_at', 'c.producto_id')
            ->where('c.negocio_id', $negocioId)->groupBy('c.producto_id')
            ->selectRaw('c.producto_id, SUM(c.cantidad) AS n')->pluck('n', 'producto_id');

        foreach ($st as $id => $n) {
            $st[$id] = $n + (float) ($mov[$id] ?? 0) - (float) ($vendido[$id] ?? 0) - (float) ($gastado[$id] ?? 0);
        }

        return $st;
    }

    /**
     * Revisa el stock guardado contra el historial y lo corrige.
     *
     * @return array<int, array{guardado: float|null, real: float}> lo que no cuadraba (producto_id => …)
     */
    public function recalcular(?array $ids = null): array
    {
        $real = $this->calcular($ids);
        $guardado = StockSaldo::when($ids !== null, fn ($q) => $q->whereIn('producto_id', $ids))->pluck('cantidad', 'producto_id');
        $mal = [];
        foreach ($real as $id => $n) {
            $g = isset($guardado[$id]) ? (float) $guardado[$id] : null;
            if ($g === null || abs($g - $n) > 0.0005) {
                $mal[$id] = ['guardado' => $g === null ? null : round($g, 3), 'real' => round($n, 3)];
                StockSaldo::updateOrCreate(['producto_id' => $id], ['cantidad' => $n]);
            }
        }
        // saldos de productos que ya no tienen control de stock
        StockSaldo::when($ids !== null, fn ($q) => $q->whereIn('producto_id', $ids))->whereNotIn('producto_id', array_keys($real))->delete();

        return $mal;
    }

    /** suma (o resta) al stock guardado de un producto, si tiene control de stock (atómico: dos cajas a la vez no se pisan) */
    public function mover(int $productoId, float $delta): void
    {
        if ($delta != 0.0) {
            StockSaldo::where('producto_id', $productoId)->increment('cantidad', $delta);
        }
    }

    /** ¿algo que pasó en ese momento cuenta para el stock de ahora? (después del último conteo) */
    public function cuenta(int $productoId, Carbon $at): bool
    {
        $desde = StockBase::where('producto_id', $productoId)->value('desde');

        return $desde !== null && $at->gt(Carbon::parse($desde));
    }

    /** Fija el stock contado ahora (desde este momento se descuentan las ventas) */
    public function fijar(int $productoId, float $cantidad): void
    {
        StockBase::updateOrCreate(['producto_id' => $productoId], [
            'negocio_id' => app(NegocioActual::class)->obligatorio()->id,
            'cantidad' => $cantidad,
            'desde' => now(),
        ]);   // al guardarse, StockBase recalcula el stock guardado de ese producto
    }

    /** la hora para anotar un movimiento: siempre después del último conteo, para que cuente aunque sea en el mismo segundo */
    public function ahoraPara(int $productoId): Carbon
    {
        $desde = StockBase::where('producto_id', $productoId)->value('desde');
        $t = now()->startOfSecond();   // la base guarda segundos, sin fracciones

        return $desde && $t->lte(Carbon::parse($desde)) ? Carbon::parse($desde)->addSecond() : $t;
    }

    public function quitarControl(int $productoId): void
    {
        StockBase::where('producto_id', $productoId)->delete();
        StockSaldo::where('producto_id', $productoId)->delete();
    }

    // ---------------------------------------------------------------- lo que hace cada venta

    /** al vender: anota lo que gastó en insumos (con la receta de hoy) y descuenta del stock guardado */
    public function alVender(Venta $v): void
    {
        $lineas = $v->items->filter(fn ($l) => $l->producto_id && ! $l->tercero);
        if ($lineas->isEmpty()) {
            return;
        }
        $recetas = ProductoInsumo::whereIn('producto_id', $lineas->pluck('producto_id')->unique())->get()->groupBy('producto_id');
        foreach ($lineas as $l) {
            if ($this->cuenta($l->producto_id, $v->vendida_at)) {
                $this->mover($l->producto_id, -(float) $l->cantidad);
            }
            foreach ($recetas[$l->producto_id] ?? [] as $r) {
                $q = (float) $l->cantidad * (float) $r->cantidad;
                StockConsumo::create(['venta_id' => $v->id, 'venta_item_id' => $l->id, 'producto_id' => $r->insumo_id, 'cantidad' => $q, 'ocurrido_at' => $v->vendida_at]);
                if ($this->cuenta($r->insumo_id, $v->vendida_at)) {
                    $this->mover($r->insumo_id, -$q);
                }
            }
        }
    }

    /**
     * Al anular: lo vendido después del último conteo vuelve solo al stock. Lo vendido antes ya estaba
     * contado: el producto vuelve como entrada (los insumos gastados, no: ya se usaron).
     */
    public function alAnular(Venta $v, Usuario $por): void
    {
        foreach ($v->items->filter(fn ($l) => $l->producto_id && ! $l->tercero) as $l) {
            if ($this->cuenta($l->producto_id, $v->vendida_at)) {
                $this->mover($l->producto_id, (float) $l->cantidad);
            } elseif (StockBase::where('producto_id', $l->producto_id)->exists()) {
                StockMovimiento::create([
                    'uid' => Texto::nuevoUid(), 'producto_id' => $l->producto_id, 'producto_uid' => $l->producto_uid, 'nombre' => $l->nombre,
                    'tipo' => 'entrada', 'cantidad' => $l->cantidad, 'nota' => 'Devolución por venta anulada',
                    'usuario_id' => $por->id, 'vendedor' => $por->nombre, 'ocurrido_at' => $this->ahoraPara($l->producto_id),
                ]);
            }
        }
        foreach (StockConsumo::where('venta_id', $v->id)->get() as $c) {
            if ($this->cuenta($c->producto_id, $c->ocurrido_at)) {
                $this->mover($c->producto_id, $c->cantidad);
            }
        }
    }

    /** a una venta que no tenga anotado lo que gastó (por ejemplo, las que trae el importador) se le anota con la receta de hoy */
    public function completarConsumos(): int
    {
        $negocioId = app(NegocioActual::class)->obligatorio()->id;

        return DB::affectingStatement('INSERT INTO stock_consumos (negocio_id, venta_id, venta_item_id, producto_id, cantidad, ocurrido_at)
            SELECT v.negocio_id, v.id, i.id, pi.insumo_id, i.cantidad * pi.cantidad, v.vendida_at
            FROM venta_items i JOIN ventas v ON v.id = i.venta_id JOIN producto_insumos pi ON pi.producto_id = i.producto_id
            WHERE v.negocio_id = ? AND i.tercero = ? AND NOT EXISTS (SELECT 1 FROM stock_consumos c WHERE c.venta_id = v.id)', [$negocioId, false]);
    }

    public static function formato(?float $n): string
    {
        if ($n === null) {
            return '';
        }

        return rtrim(rtrim(number_format($n, 2, '.', ''), '0'), '.');
    }
}
