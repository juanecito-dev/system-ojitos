@php
    use App\Services\Contadores; use App\Services\Reportes as Srv; use App\Services\Stock; use App\Support\Catalogos; use App\Support\Dinero; use App\Support\Texto; use Carbon\Carbon;
    $S = fn ($c) => Dinero::s($c); $N = fn ($c) => Dinero::n($c);
    $pct = fn ($x, $t) => $t ? (int) round($x / $t * 100) : 0;
    $fd = fn ($k) => Carbon::parse($k)->format('d/m/Y');
    // «▲ 12% vs. antes» (inv: para gastos, bajar es bueno)
    $G = function ($cur, $prev, $inv = false) use ($comparar) {
        $d = Srv::delta($cur, $prev);
        if ($d === null) { return ''; }
        if ($d > 500) { return '<div class="cap" style="color:var(--ok);font-weight:700">▲ más de 5 veces lo de antes</div>'; }
        $bien = $inv ? $d <= 0 : $d >= 0;
        return '<div class="cap" style="color:'.($d === 0 ? 'var(--soft)' : ($bien ? 'var(--ok)' : 'var(--magenta)')).';font-weight:700">'.($d > 0 ? '▲' : ($d < 0 ? '▼' : '=')).' '.abs($d).'% vs. '.($comparar === 'anio' ? 'el año pasado' : 'antes').'</div>';
    };
    $DOW = ['Dom', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb'];
    $MES = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
    $cob = $R['cobertura']; $gan = $R['ganancia'];
@endphp
<section class="rep">
    <div class="seg">@foreach (Srv::RANGOS as $k => $l)<button type="button" wire:click="$set('rango', '{{ $k }}')" aria-pressed="{{ $rango === $k ? 'true' : 'false' }}">{{ $l }}</button>@endforeach</div>
    @if ($rango === 'custom')
        <div class="daybar"><label class="cap">Desde <input type="date" wire:model.live="desde" max="{{ today()->toDateString() }}"></label><label class="cap">Hasta <input type="date" wire:model.live="hasta" max="{{ today()->toDateString() }}"></label></div>
    @endif
    <div class="daybar" style="margin-top:4px">
        <span class="cap">{{ $fd($a) }} al {{ $fd($b) }} · comparado con
            <select wire:model.live="comparar" style="border:1px solid var(--line);background:var(--sheet);color:var(--ink);border-radius:8px;padding:4px 6px">
                <option value="anterior">{{ $largo ? 'el año pasado' : 'el periodo anterior' }}</option><option value="anio">las mismas fechas del año pasado</option>
            </select> ({{ $fd($pa) }} al {{ $fd($pb) }})</span>
        <span style="margin-left:auto"></span>
        @if ($costos)<a class="btn ghost sm" href="{{ route('reportes.pdf', ['desde' => $a, 'hasta' => $b, 'cmp' => $comparar, 'r' => $rango]) }}" target="_blank">Reporte en PDF</a>@endif
        @if ($R['n'])<a class="btn ghost sm" href="{{ route('reportes.ventas', ['desde' => $a, 'hasta' => $b]) }}">Ventas en Excel</a>@endif
    </div>

    <h2 style="margin-top:6px">Resultado</h2>
    <div class="kpis">
        <div class="card kpi"><div class="cap">Vendido</div><div class="v">{{ $S($R['ventas']) }}</div>{!! $G($R['ventas'], $P['ventas']) !!}</div>
        @if ($costos)<div class="card kpi"><div class="cap">Costo de lo vendido</div><div class="v">{{ $S($R['cogs']) }}</div><div class="cap">costo conocido en el {{ $cob }}% de lo vendido</div></div>@endif
        <div class="card kpi"><div class="cap">Gastos</div><div class="v">{{ $S($R['gastos']) }}</div>{!! $G($R['gastos'], $P['gastos'], true) !!}</div>
        @if ($costos)
            <div class="card kpi"><div class="cap">Pérdidas de inventario</div><div class="v">{{ $S($R['perd']) }}</div></div>
            <div class="card kpi" style="background:{{ $gan >= 0 ? 'var(--ok-bg)' : 'var(--mag-bg)' }};border-color:transparent"><div class="cap">Ganancia</div><div class="v">{{ $S($gan) }}</div>{!! $G($gan, $P['ganancia']) !!}</div>
        @endif
        <div class="card kpi"><div class="cap">Ventas</div><div class="v">{{ $R['n'] }}</div><div class="cap">ticket promedio {{ $S($R['n'] ? round($R['ventas'] / $R['n']) : 0) }}</div>{!! $G($R['n'] ? $R['ventas'] / $R['n'] : 0, $P['n'] ? $P['ventas'] / $P['n'] : 0) !!}</div>
    </div>
    @if ($costos)
        <p class="cap">Ganancia = vendido − costo de lo vendido − gastos − pérdidas. {{ $cob < 100 && $R['bruto'] ? 'Hay '.(100 - $cob).'% de ventas sin costo registrado (se cuentan como si no costaran): registra tus compras con detalle y los insumos de los servicios para que la cifra sea exacta.' : 'Todas las ventas tienen costo registrado.' }}</p>
    @endif
    @if ($proy)
        <div class="note info">A este ritmo ({{ $S($proy['porDia']) }} por día) cerrarás el mes en unos <b>{{ $S($proy['cierre']) }}</b>.@if ($proy['meta'] > 0) Cumpliste la meta diaria de {{ $S($proy['meta']) }} en {{ $proy['cumplidos'] }} de {{ $proy['hoyN'] }} días.@endif</div>
    @endif

    <div class="two" style="gap:12px">
        <div class="card"><b>Dinero que entró y salió</b><div class="ledger" style="margin-top:8px">
            <div><span>Cobrado en ventas</span><b>{{ $S($R['ventas'] - $R['fiado']) }}</b></div>
            <div><span>Cobrado de fiados</span><b>{{ $S($R['cobFiado']) }}</b></div>
            @if ($R['ingresos'])<div><span>Otros ingresos</span><b>{{ $S($R['ingresos']) }}</b></div>@endif
            <div><span>Gastos</span><b>− {{ $N($R['gastos']) }}</b></div>
            <div><span>Pagos a proveedores</span><b>− {{ $N($R['pagTot']) }}</b></div>
            @if ($R['retiros'])<div><span>Retiros (banco o uso personal)</span><b>− {{ $N($R['retiros']) }}</b></div>@endif
            <div class="tot"><span>Saldo</span><span>{{ $S($R['entra'] - $R['sale']) }}</span></div>
        </div></div>
        <div class="card"><b>Otros datos del periodo</b><div class="ledger" style="margin-top:8px">
            <div><span>Vendido al fiado (aún por cobrar)</span><b>{{ $S($R['fiado']) }}</b></div>
            <div><span>Descuentos dados</span><b>{{ $S($R['desc']) }}</b></div>
            <div><span>Tasas cobradas para terceros</span><b>{{ $S($R['tasas']) }}</b></div>
            <div><span>Ventas anuladas</span><b>{{ count($R['anul']) ? count($R['anul']).' por '.$S(collect($R['anul'])->sum('total')) : 'Ninguna' }}</b></div>
            @if ($R['sinCobrar'])<div><span>Copias sin cobrar (contadores)</span><b style="color:var(--magenta)">{{ Contadores::numero($R['sinCobrar']) }} ≈ {{ $S($R['sinCobrarS']) }}</b></div>@endif
            <div><span>Promedio por día trabajado</span><b>{{ $S($R['dias'] ? round($R['ventas'] / $R['dias']) : 0) }}</b></div>
        </div></div>
    </div>

    @if ($largo)
        <h2>Mes a mes</h2>
        <div class="card chart">@include('livewire.partials.barras', ['pts' => collect($R['porMes'])->map(fn ($x, $m) => ['t' => ucfirst($MES[(int) substr($m, 5, 2) - 1]).' '.substr($m, 0, 4), 'v' => $x['ventas'], 'l' => ucfirst(mb_substr($MES[(int) substr($m, 5, 2) - 1], 0, 3))])->values()->all()])</div>
        <div class="scroll"><table><thead><tr><th>Mes</th><th class="r">Vendido</th>@if ($costos)<th class="r">Costo</th>@endif<th class="r">Gastos</th>@if ($costos)<th class="r">Ganancia*</th>@endif<th class="r">Ventas</th></tr></thead><tbody>
            @foreach ($R['porMes'] as $m => $x)
                <tr><td>{{ ucfirst($MES[(int) substr($m, 5, 2) - 1]) }} {{ substr($m, 0, 4) }}</td><td class="r">{{ $S($x['ventas']) }}</td>@if ($costos)<td class="r">{{ $S($x['cogs']) }}</td>@endif<td class="r">{{ $S($x['gastos']) }}</td>
                    @if ($costos)<td class="r"><b>{{ $S($x['ventas'] - $x['cogs'] - $x['gastos']) }}</b></td>@endif<td class="r">{{ $x['n'] }}</td></tr>
            @endforeach
        </tbody></table></div>
        @if ($costos)<p class="cap">* Sin contar pérdidas de inventario, que se restan en el total del periodo.</p>@endif
    @else
        <div class="card chart" style="margin-top:12px"><div class="cap" style="margin-bottom:6px">Ventas por día (S/)</div>
            @include('livewire.partials.barras', ['pts' => array_map(fn ($d) => ['t' => $fd($d['k']), 'v' => $d['v'], 'hi' => $d['hoy'], 'l' => count($diasGrafico) <= 10 ? $DOW[Carbon::parse($d['k'])->dayOfWeek].' '.Carbon::parse($d['k'])->day : (string) Carbon::parse($d['k'])->day], $diasGrafico)])</div>
    @endif

    @if ($grupos->isNotEmpty())
        @php $gTot = $grupos->sum('v'); $gMax = $grupos->first()['v'] ?: 1; @endphp
        <h2>Por grupo</h2>
        <div class="scroll"><table><thead><tr><th>Grupo</th><th class="r">Vendido</th><th class="r">%</th>@if ($costos)<th class="r">Ganancia</th>@endif</tr></thead><tbody>
            @foreach ($grupos as $g => $x)
                <tr><td class="bar-cell"><i style="width:{{ max(4, $x['v'] / $gMax * 100) }}%"></i><span>{{ $g }}</span></td><td class="r">{{ $S($x['v']) }}</td><td class="r">{{ $pct($x['v'], $gTot) }}%</td>
                    @if ($costos)<td class="r">@if ($x['cub']){{ $S($x['cub'] - $x['c']) }}@if ($x['cub'] < $x['v']) <small class="cap">(de {{ $S($x['cub']) }})</small>@endif @else<span class="cap">sin costo</span>@endif</td>@endif</tr>
            @endforeach
        </tbody></table></div>
    @endif

    @if ($prods->isNotEmpty())
        <h2>Lo que más te deja @if ($costos)<small><button type="button" class="link" style="padding:0" wire:click="$set('orden', '{{ $orden === 'g' ? 'v' : 'g' }}')">{{ $orden === 'g' ? 'ordenado por ganancia · ver por ventas' : 'ordenado por ventas · ver por ganancia' }}</button></small>@endif</h2>
        <div class="scroll"><table><thead><tr><th>Producto o servicio</th><th class="r">Cant.</th><th class="r">Vendido</th>@if ($costos)<th class="r">Ganancia</th><th class="r">Margen</th>@endif</tr></thead><tbody>
            @foreach ($prods as $x)
                <tr><td>{{ $x['n'] }}</td><td class="r">{{ Contadores::numero($x['cant']) }}</td><td class="r">{{ $S($x['v']) }}</td>
                    @if ($costos)<td class="r">@if ($x['gan'] !== null){{ $S($x['gan']) }}@else<span class="cap">sin costo</span>@endif</td><td class="r">{{ $x['gan'] !== null && $x['cub'] ? round(($x['cub'] - $x['c']) / $x['cub'] * 100).'%' : '' }}</td>@endif</tr>
            @endforeach
        </tbody></table></div>
    @endif

    @if ($R['heat'])
        @php
            $horas = array_map(fn ($k) => (int) explode('-', $k)[1], array_keys($R['heat'])); $hMin = min($horas); $hMax = max($horas); $hTop = max($R['heat']);
            [$tw, $th] = array_map('intval', explode('-', array_search($hTop, $R['heat'])));
        @endphp
        <h2>Cuándo vendes más</h2>
        <div class="scroll"><table style="border:0;background:none"><thead><tr><th></th>@for ($h = $hMin; $h <= $hMax; $h++)<th style="text-align:center;padding:4px;font-size:.75rem">{{ $h }}</th>@endfor</tr></thead><tbody>
            @foreach ([1, 2, 3, 4, 5, 6, 0] as $w)
                <tr><td style="padding:4px 8px;font-size:.8rem;color:var(--soft);border:0">{{ $DOW[$w] }}</td>
                    @for ($h = $hMin; $h <= $hMax; $h++)
                        @php $v = $R['heat'][$w.'-'.$h] ?? 0; @endphp
                        <td title="{{ $DOW[$w] }} {{ $h }}:00 · {{ $S($v) }}" style="padding:0;border:2px solid var(--paper)"><div style="height:26px;min-width:22px;border-radius:4px;background:{{ $v ? 'color-mix(in srgb, var(--cyan) '.round(15 + $v / $hTop * 85).'%, transparent)' : 'var(--sheet)' }}"></div></td>
                    @endfor
                </tr>
            @endforeach
        </tbody></table></div>
        <p class="cap">Más oscuro = más ventas. Tu mejor momento: los {{ ['domingos', 'lunes', 'martes', 'miércoles', 'jueves', 'viernes', 'sábados'][$tw] }} de {{ $th }}:00 a {{ $th + 1 }}:00.</p>
    @endif

    @if ($vendRows->isNotEmpty() && ! ($vendRows->count() === 1 && $vendRows->keys()->first() === 'Sin usuario'))
        <h2>Por vendedor</h2>
        <div class="scroll"><table><thead><tr><th>Vendedor</th><th class="r">Vendido</th><th class="r">Ventas</th><th class="r">Ticket</th><th class="r">Descuentos</th><th class="r">Anuladas</th><th class="r">Caja</th></tr></thead><tbody>
            @foreach ($vendRows as $n => $x)
                <tr><td>{{ $n }}</td><td class="r">{{ $S($x['v']) }}</td><td class="r">{{ $x['n'] }}</td><td class="r">{{ $S($x['n'] ? round($x['v'] / $x['n']) : 0) }}</td><td class="r">{{ $x['desc'] ? $S($x['desc']) : '—' }}</td>
                    <td class="r" style="{{ $x['anul'] ? 'color:var(--magenta);font-weight:700' : '' }}">{{ $x['anul'] ? $x['anul'].' ('.$S($x['anulM']).')' : '—' }}</td>
                    <td class="r" style="{{ $x['dif'] < 0 ? 'color:var(--magenta);font-weight:700' : '' }}">{{ $x['dif'] ? ($x['dif'] > 0 ? 'sobró ' : 'faltó ').$S(abs($x['dif'])) : 'cuadra' }}</td></tr>
            @endforeach
        </tbody></table></div>
        <p class="cap">Anuladas: ventas que se anularon o deshicieron y que había registrado ese vendedor. Caja: suma de lo que sobró o faltó al cerrar sus turnos.</p>
    @endif

    @if ($R['anul'])
        <h2>Ventas anuladas <small>{{ count($R['anul']) }}</small></h2>
        @foreach (array_slice($R['anul'], 0, 30) as $x)
            <div class="sale"><span class="t">{{ $x->anulada_at->format('d/m') }}<br>{{ $x->anulada_at->format('H:i') }}</span>
                <span class="d">{{ $x->detalle }}<br><span class="tag r">{{ $x->tipo === 'deshecha' ? 'Deshecha' : 'Anulada' }} por {{ Texto::primerNombre($x->por) ?: '?' }}</span>@if ($x->vendedor_original && $x->vendedor_original !== $x->por)<span class="tag s">la vendió {{ Texto::primerNombre($x->vendedor_original) }}</span>@endif @if ($x->motivo)<span class="tag s">{{ $x->motivo }}</span>@endif</span>
                <span class="amt">{{ $S($x->total) }}</span></div>
        @endforeach
    @endif

    @if ($R['docs'])
        <h2>Documentos redactados</h2>
        <div class="scroll"><table><thead><tr><th>Tipo</th><th class="r">Cantidad</th><th class="r">Vendido</th></tr></thead><tbody>
            @foreach (collect($R['docs'])->sortByDesc('v') as $n => $x)<tr><td>{{ $n }}</td><td class="r">{{ $x['n'] }}</td><td class="r">{{ $S($x['v']) }}</td></tr>@endforeach
        </tbody></table></div>
    @endif

    <h2>Clientes y pedidos</h2>
    <div class="kpis">
        <div class="card kpi"><div class="cap">Ventas con cliente registrado</div><div class="v">{{ $pct($R['conCliente'], $R['ventas']) }}%</div><div class="cap">{{ $S($R['conCliente']) }}</div></div>
        <div class="card kpi"><div class="cap">Clientes nuevos</div><div class="v">{{ $cp['nuevos'] }}</div></div>
        @if ($cp['cot'] || $cp['ent'])
            <div class="card kpi"><div class="cap">Cotizaciones aceptadas</div><div class="v">{{ $cp['acc'] }} de {{ $cp['cot'] }}</div><div class="cap">{{ $cp['cot'] ? $pct($cp['acc'], $cp['cot']).'%' : '' }}</div></div>
            <div class="card kpi"><div class="cap">Pedidos entregados</div><div class="v">{{ $cp['ent'] }}</div><div class="cap">{{ $cp['ent'] ? 'en '.$cp['diasEntrega'].' días en promedio' : '' }}</div></div>
        @endif
    </div>
    @if ($cp['top']->isNotEmpty())
        <div class="scroll"><table><thead><tr><th>Mejores clientes</th><th class="r">Compras</th><th class="r">Total</th></tr></thead><tbody>
            @foreach ($cp['top'] as $x)<tr><td>{{ $x['nombre'] }}</td><td class="r">{{ $x['n'] }}</td><td class="r">{{ $S($x['v']) }}</td></tr>@endforeach
        </tbody></table></div>
    @endif

    <div class="two" style="gap:12px;margin-top:14px">
        @if ($R['gastos'])
            <div><h2 style="margin-top:0">En qué gastas</h2><div class="card methods">
                @foreach (collect($R['gasC'])->sortDesc() as $c => $v)<div><span>{{ $c }}</span><b>{{ $S($v) }} <small class="cap">{{ $pct($v, $R['gastos']) }}%</small></b></div>@endforeach
            </div></div>
        @endif
        <div><h2 style="margin-top:0">Cómo te pagan</h2><div class="card methods">
            @foreach (array_keys(Catalogos::METODOS + ['fiado' => 'Fiado']) as $m)
                @if (($R['met'][$m] ?? 0) || $m === 'efectivo' || ($m !== 'fiado' && array_key_exists($m, $neg->metodosActivos())))
                    <div><span>{{ $neg->nombreMetodo($m) }}</span><b>{{ $S($R['met'][$m] ?? 0) }} <small class="cap">{{ $pct($R['met'][$m] ?? 0, $R['ventas']) }}%</small></b></div>
                @endif
            @endforeach
        </div></div>
    </div>
</section>
