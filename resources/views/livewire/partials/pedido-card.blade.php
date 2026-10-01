@php
    use App\Support\Dinero; use App\Support\Texto; use App\Support\Catalogos; use App\Services\PedidoTextos as PT;
    $e = $p->etapaVista(); [$tc, $tl] = Catalogos::ETAPAS_PEDIDO[$e] ?? Catalogos::ETAPAS_PEDIDO['proceso'];
    $sal = $p->saldo(); $pag = $p->pagado(); $dias = (int) $p->creado_at->diffInDays(now(), true);
    $cuando = match ($e) {
        'cotizado' => 'Cotizado el '.$p->fecha->format('d/m/Y').', válido hasta el '.$p->vence()->format('d/m/Y'),
        'vencido' => 'Cotización del '.$p->fecha->format('d/m/Y').', venció el '.$p->vence()->format('d/m/Y'),
        'entregado' => 'Entregado '.($p->entregado_at?->format('d/m/Y') ?? ''),
        'rechazado' => 'No aceptó',
        default => ($p->atrasado() ? 'Atrasado: era para ' : 'Entrega: ').PT::entrega($p->fecha_entrega).($p->hora_entrega ? ', '.PT::hora($p->hora_entrega) : ''),
    };
@endphp
<div class="enc {{ in_array($e, ['entregado', 'rechazado']) ? 'entregado' : ($p->atrasado() || $p->olvidado() ? 'late' : ($e === 'listo' ? 'listo' : '')) }}" wire:key="p{{ $p->id }}">
    <div class="top"><span class="who">{{ $p->dato('nombre') ?: 'Cliente' }}@if ($p->dato('inst')) <small class="cap">{{ $p->dato('inst') }}</small>@endif</span><span class="money">{{ Dinero::s($p->total) }}</span></div>
    <div class="det">{{ \Illuminate\Support\Str::limit($p->descripcion(), 180) }}</div>
    <div class="meta">{{ $p->numeroTxt() }} · {{ $cuando }}@if ($p->celular()) · cel. {{ Texto::fmtCel($p->celular()) }}@endif @if ($p->responsable) · lo hace {{ $p->responsable }}@endif</div>
    <div><span class="tag {{ $tc }}">{{ $tl }}</span>
        @if ($pag && $p->activo())<span class="tag m">A cuenta {{ Dinero::s($pag) }}</span>@endif
        @if ($p->activo()) @if ($sal)<span class="tag p">Saldo {{ Dinero::s($sal) }}</span>@else<span class="tag b">Pagado</span>@endif @endif
        @if ($p->atrasado())<span class="tag r">Atrasado</span>@endif
        @if ($p->olvidado())<span class="tag r">Sin recoger hace {{ (int) $p->listo_at->diffInDays(now(), true) }} días</span>@endif
        @if ($e === 'cotizado' && $dias >= 3)<span class="tag p">Sin respuesta hace {{ $dias }} días</span>@endif
    </div>
    <div class="actions tools">
        @if ($e === 'cotizado' || $e === 'vencido')
            <button type="button" class="btn sm" wire:click="pedirAceptar('{{ $p->uid }}')">Aceptó</button>
            <button type="button" class="btn ghost sm" wire:click="abrir('{{ $p->uid }}')">Ver cotización</button>
            @if ($e === 'cotizado' && $dias >= 3 && ($wa = PT::seguimiento($p, $neg)))<a class="btn ghost sm" href="{{ $wa }}" target="_blank" rel="noopener">Preguntar por WhatsApp</a>@endif
        @elseif ($e === 'proceso')
            <button type="button" class="btn ghost sm" wire:click="marcarListo('{{ $p->uid }}')">Marcar listo</button>
            @if ($sal)<button type="button" class="btn ghost sm" wire:click="pedirPago('{{ $p->uid }}')">Registrar pago</button>@endif
            <button type="button" class="btn sm" wire:click="entregar('{{ $p->uid }}')">{{ $sal ? 'Entregar y cobrar '.Dinero::s($sal) : 'Entregar' }}</button>
            <button type="button" class="link" wire:click="abrir('{{ $p->uid }}')">Ver</button>
        @elseif ($e === 'listo')
            @if ($wa = PT::listo($p, $neg))<a class="btn ghost sm" href="{{ $wa }}" target="_blank" rel="noopener">Avisar por WhatsApp</a>@endif
            <button type="button" class="btn sm" wire:click="entregar('{{ $p->uid }}')">{{ $sal ? 'Entregar y cobrar '.Dinero::s($sal) : 'Entregar' }}</button>
            <button type="button" class="link" wire:click="abrir('{{ $p->uid }}')">Ver</button>
        @else
            <button type="button" class="btn ghost sm" wire:click="abrir('{{ $p->uid }}')">Ver</button>
        @endif
    </div>
</div>
