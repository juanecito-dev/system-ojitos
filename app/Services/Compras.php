<?php

namespace App\Services;

use App\Models\CajaMovimiento;
use App\Models\Compra;
use App\Models\CompraItem;
use App\Models\Producto;
use App\Models\Proveedor;
use App\Models\StockBase;
use App\Models\StockMovimiento;
use App\Models\Usuario;
use App\Support\Dinero;
use App\Support\NegocioActual;
use App\Support\Texto;
use App\Support\Valida;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Compras y proveedores (aplicarCompra, revertirCompra, openCompra, openPagoProv y sugerencias del sistema anterior).
 * Cada línea con producto suma al stock y recalcula su costo promedio; el pago en efectivo puede salir de la caja.
 */
class Compras
{
    public function __construct(private Stock $st) {}

    // ---------------------------------------------------------------- proveedores

    public function guardarProveedor(?Proveedor $p, string $nombre, string $ruc, string $cel): Proveedor
    {
        $nombre = trim($nombre);
        $ruc = preg_replace('/\D/', '', $ruc);
        if ($nombre === '') {
            throw new ErrorNegocio('Escribe el nombre.');
        }
        if ($ruc !== '' && ! Comprobantes::rucValido($ruc)) {
            throw new ErrorNegocio('Ese RUC no es válido: revisa los 11 dígitos.');
        }
        if ($ruc !== '' && ($dup = Proveedor::where('ruc', $ruc)->where('id', '!=', $p?->id ?? 0)->first())) {
            throw new ErrorNegocio('Ese RUC ya es de '.$dup->nombre.'.');
        }
        $d = ['nombre' => mb_substr($nombre, 0, 120), 'ruc' => $ruc ?: null, 'celular' => mb_substr(trim($cel), 0, 20) ?: null];
        if ($p) {
            $p->update($d);
        } else {
            $p = Proveedor::create(['uid' => Texto::nuevoUid()] + $d);
        }
        Bitacora::registrar('compra', 'Guardó el proveedor '.$p->nombre);

        return $p;
    }

    public function eliminarProveedor(Proveedor $p): void
    {
        if ($p->compras()->exists()) {
            throw new ErrorNegocio('Tiene compras registradas: no se puede eliminar.');
        }
        $p->delete();
        Bitacora::registrar('compra', 'Eliminó el proveedor '.$p->nombre);
    }

    // ---------------------------------------------------------------- compras

