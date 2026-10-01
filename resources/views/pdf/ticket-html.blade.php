@php use App\Support\Dinero; use App\Models\Venta; $pago = $v->pago ?: $v->total; @endphp
<div class="ticket">
    @if ($neg->logo)<div class="c"><img src="{{ $neg->logo }}" alt="" style="max-width:60%;max-height:70px;filter:grayscale(1)"></div>@endif
    <div class="c big">{{ mb_strtoupper($neg->nombre) }}</div>
    <div class="c">{{ $neg->giro }}</div><div class="c">{{ $neg->titular }}</div>
    <div class="c">RUC {{ $neg->ruc }}</div><div class="c">{{ $neg->direccion }}</div><div class="c">Cel. {{ $neg->celular }}</div>
    <hr><div class="c"><b>NOTA DE VENTA</b></div>
    <div class="l"><span>N° {{ $v->numeroTicket() }}</span><span>{{ $v->vendida_at->format('d/m/Y H:i') }}</span></div>
    @if ($v->vendedor)<div class="l"><span>Atendido por</span><span>{{ $v->vendedor }}</span></div>@endif
    <hr>
    @foreach ($v->items as $l)
        <div>{{ Venta::cant($l->cantidad) }} x {{ $l->nombre }}{{ $l->detalle ? ' ('.$l->detalle.')' : '' }}</div>
        <div class="l"><span>&nbsp;&nbsp;{{ Dinero::n($l->precio) }} c/u{{ $l->precio_lista && $l->precio_lista != $l->precio ? ' (antes '.Dinero::n($l->precio_lista).')' : '' }}</span><span>{{ Dinero::n($l->subtotal) }}</span></div>
    @endforeach
    <hr>
    @if ($v->descuento)<div class="l"><span>Subtotal</span><span>{{ Dinero::s($v->total + $v->descuento) }}</span></div><div class="l"><span>Descuento</span><span>-{{ Dinero::n($v->descuento) }}</span></div>@endif
    <div class="l tt"><span>TOTAL</span><span>{{ Dinero::s($v->total) }}</span></div>
    @if ($v->cliente)<div class="l"><span>Cliente</span><span>{{ $v->cliente->nombre }}</span></div>@endif
    @if ($v->abono)<div class="l"><span>Pagó deuda</span><span>{{ Dinero::s($v->abono) }}</span></div>@endif
    <div class="l"><span>Pago</span><span>{{ $neg->nombreMetodo($v->metodo) }}</span></div>
    @if ($pago > $v->total)<div class="l"><span>Recibido</span><span>{{ Dinero::s($pago) }}</span></div><div class="l"><span>Vuelto</span><span>{{ Dinero::s($pago - $v->total) }}</span></div>@endif
    <hr><div class="fine">Este documento no es comprobante de pago.<br>{{ $v->boleta ? ($v->comprobante_numero ? 'Comprobante electrónico '.$v->comprobante_numero.' emitido por separado.' : 'Comprobante electrónico emitido por separado.') : 'Si necesita boleta o factura, solicítela.' }}</div>
    <div class="c" style="margin-top:8px">{{ $neg->ajuste('tkPie') ?: '¡Gracias por su preferencia!' }}</div>
    @if ($neg->ajuste('tkQr') && $neg->qr_yape)<div class="c" style="margin-top:6px"><img src="{{ $neg->qr_yape }}" alt="QR de Yape" style="width:45%"><br>Yape {{ $neg->ajuste('yapeCel') ?: $neg->celular }}</div>@endif
</div>
