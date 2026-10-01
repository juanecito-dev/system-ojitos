@php use App\Support\Dinero; use App\Models\Venta; use App\Services\Contadores; @endphp
<section>
    <div class="hello">{{ $saludo }}{{ $nombre ? ', '.$nombre : '' }}</div>
    <div class="cap">{{ $fechaTxt }}@if (! $cajaAbierta && isset($modulos['caja'])). Tu caja aún no está abierta.@endif</div>

    @if ($meta > 0)
        <div class="card" style="margin-top:12px">
            <div style="display:flex;justify-content:space-between;gap:10px"><b>Meta del día</b><span class="cap">{{ Dinero::s($r['total']) }} de {{ Dinero::s($meta) }}</span></div>
            <div style="height:12px;background:var(--paper);border-radius:6px;margin-top:8px;overflow:hidden"><div style="height:100%;width:{{ min(100, $r['total'] / $meta * 100) }}%;background:{{ $r['total'] >= $meta ? 'var(--ok)' : 'var(--cyan)' }};border-radius:6px"></div></div>
            <div class="cap" style="margin-top:6px">{{ $r['total'] >= $meta ? '¡Meta cumplida!' : 'Te faltan '.Dinero::s($meta - $r['total']) }}</div>
        </div>
    @endif

    <div class="dash">
        <a class="dcard" href="{{ isset($modulos['ventas']) ? route('ventas') : '#' }}" style="text-decoration:none;color:inherit"><span class="cap">Vendido hoy</span><span class="v">{{ Dinero::s($r['total']) }}</span><span class="cap">{{ $r['n'] }} {{ $r['n'] === 1 ? 'venta' : 'ventas' }}</span></a>
        <a class="dcard" href="{{ isset($modulos['caja']) ? route('caja') : '#' }}" style="text-decoration:none;color:inherit"><span class="cap">Efectivo en caja</span><span class="v">{{ Dinero::s($r['esperado']) }}</span><span class="cap">{{ $r['gastos'] ? 'Gastos del día '.Dinero::s($r['gastos']) : 'Sin gastos registrados' }}</span></a>
        <div class="dcard {{ $porEmitir ? 'alert' : 'good' }}"><span class="cap">Comprobantes por emitir</span><span class="v">{{ $porEmitir }}</span>
            <span class="cap">{{ $porEmitir ? collect([$cierrePend ? 'Cierre '.Dinero::s($cierrePend) : '', $r['pendientes']->count() ? $r['pendientes']->count().($r['pendientes']->count() === 1 ? ' venta grande' : ' ventas grandes').' hoy' : ''])->filter()->join(' y ') ?: 'De días anteriores' : 'Todo al día' }}</span></div>
        @isset($modulos['encargos'])
            <a class="dcard {{ $pedUrg ? 'alert' : '' }}" href="{{ route('pedidos') }}" style="text-decoration:none;color:inherit"><span class="cap">Pedidos por entregar</span><span class="v">{{ $pedAct }}</span>
                <span class="cap">{{ $pedUrg ? $pedUrg.' para hoy o atrasados' : 'Nada vence hoy' }}{{ $cotEsperan ? ' · '.$cotEsperan.($cotEsperan === 1 ? ' cotización espera' : ' cotizaciones esperan') : '' }}</span></a>
        @endisset
        <a class="dcard" href="{{ isset($modulos['clientes']) ? route('clientes') : '#' }}" style="text-decoration:none;color:inherit"><span class="cap">Te deben (fiados)</span><span class="v">{{ Dinero::s($deben) }}</span><span class="cap">{{ $nDeben }} {{ $nDeben === 1 ? 'cliente' : 'clientes' }}</span></a>
        @if ($vencen->isNotEmpty())
            @php $vencidas = $vencen->filter->vencida(); @endphp
            <a class="dcard alert" href="{{ route('compras') }}" style="text-decoration:none;color:inherit"><span class="cap">Facturas de proveedores</span><span class="v">{{ Dinero::s($vencen->sum(fn ($c) => $c->saldo())) }}</span>
                <span class="cap">{{ $vencidas->count() ? $vencidas->count().' '.($vencidas->count() === 1 ? 'vencida' : 'vencidas') : '' }}{{ $vencidas->count() && $vencen->count() > $vencidas->count() ? ' y ' : '' }}{{ $vencen->count() > $vencidas->count() ? ($vencen->count() - $vencidas->count()).' '.($vencen->count() - $vencidas->count() === 1 ? 'vence' : 'vencen').' en 3 días' : '' }}: {{ $vencen->take(2)->map(fn ($c) => $c->proveedor?->nombre)->filter()->unique()->join(', ') }}</span></a>
        @endif
        @if ($sinCobrar && $sinCobrar['copias'] > 0)
            <a class="dcard alert" href="{{ isset($modulos['caja']) ? route('caja') : '#' }}" style="text-decoration:none;color:inherit"><span class="cap">Copias sin cobrar (7 días)</span><span class="v">{{ Contadores::numero($sinCobrar['copias']) }}</span>
                <span class="cap">≈ {{ Dinero::s($sinCobrar['monto']) }} en {{ $sinCobrar['dias'] }} {{ $sinCobrar['dias'] === 1 ? 'día' : 'días' }}, según los contadores</span></a>
        @endif
        <a class="dcard {{ $bajo->count() ? 'alert' : '' }}" href="{{ isset($modulos['inventario']) ? route('inventario', $bajo->count() ? ['f' => 'reponer'] : []) : '#' }}" style="text-decoration:none;color:inherit"><span class="cap">Por reponer</span><span class="v">{{ $bajo->count() }}</span><span class="cap">{{ $bajo->count() ? $bajo->take(3)->pluck('nombre')->join(', ').($bajo->count() > 3 ? '…' : '') : 'Stock en orden' }}</span></a>
    </div>

    <h2>Módulos</h2>
    <div class="mods">
        @foreach ($modulos as $k => $m)
            <a class="mod" href="{{ route($m['ruta']) }}" style="text-decoration:none;color:inherit"><span class="ic" style="color:var(--{{ ['vender' => 'magenta', 'caja' => 'ok', 'ventas' => 'cyan', 'usuarios' => 'ink', 'ajustes' => 'soft', 'clientes' => 'yellow', 'encargos' => 'yellow', 'comprobantes' => 'magenta'][$k] ?? 'ink' }})"><x-icono :name="$k" /></span><span><b>{{ $m['titulo'] }}</b><small>{{ $m['desc'] }}</small></span></a>
        @endforeach
    </div>
    @if ($faltan->isNotEmpty())
        <p class="cap" style="margin-top:10px">En esta versión todavía no están: {{ $faltan->join(', ', ' y ') }}. Llegan en las próximas etapas; mientras tanto se siguen usando en el sistema actual.</p>
    @endif

    @if ($ultimas->isNotEmpty())
        <h2>Últimas ventas</h2>
        @foreach ($ultimas as $v)
            <div class="sale"><span class="t">{{ $v->vendida_at->format('H:i') }}</span><span class="d">{{ $v->items->map(fn ($l) => Venta::cant($l->cantidad).' '.$l->nombre)->join(', ') }}</span><span class="amt">{{ Dinero::s($v->total) }}</span></div>
        @endforeach
    @endif
</section>