    /**
     * Registra o corrige una compra.
     * $d: proveedor_id | proveedor_nuevo, numero, fecha, condicion, vence, nota, total (si no hay líneas)
     * $lineas: [[producto_id, cantidad, unidad, factor, costo_unitario]]; $pago (solo nueva al contado): [metodo, caja]
     */
    public function guardar(?Compra $c, array $d, array $lineas, Usuario $u, ?array $pago = null): Compra
    {
        $lineas = array_values(array_filter($lineas, fn ($l) => ($l['cantidad'] ?? 0) > 0));
        foreach ($lineas as &$l) {
            $l['factor'] = max(1, (float) ($l['factor'] ?? 1));
            $l['costo_unitario'] = round((float) ($l['costo_unitario'] ?? 0), 4);
            $l['subtotal'] = (int) round($l['cantidad'] * $l['costo_unitario']);
        }
        unset($l);
        $total = $lineas ? array_sum(array_column($lineas, 'subtotal')) : (int) ($d['total'] ?? 0);
        if (! ($total > 0)) {
            throw new ErrorNegocio($lineas ? 'Escribe el costo de lo que compraste.' : 'Escribe el total de la compra.');
        }
        if (collect($lineas)->contains(fn ($l) => ! ($l['costo_unitario'] > 0))) {
            throw new ErrorNegocio('Falta el costo de algún producto.');
        }
        if ($c && $total < $c->pagado()) {
            throw new ErrorNegocio('El total no puede ser menor que lo ya pagado ('.Dinero::s($c->pagado()).').');
        }
        $numero = mb_substr(trim((string) ($d['numero'] ?? '')), 0, 40);
        $fecha = Valida::fecha($d['fecha'] ?? null) ? $d['fecha'] : today()->toDateString();
        $cond = ($d['condicion'] ?? 'credito') === 'contado' ? 'contado' : 'credito';
        $vence = $cond === 'credito' && Valida::fecha($d['vence'] ?? null) ? $d['vence'] : null;
        if ($fecha > today()->toDateString()) {
            throw new ErrorNegocio('La fecha de la compra no puede ser futura.');
        }
        if ($vence && $vence < $fecha) {
            throw new ErrorNegocio('El vencimiento no puede ser antes de la fecha de la compra.');
        }

        return DB::transaction(function () use ($c, $d, $lineas, $u, $pago, $total, $numero, $fecha, $cond, $vence) {
            $provId = $d['proveedor_id'] ?? null;
            if (! $provId) {
                $provId = $this->guardarProveedor(null, (string) ($d['proveedor_nuevo'] ?? ''), '', '')->id;
            } elseif (! Proveedor::whereKey($provId)->exists()) {
                throw new ErrorNegocio('Elige el proveedor.');
            }
            if ($numero !== '' && ($dup = Compra::where('proveedor_id', $provId)->where('id', '!=', $c?->id ?? 0)->get()
                ->first(fn ($x) => Texto::norm($x->numero) === Texto::norm($numero)))) {
                throw new ErrorNegocio('Ya registraste la '.$numero.' de '.$dup->proveedor?->nombre.' ('.$dup->fecha->format('d/m/Y').').');
            }
            $datos = ['proveedor_id' => $provId, 'numero' => $numero ?: null, 'fecha' => $fecha, 'total' => $total, 'condicion' => $cond,
                'vence' => $vence,
                'nota' => mb_substr(trim((string) ($d['nota'] ?? '')), 0, 250) ?: null];
            if ($c) {
                $this->revertir($c, $u);
                $c->update($datos);
                $c->items()->delete();
            } else {
                $c = Compra::create(['uid' => Texto::nuevoUid(), 'usuario_id' => $u->id, 'vendedor' => $u->nombre] + $datos);
            }
            $P = Producto::whereIn('id', array_column($lineas, 'producto_id'))->get()->keyBy('id');
            foreach ($lineas as $i => $l) {
                $p = $P[$l['producto_id'] ?? 0] ?? null;
                $c->items()->create(['producto_id' => $p?->id, 'producto_uid' => $p?->uid, 'nombre' => $p?->nombre ?? mb_substr((string) ($l['nombre'] ?? '?'), 0, 120),
                    'cantidad' => $l['cantidad'], 'unidad' => mb_substr((string) ($l['unidad'] ?? ''), 0, 20) ?: null, 'factor' => $l['factor'],
                    'costo_unitario' => $l['costo_unitario'], 'subtotal' => $l['subtotal'], 'orden' => $i]);
            }
            $this->aplicar($c->fresh(['items', 'proveedor']), $u);
            if ($pago && $cond === 'contado' && ! $c->pagos()->exists()) {
                $this->pagar($c->fresh(), $total, $pago[0] ?? 'efectivo', (bool) ($pago[1] ?? false), $u);
            }
            Bitacora::registrar('compra', 'Registró la compra '.($numero ?: 'sin número').' de '.$c->proveedor?->nombre.' por '.Dinero::s($total));

            return $c->fresh(['items', 'pagos', 'proveedor']);
        });
    }

    /** suma al stock y recalcula el costo promedio de cada producto */
    public function aplicar(Compra $c, Usuario $u): void
    {
        foreach ($c->items as $l) {
            $p = $l->producto_id ? Producto::find($l->producto_id) : null;
            $unid = $l->unidades();
            if (! $p || ! ($unid > 0)) {
                continue;
            }
            if (! StockBase::where('producto_id', $p->id)->exists()) {
                StockBase::create(['producto_id' => $p->id, 'cantidad' => 0, 'desde' => now()->subSecond()]);
            }
            $actual = max(0, $this->st->de($p->id) ?? 0);
            $cu = $l->costoPorUnidad();
            $nuevo = round($actual > 0 && $p->costo ? ($actual * $p->costo + $unid * $cu) / ($actual + $unid) : $cu, 4);
            $l->update(['costo_antes' => $p->costo, 'costo_nuevo' => $nuevo]);
            $p->update(['costo' => $nuevo]);
            StockMovimiento::create([
                'uid' => Texto::nuevoUid(), 'producto_id' => $p->id, 'producto_uid' => $p->uid, 'nombre' => $p->nombre, 'tipo' => 'entrada', 'cantidad' => $unid,
                'costo' => round($cu, 4), 'compra_uid' => $c->uid, 'nota' => trim(($c->proveedor?->nombre ?? 'Proveedor').' '.($c->numero ?? '')),
                'usuario_id' => $u->id, 'vendedor' => $u->nombre, 'ocurrido_at' => $this->st->ahoraPara($p->id),
            ]);
        }
    }

