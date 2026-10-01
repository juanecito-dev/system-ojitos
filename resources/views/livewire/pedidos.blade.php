@php use App\Support\Dinero; $hoyTxt = today()->toDateString(); @endphp
<section>
    <div class="kpis">
        <div class="card kpi"><div class="cap">Por entregar</div><div class="v">{{ $act->count() }}</div>
            <div class="cap">{{ $act->filter->atrasado()->count() ? $act->filter->atrasado()->count().' atrasados' : $act->filter(fn ($p) => $p->fecha_entrega?->isToday())->count().' para hoy' }}</div></div>
        <div class="card kpi"><div class="cap">Por cobrar</div><div class="v">{{ Dinero::s($act->sum(fn ($p) => $p->saldo())) }}</div><div class="cap">saldos de los que están en proceso</div></div>
        <div class="card kpi"><div class="cap">Cotizaciones esperando</div><div class="v">{{ $cot->count() }}</div><div class="cap">{{ Dinero::s($cot->sum('total')) }} en juego</div></div>
        <div class="card kpi"><div class="cap">Aceptadas este mes</div><div class="v">{{ $acMes->count() }} de {{ $cotMes->count() }}</div>
            <div class="cap">{{ $cotMes->count() ? round($acMes->count() / $cotMes->count() * 100).'% de las cotizaciones' : 'aún sin cotizaciones' }}</div></div>
    </div>
    @if ($olvidados->isNotEmpty())
        <div class="note warn"><b>{{ $olvidados->count() }} {{ $olvidados->count() === 1 ? 'pedido listo no lo recogen' : 'pedidos listos no los recogen' }}</b> hace más de 7 días: {{ $olvidados->map(fn ($p) => $p->dato('nombre') ?: $p->numeroTxt())->join(', ') }}. Avísales por WhatsApp.</div>
    @endif
    <div class="daybar">
        <button type="button" class="btn big" wire:click="nuevo('proceso')">Nuevo encargo</button>
        <button type="button" class="btn ghost big" wire:click="nuevo('cotizado')">Nueva cotización</button>
        <button type="button" class="btn ghost" wire:click="verListas">Listas de útiles</button>
        <input class="field" wire:model.live.debounce.250ms="q" placeholder="Buscar: nombre, celular o número" style="flex:1;min-width:180px;width:auto">
    </div>
    <div class="seg">
        @foreach (['proceso' => 'Por entregar', 'entregas' => 'Entregas por día', 'cotizado' => 'Cotizaciones', 'entregado' => 'Entregados', 'todos' => 'Todos'] as $k => $l)
            <button type="button" wire:click="$set('filtro', '{{ $k }}')" aria-pressed="{{ $filtro === $k ? 'true' : 'false' }}">{{ $l }} @if ($k !== 'entregas')<small>{{ $cuenta[$k] }}</small>@endif</button>
        @endforeach
    </div>

    @if ($filtro === 'entregas')
        @forelse ($grupos as $g => $ps)
            <h2 style="{{ str_starts_with($g, '1') ? 'color:var(--magenta)' : '' }}">{{ substr($g, 2) }} <small>{{ $ps->count() }} · por cobrar {{ Dinero::s($ps->sum(fn ($p) => $p->saldo())) }}</small></h2>
            <div class="stack">@foreach ($ps as $p) @include('livewire.partials.pedido-card', ['p' => $p]) @endforeach</div>
        @empty
            <div class="empty">No hay trabajos por entregar.</div>
        @endforelse
    @elseif ($lista->isNotEmpty())
        <div class="stack">@foreach ($lista as $p) @include('livewire.partials.pedido-card', ['p' => $p]) @endforeach</div>
    @else
        <div class="empty">
            @if (trim($q) !== '') No encontré pedidos con «{{ $q }}».
            @elseif ($filtro === 'proceso') No hay trabajos por entregar. Registra planos, anillados o impresiones grandes que el cliente recoge después.
            @elseif ($filtro === 'cotizado') No hay cotizaciones esperando respuesta.
            @else Aún no hay pedidos aquí.
            @endif
        </div>
    @endif

    @include('livewire.partials.pedido-ventanas')
</section>
