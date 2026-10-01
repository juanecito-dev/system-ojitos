<?php

namespace App\Services;

use App\Events\VentaAnulada as AvisoVentaAnulada;
use App\Events\VentaRegistrada;
use App\Models\CajaMovimiento;
use App\Models\Cliente;
use App\Models\ClienteMovimiento;
use App\Models\Comprobante;
use App\Models\StockBase;
use App\Models\StockMovimiento;
use App\Models\Usuario;
use App\Models\Venta;
use App\Models\VentaAnulada;
use App\Support\Catalogos;
use App\Support\Dinero;
use App\Support\NegocioActual;
use App\Support\Texto;
use App\Support\Valida;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Cobrar, anular y deshacer ventas con las reglas de caja-rapida.html.
 * Los permisos (fiar, descuentos, tope por rol) los revisa la pantalla antes de llamar aquí.
 */
class Ventas
{
    public function __construct(private Numeracion $numeracion, private Comprobantes $comprobantes) {}

    /**
     * @param  array  $d  lineas[], descuento, metodo, recibido, cliente_id, abono,
     *                    cpe => [tipo, pide, doc => [td, nd, nom, dir] | null, emitida, serie, numero],
     *                    origen => [tipo, uid] (de dónde viene la venta, por ejemplo ['pedido', uid])
     *                    (cada línea puede traer su origen: ['documento', uid] es el documento de Redacción que cobra)
     *
     * Al guardar avisa VentaRegistrada: cada módulo (Redacción, Pedidos…) actualiza lo suyo en la misma transacción.
     */
    public function registrar(Usuario $u, array $d): Venta
    {
        $neg = app(NegocioActual::class)->obligatorio();
        $lineas = array_values(array_filter($d['lineas'] ?? [], fn ($l) => ($l['cantidad'] ?? 0) > 0));
        if (! $lineas) {
            throw new ErrorNegocio('El pedido está vacío.');
        }
        foreach ($lineas as $l) {
            if (! (($l['precio'] ?? 0) > 0)) {
                throw new ErrorNegocio('Revisa el precio de '.$l['nombre'].'.');
            }
        }
        $bruto = array_sum(array_map(fn ($l) => $this->sub($l), $lineas));
        $terceros = array_sum(array_map(fn ($l) => ! empty($l['tercero']) ? $this->sub($l) : 0, $lineas));
        $desc = max(0, (int) ($d['descuento'] ?? 0));
        if ($desc && $desc >= $bruto - $terceros) {
            throw new ErrorNegocio('El descuento no puede ser mayor que lo tuyo ('.Dinero::s($bruto - $terceros).').');
        }
        $total = $bruto - $desc;
        $propio = $total - $terceros;

        $metodo = $d['metodo'] ?? 'efectivo';
        if ($metodo !== 'fiado' && ! array_key_exists($metodo, $neg->metodosActivos())) {
            throw new ErrorNegocio('Ese método de pago no está activo.');
        }
        $cliente = ! empty($d['cliente_id']) ? Cliente::find($d['cliente_id']) : null;
        if ($metodo === 'fiado' && ! $cliente) {
            throw new ErrorNegocio('Para fiar, elige o registra al cliente (celular o nombre).');
        }

        $cpe = $d['cpe'] ?? [];
        $this->revisarComprobante($cpe, $propio);

        return DB::transaction(function () use ($u, $d, $lineas, $total, $desc, $metodo, $cliente, $cpe, $propio) {
            $abono = $cliente && $metodo !== 'fiado' ? min((int) ($d['abono'] ?? 0), max(0, $cliente->saldo())) : 0;
            $grand = $total + $abono;
            $recibido = $d['recibido'] ?? null;
            $ahora = now();
            $fecha = $ahora->toDateString();

            $doc = ! empty($cpe['doc']['nd']) ? [
                'td' => ($cpe['tipo'] ?? '03') === '01' ? '6' : ($cpe['doc']['td'] ?? '1'),
                'nd' => trim($cpe['doc']['nd']), 'nom' => trim($cpe['doc']['nom'] ?? ''), 'dir' => trim($cpe['doc']['dir'] ?? ''),
            ] : null;
            $pide = ($cpe['tipo'] ?? '03') === '01' || ! empty($cpe['pide']) || $doc;
            $pedidoCpe = $pide ? array_filter(['tipo' => $cpe['tipo'] ?? '03', 'pide' => (bool) $pide, 'doc' => $doc]) : null;

            $v = Venta::create([
                'uid' => ($d['uid'] ?? null) ?: Texto::nuevoUid(),   // la llave del cobro: un reintento no duplica la venta
                'fecha' => $fecha,
                'numero' => $this->numeracion->ticket($u, $fecha),
                'usuario_id' => $u->id,
                'vendedor' => $u->nombre,
                'cliente_id' => $cliente?->id,
                'total' => $total,
                'descuento' => $desc,
                'metodo' => $metodo,
                'pago' => $metodo === 'efectivo' && $recibido !== null && $recibido >= $grand ? $recibido - $abono : $total,
                'abono' => $abono,
                'comprobante_pedido' => $pedidoCpe,
                'origen_tipo' => $d['origen'][0] ?? null,
                'origen_uid' => $d['origen'][1] ?? null,
                'vendida_at' => $ahora,
            ]);
            foreach ($lineas as $i => $l) {
                $v->items()->create([
                    'producto_id' => $l['producto_id'] ?? null,
                    'producto_uid' => $l['producto_uid'] ?? null,
                    'origen_tipo' => $l['origen'][0] ?? null,
                    'origen_uid' => $l['origen'][1] ?? null,
                    'nombre' => $l['nombre'],
                    'detalle' => ($l['detalle'] ?? '') !== '' ? $l['detalle'] : null,
                    'cantidad' => $l['cantidad'],
                    'precio' => $l['precio'],
                    'precio_lista' => isset($l['lista']) && $l['lista'] != $l['precio'] ? $l['lista'] : null,
                    'subtotal' => $this->sub($l),
                    'costo' => $l['costo'] ?? null,
                    'tercero' => ! empty($l['tercero']),
                    'orden' => $i,
                ]);
            }

            if ($cliente) {
                if ($metodo === 'fiado') {
                    ClienteMovimiento::create([
                        'uid' => Texto::nuevoUid(), 'cliente_id' => $cliente->id, 'tipo' => 'fiado', 'monto' => $total,
                        'detalle' => Str::limit(collect($lineas)->map(fn ($l) => Venta::cant($l['cantidad']).' '.$l['nombre'])->join(', '), 117),
                        'venta_uid' => $v->uid, 'fecha' => $fecha, 'usuario_id' => $u->id, 'vendedor' => $u->nombre, 'ocurrido_at' => $ahora,
                    ]);
                }
                if ($abono) {
                    ClienteMovimiento::create([
                        'uid' => Texto::nuevoUid(), 'cliente_id' => $cliente->id, 'tipo' => 'abono', 'monto' => $abono, 'metodo' => $metodo,
                        'venta_uid' => $v->uid, 'fecha' => $fecha, 'usuario_id' => $u->id, 'vendedor' => $u->nombre, 'ocurrido_at' => $ahora,
                    ]);
                    CajaMovimiento::create([
                        'uid' => Texto::nuevoUid(), 'fecha' => $fecha, 'tipo' => 'ingreso', 'concepto' => 'Pago de fiado', 'nota' => $cliente->nombre,
                        'monto' => $abono, 'metodo' => $metodo, 'referencia' => $v->uid, 'usuario_id' => $u->id, 'vendedor' => $u->nombre, 'ocurrido_at' => $ahora,
                    ]);
                }
                $cliente->visitas += 1;
                $cliente->gastado += $propio;
                $cliente->ultima_visita_at = $ahora;
                if (! $cliente->documento && $doc && in_array($doc['td'], ['1', '6'], true)) {
                    $cliente->documento = $doc['nd'];
                }
                $cliente->save();
            }

            event(new VentaRegistrada($v->load('items'), $u));

            if (! empty($cpe['emitida'])) {
                $this->comprobantes->registrar([
                    'tipo' => $cpe['tipo'] ?? '03', 'serie' => $cpe['serie'] ?? null, 'numero' => $cpe['numero'] ?? null,
                    'cliente' => $doc, 'total' => $propio, 'ventas' => [['k' => $fecha, 'id' => $v->uid]],
                    'descripcion' => $v->load('items')->descripcionSunat(),
                ]);
            }

            return $v->fresh(['items', 'cliente']);
        });
    }

