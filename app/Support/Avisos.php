<?php

namespace App\Support;

use App\Models\Dia;
use App\Models\Pedido;
use App\Models\Producto;
use App\Models\Venta;
use App\Services\Compras;
use App\Services\Stock;
use Illuminate\Support\Collection;

/** Cuentas para los avisos del menú y del Inicio (se calculan una vez por petición). */
class Avisos
{
    private ?array $menu = null;

    private ?Collection $bajo = null;

    public function menu(): array
    {
        return $this->menu ??= [
            'inventario' => $this->stockBajo()->count(),
            'comprobantes' => $this->comprobantesPorEmitir(),
            'encargos' => $this->pedidosUrgentes()->count(),
            'compras' => $this->comprasPorVencer()->count(),
        ];
    }

    private ?Collection $vencen = null;

    /** facturas de proveedor vencidas o que vencen en los próximos 3 días */
    public function comprasPorVencer(): Collection
    {
        return $this->vencen ??= app(Compras::class)->porVencer(3);
    }

    /** pedidos para hoy o atrasados, y listos que no recogen hace más de 7 días */
    public function pedidosUrgentes(): Collection
    {
        if (! app(NegocioActual::class)->get()) {
            return collect();
        }

        return Pedido::whereIn('etapa', ['proceso', 'listo'])->get()
            ->filter(fn ($p) => ($p->fecha_entrega && $p->fecha_entrega->lte(today())) || $p->olvidado())->values();
    }

    /** productos con stock en o bajo su mínimo */
    public function stockBajo(): Collection
    {
        if ($this->bajo) {
            return $this->bajo;
        }
        if (! app(NegocioActual::class)->get()) {
            return $this->bajo = collect();
        }
        $st = app(Stock::class)->todos();

        return $this->bajo = Producto::whereIn('id', array_keys($st))->orderBy('orden')->get()
            ->filter(fn ($p) => $st[$p->id] <= $p->minimo())->each(fn ($p) => $p->stock = $st[$p->id])->values();
    }

    /** ventas grandes sin boleta + boletas de cierre pendientes (últimos 31 días) */
    public function comprobantesPorEmitir(): int
    {
        if (! app(NegocioActual::class)->get()) {
            return 0;
        }
        $desde = today()->subDays(31)->toDateString();
        $V = Venta::with('items')->where('fecha', '>=', $desde)->where('boleta', false)->get();
        $cerrados = Dia::where('fecha', '>=', $desde)->where('cierre', true)->pluck('fecha')->map->toDateString()->all();
        $n = $V->filter->faltaComprobante()->count();
        $n += $V->filter->vaAlCierre()->groupBy(fn ($v) => $v->fecha->toDateString())->keys()->reject(fn ($k) => in_array($k, $cerrados, true))->count();

        return $n;
    }
}
