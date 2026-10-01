@php use App\Services\Stock; use App\Support\Dinero; $F = fn ($n) => Stock::formato($n); @endphp
<section x-data x-init="window.OJITOS_ESCANEO_AQUI = true" x-on:ojitos-escaneo.window="$wire.escanearCodigo($event.detail.codigo)" x-on:enfocar-conteo.window="$nextTick(() => { const i = document.querySelector('[data-tc=\'' + $event.detail.id + '\']'); if (i) { i.scrollIntoView({block: 'center'}); i.focus(); } })">
    <div class="daybar"><a class="btn ghost sm" href="{{ route('inventario') }}">‹ Inventario</a><b style="font-size:1.15rem">Toma de inventario</b><span class="cap" style="margin-left:auto">{{ $T->count() }} contados de {{ $total }}</span></div>
    <p class="cap" style="margin-top:0">Escribe lo que hay de cada producto o pasa el lector de códigos: cada lectura suma 1. Lo que no cuentes no cambia. Lo contado se guarda en el sistema: puedes seguir desde otro equipo.@if ($quien) Contaron: {{ $quien }}.@endif</p>
    <div class="daybar">
        <form wire:submit="escanear" style="flex:1;min-width:170px;display:flex"><input class="field" wire:model.live.debounce.300ms="buscar" placeholder="Buscar o escanear" autocomplete="off" style="flex:1;width:auto" autofocus></form>
        @if ($grupos->count() > 1)
            <select wire:model.live="grupo" style="padding:10px;border-radius:12px;border:1.5px solid var(--line);background:var(--sheet);color:var(--ink)"><option value="">Todos los grupos</option>@foreach ($grupos as $g)<option>{{ $g }}</option>@endforeach</select>
        @endif
    </div>
    <div class="stack">
        @forelse ($lista as $p)
            @php $has = isset($T[$p->id]); $d = $has ? $dif($p->id) : 0; @endphp
            <div class="inv" wire:key="t{{ $p->id }}" style="{{ $ultimo === $p->id ? 'border-color:var(--cyan);box-shadow:0 0 0 2px var(--cyan)' : '' }}">
                <span class="nm">{{ $p->nombre }}<small>{{ $p->grupo }} · el sistema dice {{ $F($st[$p->id]) }}
                    @if ($has && $d) · <b style="color:{{ $d < 0 ? 'var(--magenta)' : 'var(--ok)' }}">{{ $d < 0 ? 'faltan '.$F(-$d) : 'sobran '.$F($d) }}</b>@elseif ($has) · <b style="color:var(--ok)">cuadra</b>@endif</small></span>
                <input class="field" data-tc="{{ $p->id }}" inputmode="decimal" value="{{ $has ? $F($T[$p->id]->cantidad) : '' }}" placeholder="Contado" style="width:100px"
                       x-on:change="$wire.fijar({{ $p->id }}, $event.target.value)" x-on:keydown.enter.prevent="$el.blur()">
            </div>
        @empty
            <div class="empty">{{ $total ? 'Nada coincide.' : 'Aún no controlas stock de ningún producto.' }}</div>
        @endforelse
    </div>
    <div class="actions" style="position:sticky;bottom:0;background:var(--paper);padding:10px 0 calc(10px + env(safe-area-inset-bottom,0px));border-top:1px solid var(--line);align-items:center">
        <span class="cap" style="flex:1">{{ $T->count() ? $nDif.' con diferencia'.($perd ? ' · faltantes por '.Dinero::s($perd) : '') : 'Aún no cuentas nada' }}</span>
        <button type="button" class="btn ghost" wire:click="pedir('borrar')" @disabled(! $T->count())>Borrar lo contado</button>
        <button type="button" class="btn big" wire:click="pedir('aplicar')" @disabled(! $T->count())>Aplicar conteo</button>
    </div>

    @if ($confirmar)
        <div class="dlg" role="alertdialog" aria-modal="true"><div>
            <p>{{ $confirmar === 'borrar' ? '¿Borrar todo lo contado hasta ahora?' : 'Se actualiza el stock de '.$T->count().' productos'.($nDif ? ', '.$nDif.' con diferencia' : '').($perd ? '. Faltantes por '.Dinero::s($perd).' quedan como pérdida del mes' : '').'. ¿Aplicar?' }}</p>
            <div class="actions"><button type="button" class="btn ghost big" wire:click="cancelar">Cancelar</button>
                <button type="button" class="btn big {{ $confirmar === 'borrar' ? 'warn' : '' }}" wire:click="{{ $confirmar === 'borrar' ? 'borrar' : 'aplicar' }}">{{ $confirmar === 'borrar' ? 'Borrar' : 'Aplicar conteo' }}</button></div>
        </div></div>
    @endif
</section>
