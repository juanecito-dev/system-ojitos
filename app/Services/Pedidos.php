<?php

namespace App\Services;

use App\Models\Cliente;
use App\Models\Pedido;
use App\Models\PedidoHistorial;
use App\Models\PlantillaUtiles;
use App\Models\Producto;
use App\Models\StockBase;
use App\Models\StockMovimiento;
use App\Models\Usuario;
use App\Models\Venta;
use App\Support\Dinero;
use App\Support\NegocioActual;
use App\Support\Texto;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Pedidos: cotización → en proceso → listo → entregado. Cada pago (adelanto, a cuenta, saldo)
 * entra como una venta del día, igual que en el sistema anterior.
 */
class Pedidos
{
    public function __construct(private Numeracion $numeracion, private Ventas $ventas) {}

    public const COND_ENTREGA = 'Inmediata, o según cantidad (a coordinar)';

    public const COND_PAGO = 'Al contado. En pedidos grandes, 50% de adelanto y 50% a la entrega.';

    public function anotar(Pedido $p, string $que, ?Usuario $u = null): void
    {
        $u ??= Auth::user();
        PedidoHistorial::create(['pedido_id' => $p->id, 'que' => mb_substr($que, 0, 250), 'usuario_id' => $u?->id,
            'vendedor' => $u ? Texto::primerNombre($u->nombre) : null, 'ocurrido_at' => now()]);
    }

    /** precio de hoy: el del catálogo para ese producto y opción; si ya no existe, el que tenía */
    public function precioHoy(?int $productoId, ?string $detalle, int $precio): int
    {
        $p = $productoId ? Producto::with('opciones')->find($productoId) : null;
        if (! $p || $p->opciones->isEmpty()) {
            return $precio;
        }
        $o = ($detalle ? $p->opciones->firstWhere('etiqueta', $detalle) : null) ?? ($p->opciones->count() === 1 ? $p->opciones->first() : null);

        return $o ? $o->precio : $precio;
    }