    /** Mismas validaciones que cpeCheck() del sistema anterior */
    public function revisarComprobante(array $cpe, int $propio): void
    {
        $tipo = $cpe['tipo'] ?? '03';
        $doc = $cpe['doc'] ?? [];
        $nd = trim($doc['nd'] ?? '');
        if ($tipo === '01') {
            if (! $this->comprobantes->puedeFactura()) {
                throw new ErrorNegocio('Tu régimen no permite emitir facturas.');
            }
            if (! Comprobantes::rucValido($nd)) {
                throw new ErrorNegocio('Para factura escribe un RUC válido.');
            }
            if (trim($doc['nom'] ?? '') === '') {
                throw new ErrorNegocio('Para factura escribe la razón social.');
            }
        }
        $td = $tipo === '01' ? '6' : ($doc['td'] ?? '1');
        if ($nd !== '' && ! Comprobantes::documentoValido($td, $nd)) {
            throw new ErrorNegocio('Revisa el número de documento del cliente.');
        }
        if (! empty($cpe['emitida']) && $tipo === '03' && $propio > Catalogos::MAX_SIN_DOC && $nd === '') {
            throw new ErrorNegocio('Esta boleta pasa de S/ 700: escribe el DNI del cliente.');
        }
        if (! empty($cpe['emitida']) && ! empty($cpe['serie']) && ! Valida::serie(strtoupper((string) $cpe['serie']), $tipo)) {
            throw new ErrorNegocio('Revisa la serie: 4 letras o números ('.($tipo === '01' ? 'F o E' : 'B o EB').' al inicio).');
        }
        if (! empty($cpe['emitida']) && ! empty($cpe['numero']) && ! Valida::numeroCpe(preg_replace('/\D/', '', (string) $cpe['numero']))) {
            throw new ErrorNegocio('El número del comprobante tiene de 1 a 8 cifras.');
        }
        if (! empty($cpe['emitida']) && ! empty($cpe['numero'])
            && $this->comprobantes->numeroUsado(($cpe['serie'] ?? '') ?: $this->comprobantes->serieDe($tipo), $cpe['numero'])) {
            throw new ErrorNegocio('Ese número de comprobante ya está registrado.');
        }
    }

