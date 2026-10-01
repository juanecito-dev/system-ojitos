<?php

namespace App\Services;

use App\Models\Negocio;
use App\Models\Pedido;
use App\Models\Venta;
use App\Support\Dinero;
use App\Support\Texto;
use Barryvdh\DomPDF\Facade\Pdf;

/**
 * Filas del ticket (nota de venta), las mismas de ticketRows() del sistema anterior.
 * Con ellas se arma el PDF (80 o 58 mm) y la imagen para WhatsApp.
 */
class Ticket
{
    public function filas(Venta $v, Negocio $neg): array
    {
        $r = [];
        $C = function ($t, $st = null) use (&$r) {
            $r[] = ['t' => (string) $t, 'a' => 'c', 'st' => $st];
        };
        $L = function ($a, $b = null, $st = null) use (&$r) {
            $r[] = ['t' => (string) $a, 'r' => $b === null ? '' : (string) $b, 'st' => $st];
        };
        $H = function () use (&$r) {
            $r[] = ['hr' => true];
        };
        $n = fn ($x) => Venta::cant($x);

        if ($neg->logo) {
            $r[] = ['img' => $neg->logo, 'w' => 0.5];
        }
        $C(mb_strtoupper($neg->nombre), 'title');
        foreach ([$neg->giro, $neg->titular, $neg->ruc ? 'RUC '.$neg->ruc : null, $neg->direccion, $neg->celular ? 'Cel. '.$neg->celular : null] as $x) {
            if ($x) {
                $C($x);
            }
        }
        $H();
        $C('NOTA DE VENTA', 'bold');
        $L('N° '.$v->numeroTicket(), $v->vendida_at->format('d/m/Y H:i'));
        if ($v->vendedor) {
            $L('Atendido por', $v->vendedor);
        }
        $H();
        foreach ($v->items as $l) {
            $L($n($l->cantidad).' x '.$l->nombre.($l->detalle ? ' ('.$l->detalle.')' : ''));
            $L('   '.Dinero::n($l->precio).' c/u'.($l->precio_lista && $l->precio_lista != $l->precio ? ' (antes '.Dinero::n($l->precio_lista).')' : ''), Dinero::n($l->subtotal));
        }
        $H();
        if ($v->descuento) {
            $L('Subtotal', Dinero::s($v->total + $v->descuento));
            $L('Descuento', '-'.Dinero::n($v->descuento));
        }
        $L('TOTAL', Dinero::s($v->total), 'big');
        $L('Pago', $neg->nombreMetodo($v->metodo));
        if ($v->cliente) {
            $L('Cliente', $v->cliente->nombre);
        }
        if ($v->abono) {
            $L('Pagó deuda', Dinero::s($v->abono));
        }
        $pago = $v->pago ?: $v->total;
        if ($pago > $v->total) {
            $L('Recibido', Dinero::s($pago));
            $L('Vuelto', Dinero::s($pago - $v->total));
        }
        $H();
        $C('Este documento no es comprobante de pago.', 'small');
        $C($v->boleta ? ($v->comprobante_numero ? 'Comprobante '.$v->comprobante_numero.' emitido aparte.' : 'Comprobante electrónico emitido aparte.') : 'Si necesita boleta o factura, solicítela.', 'small');
        $C($neg->ajuste('tkPie') ?: '¡Gracias por su preferencia!');
        if ($neg->ajuste('tkQr') && $neg->qr_yape) {
            $r[] = ['img' => $neg->qr_yape, 'w' => 0.45];
            $C('Yape '.($neg->ajuste('yapeCel') ?: $neg->celular), 'small');
        }

        return $r;
    }

    /** PDF del ancho de la ticketera y del alto justo (se mide el contenido en una primera pasada) */
    public function pdf(array $filas, int $ancho, string $titulo)
    {
        $mmPt = 72 / 25.4;
        $vista = ['filas' => $filas, 'ancho' => $ancho, 'titulo' => $titulo];
        $alto = null;
        $medir = Pdf::loadView('pdf.ticket', $vista)->setPaper([0, 0, $ancho * $mmPt, 2000 * $mmPt]);
        $medir->getDomPDF()->setCallbacks(['medir' => ['event' => 'end_frame', 'f' => function ($frame) use (&$alto) {
            if (strtolower($frame->get_node()->nodeName) === 'body') {
                $alto = $frame->get_margin_height();
            }
        }]]);
        $medir->render();
        $margen = ($ancho === 58 ? 3 : 5) * 2 * $mmPt;
        $altoPt = $alto ? $alto + $margen + 4 : $this->alto($filas, $ancho) * $mmPt;

        return Pdf::loadView('pdf.ticket', $vista)->setPaper([0, 0, $ancho * $mmPt, max(60 * $mmPt, $altoPt)]);
    }