    /**
     * Crea o edita un pedido.
     *
     * @param  array  $d  etapa, cliente{nombre,cel,doc,inst,dir}, cliente_id, items[{pid,nombre,det,cant,precio}], detalle, monto, descuento,
     *                    validez, fecha, cond_entrega, cond_pago, notas, fecha_entrega, hora, responsable
     */
    public function guardar(?Pedido $p, array $d, Usuario $u, int $adelanto = 0, string $metodo = 'efectivo'): Pedido
    {
        $items = [];
        foreach ($d['items'] ?? [] as $l) {
            $nombre = trim((string) ($l['nombre'] ?? ''));
            $cant = (int) ($l['cant'] ?? 0);
            if ($nombre === '' || $cant <= 0) {
                continue;
            }
            $items[] = ['producto_id' => ! empty($l['pid']) ? (int) $l['pid'] : null, 'nombre' => $nombre, 'detalle' => trim((string) ($l['det'] ?? '')) ?: null,
                'cantidad' => $cant, 'precio' => max(0, (int) ($l['precio'] ?? 0))];
        }
        $c = $d['cliente'] ?? [];
        $nombre = trim((string) ($c['nombre'] ?? ''));
        $sub = array_sum(array_map(fn ($l) => $l['cantidad'] * $l['precio'], $items));
        $desc = $items ? max(0, (int) ($d['descuento'] ?? 0)) : 0;
        if ($desc >= $sub) {
            $desc = 0;
        }
        $total = $items ? $sub - $desc : max(0, (int) ($d['monto'] ?? 0));
        $pagado = $p ? $p->pagado() : 0;
        $doc = preg_replace('/\D/', '', (string) ($c['doc'] ?? ''));

        if ($nombre === '') {
            throw new ErrorNegocio('Escribe el nombre del cliente.');
        }
        if (! $items && trim((string) ($d['detalle'] ?? '')) === '') {
            throw new ErrorNegocio('Escribe qué hay que hacer o agrega productos.');
        }
        if ($total <= 0) {
            throw new ErrorNegocio($items ? 'Revisa los precios: el total sale en cero.' : 'Escribe el total del trabajo.');
        }
        if ($total < $pagado) {
            throw new ErrorNegocio('El total no puede ser menor que lo ya pagado ('.Dinero::s($pagado).').');
        }
        if ($doc !== '' && ! (strlen($doc) === 8 || (strlen($doc) === 11 && Comprobantes::rucValido($doc)))) {
            throw new ErrorNegocio(strlen($doc) === 11 ? 'Ese RUC no es válido.' : 'El DNI tiene 8 números y el RUC 11.');
        }
        if ($adelanto > $total) {
            throw new ErrorNegocio('El adelanto no puede pasar el total.');
        }

        return DB::transaction(function () use ($p, $d, $u, $items, $c, $nombre, $desc, $total, $doc, $adelanto, $metodo) {
            // el cliente queda guardado (por su celular o documento) para verlo en su ficha
            $clienteId = $d['cliente_id'] ?? $p?->cliente_id;
            $cel = Texto::cel9((string) ($c['cel'] ?? ''));
            if (! $clienteId) {
                $x = (strlen($cel) === 9 ? Cliente::where('celular', $cel)->first() : null) ?? ($doc ? Cliente::where('documento', $doc)->first() : null);
                if (! $x && strlen($cel) === 9) {
                    $x = Cliente::create(['uid' => Texto::nuevoUid(), 'nombre' => $nombre, 'celular' => $cel]);
                }
                $clienteId = $x?->id;
            }
            if ($clienteId && $doc && ($x = Cliente::find($clienteId)) && ! $x->documento) {
                $x->update(['documento' => $doc]);
            }
            $etapa = $p ? $p->etapa : (($d['etapa'] ?? 'proceso') === 'cotizado' ? 'cotizado' : 'proceso');
            $datos = [
                'etapa' => $etapa, 'cliente_id' => $clienteId,
                'cliente' => ['nombre' => $nombre, 'cel' => $cel ?: trim((string) ($c['cel'] ?? '')), 'doc' => $doc, 'inst' => trim((string) ($c['inst'] ?? '')), 'dir' => trim((string) ($c['dir'] ?? ''))],
                'detalle' => trim((string) ($d['detalle'] ?? '')) ?: null, 'total' => $total, 'descuento' => $desc,
                'validez' => max(1, (int) ($d['validez'] ?? 7)), 'fecha' => $d['fecha'] ?? ($p?->fecha ?? today()),
                'cond_entrega' => trim((string) ($d['cond_entrega'] ?? '')) ?: null, 'cond_pago' => trim((string) ($d['cond_pago'] ?? '')) ?: null,
                'notas' => trim((string) ($d['notas'] ?? '')) ?: null,
                'fecha_entrega' => ($d['fecha_entrega'] ?? '') ?: null, 'hora_entrega' => ($d['hora'] ?? '') ?: null,
                'responsable' => ($d['responsable'] ?? '') ?: null,
            ];
            if ($p) {
                $p->update($datos);
                $this->anotar($p, 'Editado', $u);
            } else {
                $max = (int) Pedido::max('numero');
                $p = Pedido::create($datos + ['uid' => 'pd-'.Texto::nuevoUid(), 'numero' => $this->numeracion->siguiente('pedido', $max),
                    'cotizada' => $etapa === 'cotizado', 'usuario_id' => $u->id, 'vendedor' => $u->nombre, 'creado_at' => now()]);
                $this->anotar($p, $etapa === 'cotizado' ? 'Cotización creada' : 'Encargo registrado', $u);
            }
            $p->items()->delete();
            foreach ($items as $i => $l) {
                $prod = $l['producto_id'] ? Producto::withTrashed()->find($l['producto_id']) : null;
                $p->items()->create($l + ['producto_uid' => $prod?->uid, 'producto_id' => $prod?->id, 'subtotal' => $l['cantidad'] * $l['precio'], 'orden' => $i]);
            }
            $p->load('items', 'pagos');
            if ($adelanto > 0) {
                $this->registrarPago($p, $adelanto, $metodo, 'Adelanto', $u);
            }

            return $p->fresh(['items', 'pagos']);
        });
    }