    /** deshace lo que la compra sumó al stock y el costo que cambió (si nadie lo cambió después) */
    public function revertir(Compra $c, Usuario $u): void
    {
        foreach (StockMovimiento::where('compra_uid', $c->uid)->get() as $e) {
            $b = StockBase::where('producto_id', $e->producto_id)->first();
            $e->delete();
            // si después hubo un conteo, la entrada ya estaba contada: se anota la salida
            if ($b && $e->ocurrido_at->lte($b->desde)) {
                StockMovimiento::create(['uid' => Texto::nuevoUid(), 'producto_id' => $e->producto_id, 'producto_uid' => $e->producto_uid, 'nombre' => $e->nombre,
                    'tipo' => 'salida', 'cantidad' => $e->cantidad, 'nota' => 'Compra corregida o eliminada', 'usuario_id' => $u->id, 'vendedor' => $u->nombre, 'ocurrido_at' => now()]);
            }
        }
        foreach ($c->items()->get()->reverse() as $l) {
            $p = $l->producto_id ? Producto::find($l->producto_id) : null;
            if ($p && $l->costo_nuevo !== null && abs(($p->costo ?? 0) - $l->costo_nuevo) < 0.0001) {
                $p->update(['costo' => $l->costo_antes]);
            }
        }
    }

    public function pagar(Compra $c, int $monto, string $metodo, bool $deCaja, Usuario $u): void
    {
        $saldo = $c->saldo();
        if (! ($monto > 0)) {
            throw new ErrorNegocio('Escribe el monto.');
        }
        if ($monto > $saldo) {
            throw new ErrorNegocio('El pago es mayor que el saldo ('.Dinero::s($saldo).').');
        }
        $metodo = array_key_exists($metodo, app(NegocioActual::class)->obligatorio()->metodosActivos()) ? $metodo : 'efectivo';
        $mov = null;
        if ($metodo === 'efectivo' && $deCaja) {
            $mov = CajaMovimiento::create(['uid' => Texto::nuevoUid(), 'fecha' => today()->toDateString(), 'tipo' => 'retiro', 'concepto' => 'Pago a proveedor',
                'nota' => trim(($c->proveedor?->nombre ?? '').' '.($c->numero ?? '')) ?: null, 'monto' => $monto, 'metodo' => 'efectivo', 'referencia' => $c->uid,
                'usuario_id' => $u->id, 'vendedor' => $u->nombre, 'ocurrido_at' => now()]);
        }
        $c->pagos()->create(['monto' => $monto, 'metodo' => $metodo, 'caja_mov_uid' => $mov?->uid, 'usuario_id' => $u->id, 'vendedor' => $u->nombre, 'pagado_at' => now()]);
        $c->unsetRelation('pagos');
        Bitacora::registrar('compra', 'Pagó '.Dinero::s($monto).' a '.$c->proveedor?->nombre.' ('.($c->numero ?: 'compra').')'.($mov ? ', salió de la caja' : ''));
    }

    public function eliminar(Compra $c, Usuario $u): void
    {
        DB::transaction(function () use ($c, $u) {
            $this->revertir($c, $u);
            CajaMovimiento::whereIn('uid', $c->pagos()->whereNotNull('caja_mov_uid')->pluck('caja_mov_uid'))->delete();
            $c->delete();
        });
        Bitacora::registrar('compra', 'Eliminó la compra '.($c->numero ?: 'sin número').' de '.$c->proveedor?->nombre.' por '.Dinero::s($c->total));
    }

    // ---------------------------------------------------------------- costos y reposición

