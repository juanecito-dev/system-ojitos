<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use App\Models\Compra;
use App\Models\Comprobante;
use App\Models\Documento;
use App\Models\Pedido;
use App\Models\Producto;
use App\Models\Venta;
use App\Services\Clientes;
use App\Services\Comprobantes;
use App\Services\Inventario;
use App\Services\PedidoTextos;
use App\Services\Redaccion;
use App\Services\Reportes;
use App\Services\Stock;
use App\Services\Ticket;
use App\Support\Catalogos;
use App\Support\Dinero;
use App\Support\NegocioActual;
use App\Support\Texto;
use App\Support\Valida;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/** Documentos: estado de cuenta del cliente, proforma y orden del pedido, registro de ventas del mes. */
class DocumentosController extends Controller
{
    public static function a4(string $titulo, string $recuadro, string $cuerpo, ?string $pie = null)
    {
        return Pdf::loadView('pdf.a4', ['neg' => app(NegocioActual::class)->obligatorio(), 'titulo' => $titulo,
            'recuadro' => $recuadro, 'cuerpo' => $cuerpo, 'pie' => $pie])->setPaper('a4');
    }

    public function estadoCuenta(string $uid, Clientes $srv)
    {
        $c = Cliente::where('uid', $uid)->firstOrFail();
        $neg = app(NegocioActual::class)->obligatorio();
        $e = $srv->estadoCuenta($c);
        $recuadro = '<div class="t">ESTADO DE CUENTA</div><div class="small">Al '.now()->format('d/m/Y H:i').'</div>';
        $cuerpo = view('pdf.estado-cuenta', ['c' => $c, 'e' => $e, 'neg' => $neg])->render();

        return self::a4('Estado de cuenta '.$c->nombre, $recuadro, $cuerpo, 'Este documento no es un comprobante de pago. Gracias por su preferencia.')
            ->stream('estado-de-cuenta-'.Str::slug(Texto::sunat($c->nombre)).'-'.today()->toDateString().'.pdf');
    }

    public function proforma(string $uid)
    {
        $p = Pedido::with(['items', 'pagos'])->where('uid', $uid)->firstOrFail();
        $recuadro = '<div class="t" style="font-size:14pt">PROFORMA</div><div style="font-weight:bold;font-size:11pt">'.e($p->numeroTxt()).'</div><div class="small">Fecha: '.$p->fecha->format('d/m/Y').'</div>';
        $cuerpo = view('pdf.proforma', ['p' => $p, 'igv' => PedidoTextos::igv(app(Comprobantes::class)->config()['igv'])])->render();

        return self::a4('Proforma '.$p->numeroTxt(), $recuadro, $cuerpo, ($p->vendedor ? 'Atendido por: '.$p->vendedor.'   ·   ' : '').'Gracias por su preferencia.')
            ->stream('proforma-'.str_pad((string) $p->numero, 6, '0', STR_PAD_LEFT).'-'.Str::slug(Texto::sunat($p->dato('nombre'))).'.pdf');
    }

    public function ordenTrabajo(string $uid, Ticket $ticket)
    {
        $p = Pedido::with(['items', 'pagos'])->where('uid', $uid)->firstOrFail();
        $neg = app(NegocioActual::class)->obligatorio();

        return $ticket->pdf($ticket->filasOrden($p, $neg), Ticket::ancho($neg), 'Orden de trabajo '.$p->numeroTxt())
            ->stream('orden-'.str_pad((string) $p->numero, 6, '0', STR_PAD_LEFT).'.pdf');
    }

    /** Inventario valorizado: lo que hay de cada producto a precio de costo, por grupo (para el contador o el cierre del año) */
    public function inventarioValorizado(Inventario $inv)
    {
        abort_unless(auth()->user()->puede('costos'), 403);
        $st = $inv->stock();
        $P = Producto::orderBy('orden')->get()->filter(fn ($p) => array_key_exists($p->id, $st))
            ->map(fn ($p) => ['p' => $p, 'n' => max(0, $st[$p->id]), 'valor' => max(0, $st[$p->id]) * ($p->costo ?? 0)]);
        $recuadro = '<div class="t">INVENTARIO VALORIZADO</div><div class="small">Al '.now()->format('d/m/Y H:i').'</div>';
        $cuerpo = view('pdf.inventario', ['grupos' => $P->groupBy(fn ($x) => $x['p']->grupo), 'total' => $P->sum('valor'), 'sinCosto' => $P->filter(fn ($x) => ! $x['p']->costo)->count()])->render();

        return self::a4('Inventario valorizado', $recuadro, $cuerpo, 'Stock según el sistema, a precio de costo promedio.')
            ->stream('inventario-valorizado-'.today()->toDateString().'.pdf');
    }

