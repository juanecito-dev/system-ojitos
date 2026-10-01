<?php

namespace App\Services;

use App\Models\Negocio;
use App\Models\Pedido;
use App\Models\Venta;
use App\Support\Dinero;
use App\Support\Texto;
use Illuminate\Support\Carbon;

/** Textos de los pedidos: fechas de entrega, horas y mensajes de WhatsApp (los mismos del sistema anterior). */
class PedidoTextos
{
    public static function hora(?string $h): string
    {
        if (! $h) {
            return '';
        }
        [$H, $M] = array_map('intval', explode(':', $h) + [0, 0]);

        return (($H % 12) ?: 12).':'.str_pad((string) $M, 2, '0', STR_PAD_LEFT).($H < 12 ? ' a. m.' : ' p. m.');
    }

    public static function entrega(?Carbon $f): string
    {
        if (! $f) {
            return 'Sin fecha';
        }
        if ($f->isToday()) {
            return 'Hoy';
        }
        if ($f->isTomorrow()) {
            return 'Mañana';
        }

        return ucfirst($f->translatedFormat('D j \d\e F'));
    }

    public static function igv(string $modo): string
    {
        return ['exonerado' => 'Precios en soles, exonerados del IGV (Amazonía).', 'gravado' => 'Precios en soles, incluyen IGV.',
            'inafecto' => 'Precios en soles, inafectos al IGV.'][$modo] ?? 'Precios en soles.';
    }

    private static function items(Pedido $p): string
    {
        if ($p->items->isEmpty()) {
            return '• '.$p->detalle;
        }

        return $p->items->map(fn ($l) => '• '.Venta::cant($l->cantidad).' '.$l->nombre.($l->detalle ? ' ('.$l->detalle.')' : '').': '.Dinero::s($l->subtotal))->join("\n")
            .($p->descuento ? "\nDescuento: -".Dinero::n($p->descuento) : '');
    }

    private static function link(Pedido $p, string $txt): ?string
    {
        return $p->celular() ? 'https://wa.me/51'.$p->celular().'?text='.rawurlencode($txt) : null;
    }

    private static function hola(Pedido $p, Negocio $n): string
    {
        return 'Hola '.Texto::primerNombre($p->dato('nombre')).', te saluda '.$n->nombre.'.';
    }

    public static function cotizacion(Pedido $p, Negocio $n): ?string
    {
        return self::link($p, self::hola($p, $n).' Te enviamos la cotización '.$p->numeroTxt().":\n".self::items($p)."\n*Total: ".Dinero::s($p->total)."*\nVálida hasta el ".$p->vence()->format('d/m/Y').'. ¡Quedamos atentos!');
    }

    public static function seguimiento(Pedido $p, Negocio $n): ?string
    {
        return self::link($p, self::hola($p, $n).' ¿Pudiste revisar la cotización '.$p->numeroTxt().' por '.Dinero::s($p->total).'? Es válida hasta el '.$p->vence()->format('d/m/Y').'. Si tienes alguna duda, te ayudamos.');
    }

    public static function orden(Pedido $p, Negocio $n): ?string
    {
        return self::link($p, self::hola($p, $n).' Registramos tu pedido '.$p->numeroTxt().":\n".self::items($p)."\nTotal: ".Dinero::s($p->total)
            .($p->pagado() ? "\nA cuenta: ".Dinero::s($p->pagado())."\nSaldo: ".Dinero::s($p->saldo()) : '')
            .($p->fecha_entrega ? "\nEntrega: ".mb_strtolower(self::entrega($p->fecha_entrega)).($p->hora_entrega ? ', '.self::hora($p->hora_entrega) : '') : '')
            ."\nPresenta este número al recoger. ¡Gracias!");
    }

    public static function listo(Pedido $p, Negocio $n): ?string
    {
        return self::link($p, self::hola($p, $n).' Tu pedido '.$p->numeroTxt().' ya está listo: '.mb_substr($p->descripcion(), 0, 120).'.'
            .($p->saldo() ? ' Saldo por pagar: '.Dinero::s($p->saldo()).'.' : '').' ¡Te esperamos!');
    }
}