    /** historial de costos de un producto (por unidad suelta), la compra más reciente primero: [[c, proveedor, fecha, compra]] */
    public function costosDe(int $productoId): Collection
    {
        $out = CompraItem::with('compra.proveedor')->where('producto_id', $productoId)->get()
            ->filter(fn ($l) => $l->compra && $l->unidades() > 0)
            ->map(fn ($l) => ['c' => $l->costoPorUnidad(), 'prov' => $l->compra->proveedor?->nombre ?? 'Proveedor', 'prov_id' => $l->compra->proveedor_id,
                'fecha' => $l->compra->fecha->toDateString(), 'compra' => $l->compra, 'cant' => $l->cantidad, 'unidad' => $l->unidad, 'factor' => $l->factor]);
        if ($out->isEmpty() && ($u = Producto::find($productoId)?->extra['ultC'] ?? null) && isset($u['c'])) {
            $out->push(['c' => (float) $u['c'], 'prov' => Proveedor::where('uid', $u['prov'] ?? '')->value('nombre') ?? 'Proveedor',
                'prov_id' => Proveedor::where('uid', $u['prov'] ?? '')->value('id'), 'fecha' => $u['fecha'] ?? null, 'compra' => null, 'cant' => null, 'unidad' => null, 'factor' => 1]);
        }

        return $out->sortByDesc(fn ($x) => ($x['fecha'] ?? '').($x['compra']?->id ?? ''))->values();
    }

    /** «Último costo S/ 0.42 c/u (Librería Central, 12/09) · más barato S/ 0.38 con Tai Loy» */
    public function infoCosto(int $productoId): string
    {
        $h = $this->costosDe($productoId);
        if ($h->isEmpty()) {
            return '';
        }
        $u = $h->first();
        $b = $h->sortBy('c')->first();

        return 'Último costo '.Dinero::sCosto($u['c']).' c/u ('.$u['prov'].($u['fecha'] ? ', '.substr($u['fecha'], 8, 2).'/'.substr($u['fecha'], 5, 2) : '').')'
            .($b['c'] < $u['c'] - 0.0001 ? ' · más barato '.Dinero::sCosto($b['c']).' con '.$b['prov'] : '');
    }

    /** productos en su mínimo o menos, con lo que conviene pedir (en la unidad en que se compra) para unas 2 semanas */
    public function sugerencias(): Collection
    {
        $st = $this->st->todos();
        $vel = app(Inventario::class)->velocidad();

        return Producto::orderBy('orden')->get()->filter(fn ($p) => array_key_exists($p->id, $st) && $st[$p->id] <= $p->minimo())
            ->map(function ($p) use ($st, $vel) {
                $objetivo = max($p->minimo() * 2, ceil(($vel[$p->id] ?? 0) * 14));
                $falta = max(1, $objetivo - $st[$p->id]);
                $uc = $p->unidadCompra();
                $h = $this->costosDe($p->id)->first();

                return ['p' => $p, 'stock' => $st[$p->id], 'cant' => (int) ceil($falta / $uc['f']), 'uc' => $uc, 'prov_id' => $h['prov_id'] ?? null,
                    'costoU' => round(($h['c'] ?? ($p->costo ?? 0)) * $uc['f'], 4)];
            })->values();
    }

    /** facturas de proveedor vencidas o que vencen en los próximos días */
    public function porVencer(int $dias = 3): Collection
    {
        if (! app(NegocioActual::class)->get()) {
            return collect();
        }

        return Compra::with(['pagos', 'proveedor'])->where('condicion', 'credito')->whereNotNull('vence')->where('vence', '<=', today()->addDays($dias)->toDateString())->get()
            ->filter(fn ($c) => $c->saldo() > 0)->sortBy(fn ($c) => $c->vence)->values();
    }

    /** texto del pedido por WhatsApp para un proveedor */
    public static function textoPedido(?Proveedor $pr, Collection $L): string
    {
        return 'Hola'.($pr ? ' '.$pr->nombre : '').', le saluda '.app(NegocioActual::class)->obligatorio()->nombre.". Quisiera hacer un pedido:\n"
            .$L->map(fn ($x) => '• '.$x['cant'].' '.($x['uc']['f'] > 1 ? mb_strtolower($x['uc']['n']).' de ' : '').$x['p']->nombre)->join("\n")
            ."\n¿Me confirma precio y disponibilidad? Gracias.";
    }
}