    /** Orden de trabajo del pedido (se entrega al cliente para recoger) */
    public function filasOrden(Pedido $p, Negocio $neg): array
    {
        $r = [];
        $C = function ($t, $st = null) use (&$r) {
            $r[] = ['t' => (string) $t, 'a' => 'c', 'st' => $st];
        };
        $L = function ($a, $b = null, $st = null) use (&$r) {
            $r[] = ['t' => (string) $a, 'r' => $b === null ? '' : (string) $b, 'st' => $st];
        };
        $H = function () use (&$r) {
            $r[] = ['hr' => true];
        };
        $C(mb_strtoupper($neg->nombre), 'title');
        foreach ([$neg->giro, $neg->direccion, $neg->celular ? 'Cel. '.$neg->celular : null] as $x) {
            if ($x) {
                $C($x);
            }
        }
        $H();
        $C('ORDEN DE TRABAJO', 'bold');
        $L($p->numeroTxt(), $p->creado_at->format('d/m/Y H:i'));
        if ($p->vendedor) {
            $L('Atendido por', $p->vendedor);
        }
        $H();
        $L('Cliente', $p->dato('nombre'));
        if ($p->celular()) {
            $L('Celular', Texto::fmtCel($p->celular()));
        }
        $H();
        if ($p->items->isNotEmpty()) {
            foreach ($p->items as $l) {
                $L(Venta::cant($l->cantidad).' x '.$l->nombre.($l->detalle ? ' ('.$l->detalle.')' : ''));
                $L('   '.Dinero::n($l->precio).' c/u', Dinero::n($l->subtotal));
            }
            if ($p->descuento) {
                $L('Descuento', '-'.Dinero::n($p->descuento));
            }
        } else {
            $L((string) $p->detalle);
        }
        $H();
        $L('TOTAL', Dinero::s($p->total), 'big');
        $L('A cuenta', Dinero::s($p->pagado()));
        $L('SALDO', Dinero::s($p->saldo()), 'bold');
        if ($p->fecha_entrega) {
            $H();
            $L('Entrega', ucfirst($p->fecha_entrega->translatedFormat('D')).' '.$p->fecha_entrega->format('d/m/Y').($p->hora_entrega ? ' '.PedidoTextos::hora($p->hora_entrega) : ''), 'bold');
        }
        $H();
        $C('Presente este número al recoger su pedido.', 'small');
        $C('Este documento no es comprobante de pago.', 'small');

        return $r;
    }

    /** ancho del papel: el de este equipo o el del negocio */
    public static function ancho(Negocio $neg): int
    {
        $eq = (int) request()->cookie('ticket_ancho');
        $w = $eq ?: (int) $neg->ajuste('tkAncho');

        return $w === 58 ? 58 : 80;
    }

    /** alto aproximado del papel en mm para que el PDF no corte ni sobre papel */
    public function alto(array $filas, int $ancho): float
    {
        $f = $ancho === 58 ? 0.8 : 1;
        $m = $ancho === 58 ? 3 : 5;
        $inner = $ancho - 2 * $m;
        $st = ['title' => [15, 6.7], 'bold' => [9.5, 4.2], 'big' => [11.5, 5.1], 'small' => [7.2, 3.2]];
        $h = 2 * $m + 3;
        foreach ($filas as $x) {
            if (isset($x['img'])) {
                $h += $inner * ($x['w'] ?? 0.5) + 2;

                continue;
            }
            if (! empty($x['hr'])) {
                $h += 3.2;

                continue;
            }
            [$pt, $lh] = $st[$x['st'] ?? ''] ?? [8.6, 3.8];
            $charMm = $pt * $f * 0.6 * 0.3528;
            $ancho_t = $inner - (! empty($x['r']) ? mb_strlen($x['r']) * $charMm + 3 : 0);
            $lineas = max(1, (int) ceil(mb_strlen($x['t']) * $charMm / max(10, $ancho_t)));
            $h += $lineas * $lh * $f;
        }

        return max(60, $h);
    }
}
