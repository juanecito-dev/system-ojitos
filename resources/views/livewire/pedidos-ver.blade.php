@php
    use App\Support\Dinero; use App\Support\Texto; use App\Support\Catalogos; use App\Services\PedidoTextos as PT; use App\Models\Venta;
    $e = $p->etapaVista(); [$tc, $tl] = Catalogos::ETAPAS_PEDIDO[$e] ?? Catalogos::ETAPAS_PEDIDO['proceso'];
    $sal = $p->saldo(); $cotz = in_array($e, ['cotizado', 'vencido'], true); $doc = preg_replace('/\D/', '', $p->dato('doc'));
    $wa = $cotz ? PT::cotizacion($p, $neg) : ($e === 'listo' ? PT::listo($p, $neg) : PT::orden($p, $neg));
@endphp
<section>
    <div class="daybar"><button type="button" class="btn ghost sm" wire:click="volver">‹ Pedidos</button><b style="font-size:1.1rem">{{ $p->numeroTxt() }}</b><span class="tag {{ $tc }}">{{ $tl }}</span>@if ($p->responsable)<span class="cap">lo hace {{ $p->responsable }}</span>@endif</div>
    <div class="actions tools" style="margin-bottom:10px">
        @if ($cotz || $e === 'rechazado')
            <a class="btn" href="{{ route('pedidos.proforma', $p->uid) }}" target="_blank">Descargar PDF</a>
        @else
            <a class="btn" href="{{ route('pedidos.orden', $p->uid) }}" target="_blank">Orden de trabajo (PDF)</a>
            <a class="btn ghost" href="{{ route('pedidos.proforma', $p->uid) }}" target="_blank">Proforma (PDF)</a>
        @endif
        @if ($wa && $e !== 'entregado')<a class="btn ghost" href="{{ $wa }}" target="_blank" rel="noopener">{{ $cotz ? 'Enviar por WhatsApp' : ($e === 'listo' ? 'Avisar que está listo' : 'Enviar orden por WhatsApp') }}</a>@endif
        @if ($e !== 'entregado')<button type="button" class="btn ghost" wire:click="editar('{{ $p->uid }}')">Editar</button>@endif
        <button type="button" class="btn ghost" wire:click="duplicar('{{ $p->uid }}')">Duplicar</button>
        @if ($p->items->isNotEmpty())<button type="button" class="btn ghost" wire:click="pedirGuardarLista('{{ $p->uid }}')">Guardar como lista de útiles</button>@endif
    </div>

    @if ($cotz)
        <div class="card" style="margin-bottom:12px"><b>¿El cliente aceptó?</b><p class="cap" style="margin:4px 0 10px">Conviértela con un toque, sin volver a escribir nada.</p>
            <div class="actions tools"><button type="button" class="btn" wire:click="pedirAceptar('{{ $p->uid }}')">Aceptó</button><button type="button" class="btn ghost" wire:click="rechazar('{{ $p->uid }}')">No aceptó</button>@if ($e === 'vencido')<button type="button" class="btn ghost" wire:click="renovar('{{ $p->uid }}')">Renovar con fecha de hoy</button>@endif</div></div>
    @elseif ($e === 'rechazado')
        <div class="note bad">El cliente no aceptó esta cotización. <button type="button" class="link" wire:click="reabrir('{{ $p->uid }}')">Volver a cotización</button></div>
    @elseif ($p->activo())
        <div class="card" style="margin-bottom:12px"><div class="actions tools">
            @if ($e === 'proceso')<button type="button" class="btn ghost" wire:click="marcarListo('{{ $p->uid }}')">Marcar listo</button>@endif
            @if ($sal)<button type="button" class="btn ghost" wire:click="pedirPago('{{ $p->uid }}')">Registrar pago</button>@endif
            <button type="button" class="btn warn" wire:click="entregar('{{ $p->uid }}')">{{ $sal ? 'Entregar y cobrar '.Dinero::s($sal) : 'Entregar' }}</button></div>
            <div class="row" style="margin:10px 0 0"><label style="min-width:auto">Lo hace</label>
                <select class="field" style="width:auto;padding:8px" x-on:change="$wire.responsable('{{ $p->uid }}', $event.target.value)"><option value="">—</option>@foreach ($usuarios as $n)<option @selected($p->responsable === $n)>{{ $n }}</option>@endforeach</select></div></div>
    @endif

    <div class="kpis">
        <div class="card kpi"><div class="cap">Total</div><div class="v">{{ Dinero::s($p->total) }}</div></div>
        <div class="card kpi"><div class="cap">Pagado</div><div class="v">{{ Dinero::s($p->pagado()) }}</div></div>
        <div class="card kpi" @if ($sal && ! $cotz) style="background:var(--mag-bg);border-color:var(--magenta)" @endif><div class="cap">Saldo</div><div class="v">{{ Dinero::s($cotz ? $p->total : $sal) }}</div></div>
        <div class="card kpi"><div class="cap">{{ $cotz ? 'Válida hasta' : 'Entrega' }}</div><div class="v" style="font-size:1.2rem">{{ $cotz ? $p->vence()->format('d/m/Y') : ($e === 'entregado' ? ($p->entregado_at?->format('d/m/Y') ?? '—') : PT::entrega($p->fecha_entrega).($p->hora_entrega ? ' '.PT::hora($p->hora_entrega) : '')) }}</div></div>
    </div>
    <p class="cap">Cliente: <b>{{ $p->dato('nombre') }}</b>@if ($p->celular()) · {{ Texto::fmtCel($p->celular()) }}@endif @if ($doc) · {{ $doc }}@endif
        @if ($p->cliente_id && isset(\App\Support\Acceso::menu()['clientes'])) · <a class="link" style="padding:0" href="{{ route('clientes', ['c' => $p->clienteGuardado?->uid]) }}">Ver ficha</a>@endif</p>

    @if ($cotz || $e === 'rechazado')
        <div class="docwrap"><div class="docpage pf">
            <div class="pf-head"><div><div class="pf-biz">{{ mb_strtoupper($neg->nombre) }}</div><div class="pf-small">{{ $neg->giro }}<br>{{ $neg->titular }} · RUC {{ $neg->ruc }}<br>{{ $neg->direccion }}{{ $neg->ciudad ? ', '.$neg->ciudad : '' }} · Cel. {{ $neg->celular }}</div></div>
                <div class="pf-box"><div class="pf-t">PROFORMA</div><div>{{ $p->numeroTxt() }}</div><div class="pf-small">Fecha: {{ $p->fecha->format('d/m/Y') }}</div></div></div>
            <div class="pf-cli"><div><span>Cliente:</span> <b>{{ $p->dato('nombre') }}</b></div>@if ($p->dato('inst'))<div><span>Institución:</span> {{ $p->dato('inst') }}</div>@endif @if ($doc)<div><span>{{ strlen($doc) === 11 ? 'RUC' : 'DNI' }}:</span> {{ $doc }}</div>@endif @if ($p->celular())<div><span>Celular:</span> {{ Texto::fmtCel($p->celular()) }}</div>@endif</div>
            @if ($p->items->isNotEmpty())
                <table class="pf-tab"><thead><tr><th>Cant.</th><th>Descripción</th><th class="r">P. unit.</th><th class="r">Importe</th></tr></thead><tbody>
                    @foreach ($p->items as $l)<tr><td>{{ Venta::cant($l->cantidad) }}</td><td>{{ $l->nombre }}@if ($l->detalle) <span class="pf-small">({{ $l->detalle }})</span>@endif</td><td class="r">{{ Dinero::n($l->precio) }}</td><td class="r">{{ Dinero::n($l->subtotal) }}</td></tr>@endforeach
                </tbody></table>
            @else
                <div style="white-space:pre-wrap;margin:6px 0 10px">{{ $p->detalle }}</div>
            @endif
            <div class="pf-tots">@if ($p->descuento && $p->items->isNotEmpty())<div><span>Subtotal</span><span>{{ Dinero::s($p->subtotal()) }}</span></div><div><span>Descuento</span><span>− {{ Dinero::n($p->descuento) }}</span></div>@endif<div class="pf-total"><span>TOTAL</span><span>{{ Dinero::s($p->total) }}</span></div></div>
            <div class="pf-small" style="margin:6px 0 14px">Son: {{ Texto::montoLetras($p->total) }}</div>
            <div class="pf-cond"><b>Condiciones</b>
                <div>• Validez de la proforma: {{ $p->validez }} días (hasta el {{ $p->vence()->format('d/m/Y') }}).</div>
                @if ($p->cond_entrega)<div>• Tiempo de entrega: {{ $p->cond_entrega }}</div>@endif
                @if ($p->cond_pago)<div>• Forma de pago: {{ $p->cond_pago }}</div>@endif
                <div>• {{ PT::igv(app(\App\Services\Comprobantes::class)->config()['igv']) }}</div>
                @if ($p->notas)<div>• {{ $p->notas }}</div>@endif</div>
            <div class="pf-foot">@if ($p->vendedor)Atendido por: {{ $p->vendedor }} · @endif Gracias por su preferencia.</div>
        </div></div>
    @else
        <h2>Qué hay que hacer</h2>
        @if ($p->items->isNotEmpty())
            <div class="scroll"><table><thead><tr><th>Cant.</th><th>Descripción</th><th class="r">Importe</th></tr></thead><tbody>
                @foreach ($p->items as $l)<tr><td>{{ Venta::cant($l->cantidad) }}</td><td>{{ $l->nombre }}@if ($l->detalle) <span class="cap">({{ $l->detalle }})</span>@endif</td><td class="r">{{ Dinero::n($l->subtotal) }}</td></tr>@endforeach
            </tbody></table></div>
        @endif
        @if ($p->detalle)<div class="card" style="white-space:pre-wrap;margin-top:8px">{{ $p->detalle }}</div>@endif
    @endif

    @if ($p->pagos->isNotEmpty())
        <h2>Pagos</h2>
        @foreach ($p->pagos as $x)
            <div class="sale"><span class="t">{{ $x->pagado_at->format('d/m') }}<br>{{ $x->pagado_at->format('H:i') }}</span>
                <span class="d">{{ $x->tipo ?: 'Pago' }}@if ($x->metodo) <span class="tag m">{{ $neg->nombreMetodo($x->metodo) }}</span>@endif @if ($x->vendedor)<span class="tag s">{{ Texto::primerNombre($x->vendedor) }}</span>@endif @unless ($x->venta_uid)<span class="tag s">registro anterior</span>@endunless</span>
                <span class="amt">{{ Dinero::s($x->monto) }}</span></div>
        @endforeach
    @endif
    @if ($p->historial->isNotEmpty())
        <h2>Historial <small>{{ $p->historial->count() }}</small></h2>
        <div class="card"><div class="ledger">@foreach ($p->historial as $h)<div><span>{{ $h->que }}</span><span class="cap">{{ $h->ocurrido_at->format('d/m H:i') }}{{ $h->vendedor ? ' · '.$h->vendedor : '' }}</span></div>@endforeach</div></div>
    @endif
    <div style="text-align:center;margin-top:14px"><button type="button" class="link" wire:click="pedirEliminar('{{ $p->uid }}')">Eliminar pedido</button></div>

    @include('livewire.partials.pedido-ventanas')
</section>
