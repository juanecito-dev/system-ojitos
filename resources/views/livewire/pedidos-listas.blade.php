@php use App\Support\Dinero; use App\Models\Venta; @endphp
<section>
    <div class="daybar"><button type="button" class="btn ghost sm" wire:click="$set('listas', false)">‹ Pedidos</button><b style="font-size:1.15rem">Listas de útiles</b></div>
    <p class="cap" style="margin-top:0">Guarda la lista de cada colegio y grado una sola vez. Cuando un padre la trae, eliges la lista y sale la cotización con los precios de hoy y el stock que tienes.</p>
    <div class="daybar"><button type="button" class="btn" wire:click="editarLista">Nueva lista</button><input class="field" wire:model.live.debounce.250ms="listasQ" placeholder="Buscar colegio o grado" style="flex:1;min-width:180px;width:auto"></div>
    @if ($plantillas->isNotEmpty())
        <div class="stack">
            @foreach ($plantillas as $x)
                <div class="card" wire:key="l{{ $x['t']->id }}"><div style="display:flex;justify-content:space-between;gap:10px;align-items:baseline"><b>{{ $x['t']->nombre }}</b><b>{{ Dinero::s($x['total']) }}</b></div>
                    <div class="cap">{{ $x['t']->institucion ?: 'Sin colegio' }} · {{ count($x['items']) }} útiles @if ($x['falta'])· <span style="color:var(--magenta)">falta stock de {{ $x['falta'] }}</span>@endif</div>
                    <div class="cap" style="margin:4px 0 8px">{{ collect($x['items'])->take(6)->map(fn ($l) => $l['cant'].' '.$l['nombre'])->join(', ') }}{{ count($x['items']) > 6 ? '…' : '' }}</div>
                    <div class="actions tools"><button type="button" class="btn sm" wire:click="cotizarLista('{{ $x['t']->uid }}')">Cotizar esta lista</button><button type="button" class="btn ghost sm" wire:click="editarLista('{{ $x['t']->uid }}')">Editar</button><button type="button" class="link" wire:click="eliminarLista('{{ $x['t']->uid }}')" wire:confirm="¿Eliminar la lista «{{ $x['t']->nombre }}»?">Eliminar</button></div></div>
            @endforeach
        </div>
    @else
        <div class="empty">Aún no tienes listas, o ninguna coincide. Crea la primera con «Nueva lista», o guarda una cotización como lista desde su vista.</div>
    @endif
</section>