    /** datos del comprobante que pide el cliente del pedido (RUC → factura, DNI → boleta con DNI) */
    public function datosComprobante(Pedido $p): ?array
    {
        $d = preg_replace('/\D/', '', $p->dato('doc'));
        if (strlen($d) === 11 && Comprobantes::rucValido($d) && app(Comprobantes::class)->puedeFactura()) {
            return ['tipo' => '01', 'pide' => true, 'doc' => ['td' => '6', 'nd' => $d, 'nom' => $p->dato('nombre'), 'dir' => $p->dato('dir')]];
        }
        if (strlen($d) === 8) {
            return ['tipo' => '03', 'pide' => true, 'doc' => ['td' => '1', 'nd' => $d, 'nom' => $p->dato('nombre'), 'dir' => '']];
        }

        return null;
    }

    /** Adelanto o pago a cuenta: entra como venta de hoy */
    public function registrarPago(Pedido $p, int $monto, string $metodo, string $tipo, Usuario $u): Venta
    {
        $p->loadMissing('items', 'pagos');
        if ($monto <= 0) {
            throw new ErrorNegocio('Escribe cuánto paga.');
        }
        if ($monto > $p->saldo()) {
            throw new ErrorNegocio('Es más que el saldo ('.Dinero::s($p->saldo()).').');
        }
        // los adelantos y pagos a cuenta se reciben; no se pueden «fiar» (eso se hace en el punto de venta, con su permiso)
        if (! array_key_exists($metodo, app(NegocioActual::class)->obligatorio()->metodosActivos())) {
            throw new ErrorNegocio('Elige cómo pagó.');
        }

        return DB::transaction(function () use ($p, $monto, $metodo, $tipo, $u) {
            $v = $this->ventas->registrar($u, [
                'lineas' => [['producto_id' => null, 'producto_uid' => 'pedido', 'nombre' => $tipo.' pedido '.$p->numeroTxt(),
                    'detalle' => mb_substr($p->descripcion(), 0, 60), 'cantidad' => 1, 'precio' => $monto]],
                'metodo' => $metodo, 'cliente_id' => $p->cliente_id, 'pedido_uid' => $p->uid,
                'cpe' => $this->datosComprobante($p) ?? [],
            ]);
            $this->anotarPago($p, $v, $tipo, $u);

            return $v;
        });
    }

    public function anotarPago(Pedido $p, Venta $v, string $tipo, Usuario $u): void
    {
        $p->pagos()->create(['uid' => Texto::nuevoUid(), 'monto' => $v->total, 'metodo' => $v->metodo, 'tipo' => $tipo, 'venta_uid' => $v->uid,
            'fecha' => $v->fecha, 'vendedor' => $u->nombre, 'pagado_at' => $v->vendida_at]);
        $this->anotar($p, $tipo.' '.Dinero::s($v->total).' ('.app(NegocioActual::class)->obligatorio()->nombreMetodo($v->metodo).')', $u);
        $p->unsetRelation('pagos');
    }

    public function marcarListo(Pedido $p): void
    {
        if ($p->etapa !== 'proceso') {
            return;
        }
        $p->update(['etapa' => 'listo', 'listo_at' => now()]);
        $this->anotar($p, 'Marcado listo');
    }

