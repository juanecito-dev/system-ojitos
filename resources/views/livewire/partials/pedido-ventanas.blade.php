@php use App\Support\Dinero; $metodosV = $neg->metodosActivos(); @endphp
@if ($aceptando && ($pa = \App\Models\Pedido::with(['items', 'pagos'])->where('uid', $aceptando)->first()))
    @php $mit = (int) (round($pa->total / 2 / 10) * 10); @endphp
    <div class="scrim on" wire:click="cerrarVentanas"></div>
    <div class="sheet on" role="dialog" aria-modal="true">
        <h3>{{ $pa->dato('nombre') ?: 'Cliente' }} aceptó la cotización</h3>
        <p class="hint">{{ $pa->numeroTxt() }} por {{ Dinero::s($pa->total) }}.@if ($pa->etapaVista() === 'vencido') <b>Esta cotización ya venció</b>: revisa que los precios sigan bien.@endif</p>
        <div class="card" style="margin-bottom:12px"><b>Pasar a encargo</b><p class="cap" style="margin:2px 0 10px">Se hace el trabajo y el cliente recoge después.</p>
            <div class="set two"><label>Fecha de entrega<input type="date" wire:model="ac.fecha"></label><label>Hora (opcional)<input type="time" wire:model="ac.hora"></label></div>
            <div class="row" style="margin:0 0 8px"><label>Adelanto</label><input data-solo="monto" class="field" wire:model.live.debounce.300ms="ac.adelanto" inputmode="decimal" placeholder="0.00">
                <div class="quick"><button type="button" wire:click="adelantoRapido(0)">Sin adelanto</button><button type="button" wire:click="adelantoRapido({{ $mit }})">50% ({{ Dinero::n($mit) }})</button><button type="button" wire:click="adelantoRapido({{ $pa->total }})">Todo</button></div></div>
            @if (Dinero::aCentimos($ac['adelanto']) > 0)
                <div class="chips">@foreach ($metodosV as $k => $n)<button type="button" class="chip" wire:click="$set('ac.metodo', '{{ $k }}')" aria-pressed="{{ $ac['metodo'] === $k ? 'true' : 'false' }}"><b style="font-size:.95rem">{{ $n }}</b></button>@endforeach</div>
            @endif
            <p class="err">{{ $error }}</p>
            <div class="actions"><button type="button" class="btn big" wire:click="aceptar">Pasar a encargo</button></div></div>
        <div class="card" style="margin-bottom:12px"><b>Se lo lleva ahora</b><p class="cap" style="margin:2px 0 10px">Cobras todo en la caja y queda entregado.</p>
            <div class="actions"><button type="button" class="btn warn big" wire:click="cobrarAhora('{{ $pa->uid }}')" @if ($pa->etapaVista() === 'vencido') wire:confirm="Esta cotización venció el {{ $pa->vence()->format('d/m/Y') }}. Se cobrará con los precios de ese día. ¿Continuar?" @endif>Cobrar ahora</button></div></div>
    </div>
@endif
@if ($pagando && ($pp = \App\Models\Pedido::with(['items', 'pagos'])->where('uid', $pagando)->first()))
    <div class="scrim on" wire:click="cerrarVentanas"></div>
    <div class="sheet on" role="dialog" aria-modal="true"><form wire:submit="pagar">
        <h3>Pago a cuenta</h3><p class="hint">{{ $pp->numeroTxt() }} · {{ $pp->dato('nombre') }}. Total {{ Dinero::s($pp->total) }}, pagado {{ Dinero::s($pp->pagado()) }}, saldo {{ Dinero::s($pp->saldo()) }}.</p>
        <div class="row"><label>Paga</label><input data-solo="monto" class="field" wire:model="pg.monto" inputmode="decimal" placeholder="0.00" x-init="$nextTick(() => $el.focus())"><div class="quick"><button type="button" wire:click="$set('pg.monto', '{{ Dinero::n($pp->saldo()) }}')">Todo el saldo ({{ Dinero::n($pp->saldo()) }})</button></div></div>
        <div class="chips">@foreach ($metodosV as $k => $n)<button type="button" class="chip" wire:click="$set('pg.metodo', '{{ $k }}')" aria-pressed="{{ $pg['metodo'] === $k ? 'true' : 'false' }}"><b style="font-size:.95rem">{{ $n }}</b></button>@endforeach</div>
        <p class="cap">Entra como venta de hoy. Si paga todo, el pedido sigue en proceso hasta que lo entregues.</p>
        <p class="err">{{ $error }}</p>
        <div class="actions"><button class="btn big" type="submit">Registrar pago</button></div>
    </form></div>
@endif
@if ($borrando && ($pb = \App\Models\Pedido::with('pagos')->where('uid', $borrando)->first()))
    <div class="dlg" role="alertdialog" aria-modal="true"><div>
        <p>¿Eliminar el pedido {{ $pb->numeroTxt() }} de {{ $pb->dato('nombre') }}?@if ($pb->pagado())

Tiene pagos por {{ Dinero::s($pb->pagado()) }}. Esas ventas no se borran: si devolviste el dinero, anúlalas en Ventas.@endif</p>
        <div class="actions"><button type="button" class="btn ghost big" wire:click="cerrarVentanas">Cancelar</button><button type="button" class="btn big warn" wire:click="eliminar">Eliminar</button></div>
    </div></div>
@endif
@if ($guardandoLista)
    <div class="dlg" role="dialog" aria-modal="true"><form wire:submit="guardarComoLista">
        <p>Nombre de la lista de útiles</p>
        <input class="field wide" wire:model="nombreLista" style="width:100%;margin-bottom:14px" placeholder="Ej.: 1.er grado 2027" x-init="$nextTick(() => $el.select())">
        <div class="actions"><button type="button" class="btn ghost big" wire:click="cerrarVentanas">Cancelar</button><button type="submit" class="btn big">Guardar</button></div>
    </form></div>
@endif
@include('livewire.partials.autorizacion')