    /**
     * Anula (o deshace) una venta: revisa su comprobante, su pedido, el stock ya contado y
     * la cuenta del cliente; deja el rastro en ventas_anuladas.
     *
     * Nada se borra: la venta queda con anulada_at y deja de contar como venta. Si su caja ya se
     * cuadró, el dinero se queda en ese cuadre y la devolución sale de la caja de hoy.
     *
     * @return string aviso para mostrar (por ejemplo, que falta la nota de crédito)
     */
    public function anular(Venta $v, Usuario $por, string $motivo = '', string $tipo = 'anulada'): string
    {
        return DB::transaction(function () use ($v, $por, $motivo, $tipo) {
            // bloqueada hasta terminar: si dos equipos la anulan a la vez, el segundo no la resta otra vez
            $v = Venta::with('items')->whereKey($v->id)->lockForUpdate()->first()
                ?? throw new ErrorNegocio('Esa venta ya se anuló.');
            $aviso = '';
            if ($v->comprobante_uid) {
                $c = Comprobante::where('uid', $v->comprobante_uid)->where('estado', '!=', 'anulado')->first();
                if ($c && ($c->cierre_de || count($c->ventas ?? []) > 1)) {
                    $aviso = 'Estaba en la boleta de cierre '.$c->etiqueta().': registra una nota de crédito por '.Dinero::s($v->propio()).' en Facturación.';
                } elseif ($c) {
                    // también al deshacer: si ya se emitió, existe en SUNAT y no se puede borrar del registro
                    $c->update(['estado' => 'anulado', 'motivo' => 'La venta se anuló', 'anulado_at' => now(), 'anulado_por' => $por->nombre]);
                    $aviso = 'Su comprobante '.$c->etiqueta().' quedó anulado en tu registro. Recuerda anularlo también en el portal de SUNAT.';
                }
            }

            // cada módulo deshace lo suyo (Pedidos: el pago o la entrega; Redacción: el documento vuelve a «sin cobrar»)
            $ev = new AvisoVentaAnulada($v, $por, $tipo);
            event($ev);
            $aviso = implode(' ', array_filter([$aviso, ...$ev->avisos]));

            // si la venta es anterior al último conteo de stock, la devolución se anota como entrada
            foreach ($v->items as $l) {
                if ($l->tercero || ! $l->producto_id) {
                    continue;
                }
                $b = StockBase::where('producto_id', $l->producto_id)->first();
                if ($b && $v->vendida_at->lte($b->desde)) {
                    StockMovimiento::create([
                        'uid' => Texto::nuevoUid(), 'producto_id' => $l->producto_id, 'producto_uid' => $l->producto_uid, 'nombre' => $l->nombre,
                        'tipo' => 'entrada', 'cantidad' => $l->cantidad, 'nota' => 'Devolución por venta anulada',
                        'usuario_id' => $por->id, 'vendedor' => $por->nombre, 'ocurrido_at' => now(),
                    ]);
                }
            }

            VentaAnulada::create([
                'uid' => Texto::nuevoUid(), 'fecha' => $v->fecha, 'venta_uid' => $v->uid, 'numero' => $v->numero,
                'venta_at' => $v->vendida_at, 'total' => $v->propio(), 'detalle' => Str::limit($v->resumen(), 117), 'motivo' => $motivo ?: null,
                'tipo' => $tipo, 'usuario_id' => $por->id, 'por' => $por->nombre, 'vendedor_original' => $v->vendedor,
                'anulada_at' => now(), 'venta' => $v->toArray(),
            ]);

            if ($v->cliente_id && ($c = Cliente::find($v->cliente_id))) {
                $c->visitas = max(0, $c->visitas - 1);
                $c->gastado = max(0, $c->gastado - $v->propio());
                $c->save();
            }
            ClienteMovimiento::where('venta_uid', $v->uid)->get()->each->delete();   // quedan marcados como borrados
            $cuadre = app(CuadreCaja::class);
            foreach (CajaMovimiento::where('referencia', $v->uid)->get() as $m) {
                $cuadre->quitar($m, $por, 'Venta anulada');
            }
            $v->forceFill(['anulada_at' => now(), 'devuelta_en_caja' => $cuadre->devolverVenta($v, $por)])->save();

            return $aviso;
        });
    }

    private function sub(array $l): int
    {
        return (int) round($l['cantidad'] * $l['precio']);
    }
}
