<?php

namespace App\Services;

use App\Models\StockBase;
use App\Support\NegocioActual;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Stock = último conteo + entradas − salidas posteriores − lo vendido después
 * (también lo que gastan los servicios con insumos, como las hojas de cada copia).
 * Las ventas anuladas ya no existen, así que devuelven solas.
 */
class Stock
{
    /** [producto_id => cantidad] de todos los productos con control de stock */
    public function todos(): array
    {
        $negocioId = app(NegocioActual::class)->obligatorio()->id;
        $st = StockBase::pluck('cantidad', 'producto_id')->map(fn ($n) => (float) $n)->all();
        if (! $st) {
            return [];
        }

        $mov = DB::table('stock_movimientos as m')
            ->join('stock_bases as b', 'b.producto_id', '=', 'm.producto_id')
            ->where('m.negocio_id', $negocioId)
            ->whereColumn('m.ocurrido_at', '>', 'b.desde')
            ->whereIn('m.tipo', ['entrada', 'salida'])
            ->groupBy('m.producto_id')
            ->selectRaw("m.producto_id, SUM(CASE WHEN m.tipo = 'entrada' THEN m.cantidad ELSE -m.cantidad END) AS n")
            ->pluck('n', 'producto_id');

        $directo = DB::table('venta_items as i')
            ->join('ventas as v', 'v.id', '=', 'i.venta_id')
            ->join('stock_bases as b', 'b.producto_id', '=', 'i.producto_id')
            ->where('v.negocio_id', $negocioId)->where('i.tercero', false)
            ->whereColumn('v.vendida_at', '>', 'b.desde')
            ->groupBy('i.producto_id')
            ->selectRaw('i.producto_id, SUM(i.cantidad) AS n')
            ->pluck('n', 'producto_id');

        $insumos = DB::table('venta_items as i')
            ->join('ventas as v', 'v.id', '=', 'i.venta_id')
            ->join('producto_insumos as pi', 'pi.producto_id', '=', 'i.producto_id')
            ->join('stock_bases as b', 'b.producto_id', '=', 'pi.insumo_id')
            ->where('v.negocio_id', $negocioId)->where('i.tercero', false)
            ->whereColumn('v.vendida_at', '>', 'b.desde')
            ->groupBy('pi.insumo_id')
            ->selectRaw('pi.insumo_id AS id, SUM(i.cantidad * pi.cantidad) AS n')
            ->pluck('n', 'id');

        foreach ($st as $id => $n) {
            $n += (float) ($mov[$id] ?? 0) - (float) ($directo[$id] ?? 0) - (float) ($insumos[$id] ?? 0);
            $st[$id] = max(0, round($n, 3));
        }

        return $st;
    }

    public function de(int $productoId): ?float
    {
        return $this->todos()[$productoId] ?? null;
    }

    /** Fija el stock contado ahora (desde este momento se descuentan las ventas) */
    public function fijar(int $productoId, float $cantidad): void
    {
        StockBase::updateOrCreate(['producto_id' => $productoId], [
            'negocio_id' => app(NegocioActual::class)->obligatorio()->id,
            'cantidad' => $cantidad,
            'desde' => now(),
        ]);
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
    }

    public static function formato(?float $n): string
    {
        if ($n === null) {
            return '';
        }

        return rtrim(rtrim(number_format($n, 2, '.', ''), '0'), '.');
    }
}