    /** El cliente aceptó la cotización: pasa a encargo */
    public function aceptar(Pedido $p, ?string $fechaEntrega, ?string $hora, int $adelanto, string $metodo, Usuario $u): void
    {
        if (! in_array($p->etapa, ['cotizado'], true)) {
            return;
        }
        if ($adelanto < 0 || $adelanto > $p->total) {
            throw new ErrorNegocio('El adelanto no puede pasar el total ('.Dinero::s($p->total).').');
        }
        DB::transaction(function () use ($p, $fechaEntrega, $hora, $adelanto, $metodo, $u) {
            $p->update(['etapa' => 'proceso', 'aceptado_at' => now(), 'fecha_entrega' => $fechaEntrega ?: today()->addDay(), 'hora_entrega' => $hora ?: null]);
            $this->anotar($p, 'Aceptó la cotización', $u);
            if ($adelanto > 0) {
                $this->registrarPago($p->fresh(['items', 'pagos']), $adelanto, $metodo, 'Adelanto', $u);
            }
        });
    }

    public function rechazar(Pedido $p): void
    {
        if ($p->etapa !== 'cotizado') {
            return;
        }
        $p->update(['etapa' => 'rechazado', 'cerrado_at' => now()]);
        $this->anotar($p, 'No aceptó');
    }

    public function reabrir(Pedido $p): void
    {
        if ($p->etapa !== 'rechazado') {
            return;
        }
        $p->update(['etapa' => 'cotizado', 'cerrado_at' => null]);
        $this->anotar($p, 'Volvió a cotización');
    }

    /** Cotización vencida: se renueva con la fecha y los precios de hoy */
    public function renovar(Pedido $p): void
    {
        if ($p->etapa !== 'cotizado') {
            return;
        }
        DB::transaction(function () use ($p) {
            foreach ($p->items as $l) {
                $pr = $this->precioHoy($l->producto_id, $l->detalle, $l->precio);
                $l->update(['precio' => $pr, 'subtotal' => (int) round($pr * $l->cantidad)]);
            }
            $p->load('items');
            $total = $p->items->isNotEmpty() ? max(0, $p->subtotal() - $p->descuento) : $p->total;
            $p->update(['fecha' => today(), 'total' => $total]);
            $this->anotar($p, 'Cotización renovada');
        });
    }

    public function responsable(Pedido $p, ?string $quien): void
    {
        $p->update(['responsable' => $quien ?: null]);
        $this->anotar($p, $quien ? 'Lo hace '.$quien : 'Sin responsable');
    }

    /** Entregar un pedido ya pagado */
    public function entregar(Pedido $p, Usuario $u): void
    {
        $p->loadMissing('items', 'pagos');
        if (! $p->activo()) {
            return;
        }
        if ($p->saldo() > 0) {
            throw new ErrorNegocio('Todavía debe '.Dinero::s($p->saldo()).': cóbralo al entregar.');
        }
        DB::transaction(function () use ($p, $u) {
            $p->update(['etapa' => 'entregado', 'entregado_at' => now()]);
            $this->salidaStock($p, $u);
            $this->anotar($p, 'Entregado', $u);
        });
    }

    public function deshacerEntrega(Pedido $p): void
    {
        if ($p->etapa !== 'entregado' || $p->directa) {
            return;
        }
        DB::transaction(function () use ($p) {
            $this->quitarStock($p);
            $p->update(['etapa' => $p->listo_at ? 'listo' : 'proceso', 'entregado_at' => null]);
            $this->anotar($p, 'Se deshizo la entrega');
        });
    }

    /**
     * Antes de cobrar un pedido en la caja: lo bloquea hasta terminar la transacción y revisa que siga cobrable.
     * Así, si está abierto en dos pestañas o dos equipos, el segundo cobro no pasa. Llamar dentro de una transacción.
     */
    public function bloquearParaCobro(Pedido $p, bool $directo): Pedido
    {
        $p = Pedido::whereKey($p->id)->lockForUpdate()->firstOrFail();
        $p->load('items', 'pagos');
        $sigue = $directo ? $p->etapa === 'cotizado' : $p->activo() && $p->saldo() > 0;
        if (! $sigue) {
            throw new ErrorNegocio('El pedido '.$p->numeroTxt().' ya se cobró o cambió desde otro equipo. Vacía la caja y revísalo en Pedidos.');
        }

        return $p;
    }