    /** Reporte de gestión del periodo en PDF (solo con el permiso de ver costos) */
    public function reporte(Request $req, Reportes $srv)
    {
        abort_unless(auth()->user()->puede('costos'), 403);
        $r = (string) $req->query('r', 'custom');
        [$a, $b] = Reportes::rango('custom', $req->query('desde'), $req->query('hasta'));
        $cmp = $req->query('cmp') === 'anio' ? 'anio' : 'anterior';
        [$pa, $pb] = Reportes::previo($a, $b, isset(Reportes::RANGOS[$r]) ? $r : 'custom', $cmp);
        $R = $srv->calcular($a, $b);
        $recuadro = '<div class="t">REPORTE DE GESTIÓN</div><div class="small">'.Carbon::parse($a)->format('d/m/Y').' al '.Carbon::parse($b)->format('d/m/Y').'</div>';
        $cuerpo = view('pdf.reporte', Reportes::tablas($R) + ['R' => $R, 'P' => $srv->calcular($pa, $pb), 'pa' => $pa, 'pb' => $pb,
            'cmpTxt' => $cmp === 'anio' || $r === 'anio' || Reportes::dias($a, $b) > 92 ? 'las mismas fechas del año pasado' : 'el periodo anterior',
            'neg' => app(NegocioActual::class)->obligatorio()])->render();

        return self::a4('Reporte '.$a.' a '.$b, $recuadro, $cuerpo, 'Generado el '.now()->format('d/m/Y H:i').'. Ganancia = vendido - costo de lo vendido - gastos - pérdidas.')
            ->stream('reporte-'.$a.'-a-'.$b.'.pdf');
    }

