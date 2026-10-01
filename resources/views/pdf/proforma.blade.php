@php use App\Support\Dinero; use App\Support\Texto; use App\Models\Venta; $doc = preg_replace('/\D/', '', $p->dato('doc')); @endphp
<table class="cli" style="width:100%">
    <tr><td class="k">Cliente:</td><td><b>{{ $p->dato('nombre') }}</b></td></tr>
    @if ($p->dato('inst'))<tr><td class="k">Institución:</td><td>{{ $p->dato('inst') }}</td></tr>@endif
    @if ($doc)<tr><td class="k">{{ strlen($doc) === 11 ? 'RUC' : 'DNI' }}:</td><td>{{ $doc }}</td></tr>@endif
    @if ($p->celular())<tr><td class="k">Celular:</td><td>{{ Texto::fmtCel($p->celular()) }}</td></tr>@endif
</table>
<table class="tab">
    <thead><tr><th class="c" style="width:14mm">Cant.</th><th>Descripción</th><th class="r" style="width:24mm">P. unit.</th><th class="r" style="width:26mm">Importe</th></tr></thead>
    <tbody>
    @forelse ($p->items as $l)
        <tr><td class="c">{{ Venta::cant($l->cantidad) }}</td><td>{{ $l->nombre }}@if ($l->detalle) <span class="small">({{ $l->detalle }})</span>@endif</td><td class="r">{{ Dinero::n($l->precio) }}</td><td class="r">{{ Dinero::n($l->subtotal) }}</td></tr>
    @empty
        <tr><td class="c">1</td><td>{!! nl2br(e($p->detalle ?: 'Trabajo')) !!}</td><td class="r">{{ Dinero::n($p->total) }}</td><td class="r">{{ Dinero::n($p->total) }}</td></tr>
    @endforelse
    </tbody>
</table>
<table class="tot">
    @if ($p->descuento && $p->items->isNotEmpty())
        <tr><td>Subtotal</td><td class="r">{{ Dinero::s($p->subtotal()) }}</td></tr>
        <tr><td>Descuento</td><td class="r">-{{ Dinero::n($p->descuento) }}</td></tr>
    @endif
    <tr class="grand"><td>TOTAL</td><td class="r">{{ Dinero::s($p->total) }}</td></tr>
</table>
<p class="small" style="margin-top:4mm">Son: {{ Texto::montoLetras($p->total) }}</p>
<div class="cond"><b>Condiciones</b>
    <div>• Validez de la proforma: {{ $p->validez }} días (hasta el {{ $p->vence()->format('d/m/Y') }}).</div>
    @if ($p->cond_entrega)<div>• Tiempo de entrega: {{ $p->cond_entrega }}</div>@endif
    @if ($p->cond_pago)<div>• Forma de pago: {{ $p->cond_pago }}</div>@endif
    <div>• {{ $igv }}</div>
    @if ($p->notas)<div>• {{ $p->notas }}</div>@endif
</div>