    /** Después de cobrar en la caja el saldo (o todo, si fue directo) */
    public function cobrado(Pedido $p, Venta $v, bool $directo, Usuario $u): void
    {
        DB::transaction(function () use ($p, $v, $directo, $u) {
            $this->bloquearParaCobro($p, $directo);
            $this->anotarPago($p, $v, $directo ? 'Venta' : 'Saldo', $u);
            $p->update(['etapa' => 'entregado', 'entregado_at' => now()] + ($directo ? ['directa' => true, 'aceptado_at' => $p->aceptado_at ?? now()] : []));
            if (! $directo) {
                $this->salidaStock($p, $u);
            }
            $this->anotar($p, ($directo ? 'Cobrado y entregado: ' : 'Entregado, cobró ').Dinero::s($v->total).' ('.app(NegocioActual::class)->obligatorio()->nombreMetodo($v->metodo).')', $u);
        });
    }

    /** los útiles con control de stock salen al entregar (en venta directa ya los descontó la venta) */
    public function salidaStock(Pedido $p, Usuario $u): void
    {
        foreach ($p->items as $l) {
            if (! $l->producto_id) {
                continue;
            }
            $prod = Producto::with('insumos.insumo')->find($l->producto_id);
            $salen = [[$l->producto_id, $l->nombre, $l->cantidad]];
            foreach ($prod?->insumos ?? [] as $x) {
                $salen[] = [$x->insumo_id, $x->insumo?->nombre, round($x->cantidad * $l->cantidad, 3)];
            }
            foreach ($salen as [$id, $nom, $cant]) {
                if (StockBase::where('producto_id', $id)->exists()) {
                    StockMovimiento::create(['uid' => Texto::nuevoUid(), 'producto_id' => $id, 'nombre' => $nom, 'tipo' => 'salida', 'cantidad' => $cant,
                        'nota' => 'Pedido '.$p->numeroTxt(), 'pedido_uid' => $p->uid, 'usuario_id' => $u->id, 'vendedor' => $u->nombre, 'ocurrido_at' => now()]);
                }
            }
        }
    }

    public function quitarStock(Pedido $p): void
    {
        StockMovimiento::where('pedido_uid', $p->uid)->delete();
    }

    public function eliminar(Pedido $p): void
    {
        DB::transaction(function () use ($p) {
            $this->quitarStock($p);
            Bitacora::registrar('pedido', 'Eliminó el pedido '.$p->numeroTxt().' de '.$p->dato('nombre'));
            $p->delete();
        });
    }

    public function guardarComoLista(Pedido $p, string $nombre): PlantillaUtiles
    {
        return PlantillaUtiles::create(['uid' => Texto::nuevoUid(), 'nombre' => mb_substr(trim($nombre), 0, 120), 'institucion' => $p->dato('inst') ?: null,
            'items' => $p->items->map(fn ($l) => ['pid' => $l->producto_id, 'sid' => $l->producto_uid, 'nombre' => $l->nombre, 'det' => (string) $l->detalle,
                'cant' => (int) $l->cantidad, 'precio' => $l->precio])->values()->all()]);
    }

    /** Útiles de una lista con los precios de hoy */
    public function itemsDeLista(PlantillaUtiles $t): array
    {
        $uids = collect($t->items)->pluck('sid')->filter()->unique()->all();
        $porUid = Producto::whereIn('uid', $uids)->pluck('id', 'uid');

        return collect($t->items)->map(function ($l) use ($porUid) {
            $pid = $l['pid'] ?? ($porUid[$l['sid'] ?? ''] ?? null);

            return ['pid' => $pid, 'nombre' => $l['nombre'] ?? '', 'det' => (string) ($l['det'] ?? ''), 'cant' => (int) ($l['cant'] ?? 1),
                'precio' => $this->precioHoy($pid, ($l['det'] ?? '') ?: null, (int) ($l['precio'] ?? 0))];
        })->values()->all();
    }
}