    /** Ventas de un periodo para Excel (mismas columnas que las ventas del día) */
    public function ventasPeriodo(Request $req)
    {
        [$a, $b] = Reportes::rango('custom', $req->query('desde'), $req->query('hasta'));
        $neg = app(NegocioActual::class)->obligatorio();
        $yo = auth()->user();
        $q = fn ($s) => '"'.str_replace('"', '""', preg_replace('/^([=+\-@])/', "'$1", (string) $s)).'"';
        $out = [implode(';', ['Fecha', 'Hora', 'Servicio', 'Detalle', 'Cantidad', 'Precio unit.', 'Subtotal', 'Total venta', 'Pago', 'Comprobante', 'Vendedor'])];
        Venta::with('items')->whereBetween('fecha', [$a, $b])->when(! $yo->puede('verTodo'), fn ($x) => $x->where('usuario_id', $yo->id))->orderBy('vendida_at')
            ->chunk(500, function ($V) use (&$out, $q, $neg) {
                foreach ($V as $v) {
                    foreach ($v->items as $l) {
                        $out[] = implode(';', [$v->fecha->format('d/m/Y'), $v->vendida_at->format('H:i'), $q($l->nombre), $q($l->detalle ?? ''), Venta::cant($l->cantidad),
                            Dinero::n($l->precio), Dinero::n($l->subtotal), Dinero::n($v->total), $neg->nombreMetodo($v->metodo),
                            $l->tercero ? 'Tasa de terceros' : ($v->boleta ? 'Boleta' : ($v->propio() > Catalogos::LIMITE_CIERRE ? 'Pendiente' : 'Cierre del dia')), $q($v->vendedor ?? '')]);
                    }
                }
            });

        return response("\u{FEFF}".implode("\r\n", $out), 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="ventas-'.$a.'-a-'.$b.'.csv"',
        ]);
    }

    /** Registro de compras del mes para el contador (base e IGV solo si el negocio está gravado) */
    public function registroCompras(string $mes)
    {
        abort_unless(Valida::mes($mes), 404);
        $desde = $mes.'-01';
        $F = Compra::with(['proveedor', 'items', 'pagos'])->where('fecha', '>=', $desde)->where('fecha', '<=', Carbon::parse($desde)->endOfMonth()->toDateString())->orderBy('fecha')->get();
        $grav = app(Comprobantes::class)->config()['igv'] === 'gravado';
        $q = fn ($s) => '"'.str_replace('"', '""', preg_replace('/^([=+\-@])/', "'$1", (string) $s)).'"';
        $out = [implode(';', ['Fecha', 'Proveedor', 'RUC', 'Numero', 'Condicion', ...($grav ? ['Base imponible', 'IGV'] : []), 'Total', 'Pagado', 'Saldo', 'Detalle'])];
        foreach ($F as $f) {
            $base = (int) round($f->total / 1.18);
            $out[] = implode(';', [$f->fecha->format('d/m/Y'), $q($f->proveedor?->nombre ?? ''), $q($f->proveedor?->ruc ?? ''), $q($f->numero ?? ''),
                $f->condicion === 'credito' ? 'Credito' : 'Contado', ...($grav ? [Dinero::n($base), Dinero::n($f->total - $base)] : []),
                Dinero::n($f->total), Dinero::n($f->pagado()), Dinero::n($f->saldo()),
                $q($f->items->map(fn ($l) => Stock::formato($l->cantidad).' '.$l->nombre)->join(', ') ?: ($f->nota ?? ''))]);
        }

        return response("\u{FEFF}".implode("\r\n", $out), 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="registro-compras-'.$mes.'.csv"',
        ]);
    }

    /** Registro de ventas del mes para el contador (mismas columnas que antes). Los anulados van en 0; las notas de crédito restan. */
    public function registroVentas(string $mes)
    {
        abort_unless(Valida::mes($mes), 404);
        $desde = $mes.'-01';
        $hasta = Carbon::parse($desde)->endOfMonth()->toDateString();
        $C = Comprobante::where('fecha', '>=', $desde)->where('fecha', '<=', $hasta)->orderBy('emitido_at')->get();
        $q = fn ($s) => '"'.str_replace('"', '""', preg_replace('/^([=+\-@])/', "'$1", (string) $s)).'"';
        $out = [implode(';', ['Fecha', 'Tipo', 'Serie', 'Numero', 'Tipo doc', 'Nro doc', 'Cliente', 'Gravado', 'Exonerado', 'Inafecto', 'IGV', 'Total', 'Estado', 'Referencia', 'Emitido por'])];
        foreach ($C as $c) {
            $sg = $c->tipo === '07' ? -1 : 1;
            $z = fn ($f) => Dinero::n($c->estado === 'anulado' ? 0 : $sg * abs((int) $c->{$f}));
            $out[] = implode(';', [$c->fecha->format('d/m/Y'), Catalogos::TIPOS_COMPROBANTE[$c->tipo] ?? $c->tipo, $c->serie, $c->numero ?? '',
                $c->cliente['td'] ?? '0', $q($c->cliente['nd'] ?? ''), $q($c->cliente['nom'] ?? ''),
                $z('gravado'), $z('exonerado'), $z('inafecto'), $z('igv'), $z('total'), $c->estado, $q($c->referencia ?? ''), $q($c->vendedor ?? '')]);
        }

        return response("\u{FEFF}".implode("\r\n", $out), 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="registro-ventas-'.$mes.'.csv"',
        ]);
    }

    /** Documento de Redacción en PDF (A4, para imprimir) */
    public function redaccionPdf(string $uid, Redaccion $srv)
    {
        $d = Documento::where('uid', $uid)->firstOrFail();

        return $srv->pdf($d)->stream($srv->archivo($d).'.pdf');
    }

    /** Documento de Redacción para abrir y seguir editando en Word */
    public function redaccionWord(string $uid, Redaccion $srv)
    {
        $d = Documento::where('uid', $uid)->firstOrFail();

        return response($srv->word($d), 200, [
            'Content-Type' => 'application/msword; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$srv->archivo($d).'.doc"',
        ]);
    }
}
