@php
    use App\Services\Contadores; use App\Services\Reportes as Srv; use App\Support\Dinero;
    $S = fn ($c) => Dinero::s($c); $N = fn ($c) => Dinero::n($c);
    $dp = function ($x, $p) { $d = Srv::delta($x, $p); return $d === null ? '' : ($d > 500 ? '  (más de 5 veces)' : '  ('.($d > 0 ? '+' : '').$d.'%)'); };
    $fila = fn ($l, $r, $b = false) => '<tr'.($b ? ' style="font-weight:bold"' : '').'><td>'.e($l).'</td><td class="r">'.e($r).'</td></tr>';
@endphp
<style>
    h3 { font-size: 11.5pt; margin: 6mm 0 2mm; padding-bottom: 1.5mm; border-bottom: .5pt solid #c8c8c8; }
    table.kv { width: 100%; border-collapse: collapse; } table.kv td { padding: 2.2pt 0; font-size: 9.8pt; }
</style>
<p class="small" style="margin:0 0 2mm">Comparado con {{ $cmpTxt }} ({{ \Carbon\Carbon::parse($pa)->format('d/m/Y') }} al {{ \Carbon\Carbon::parse($pb)->format('d/m/Y') }}).</p>

<h3>Resultado</h3>
<table class="kv">
    {!! $fila('Vendido', $S($R['ventas']).$dp($R['ventas'], $P['ventas'])) !!}
    {!! $fila('Costo de lo vendido (conocido en el '.$R['cobertura'].'%)', '- '.$N($R['cogs'])) !!}
    {!! $fila('Gastos', '- '.$N($R['gastos'])) !!}
    @if ($R['perd']){!! $fila('Pérdidas de inventario', '- '.$N($R['perd'])) !!}@endif
    {!! $fila('GANANCIA', $S($R['ganancia']).$dp($R['ganancia'], $P['ganancia']), true) !!}
    {!! $fila('Ventas / ticket promedio', $R['n'].' / '.$S($R['n'] ? round($R['ventas'] / $R['n']) : 0)) !!}
</table>

<h3>Dinero que entró y salió</h3>
<table class="kv">
    {!! $fila('Cobrado en ventas', $S($R['ventas'] - $R['fiado'])) !!}
    {!! $fila('Cobrado de fiados', $S($R['cobFiado'])) !!}
    @if ($R['ingresos']){!! $fila('Otros ingresos', $S($R['ingresos'])) !!}@endif
    {!! $fila('Gastos', '- '.$N($R['gastos'])) !!}
    {!! $fila('Pagos a proveedores', '- '.$N($R['pagTot'])) !!}
    @if ($R['retiros']){!! $fila('Retiros', '- '.$N($R['retiros'])) !!}@endif
    {!! $fila('Saldo', $S($R['entra'] - $R['sale']), true) !!}
    {!! $fila('Vendido al fiado (por cobrar)', $S($R['fiado'])) !!}
    {!! $fila('Descuentos dados', $S($R['desc'])) !!}
    @if ($R['sinCobrar']){!! $fila('Copias sin cobrar según contadores', Contadores::numero($R['sinCobrar']).' ≈ '.$S($R['sinCobrarS'])) !!}@endif
</table>

@if ($grupos->isNotEmpty())
    <h3>Por grupo</h3>
    <table class="tab"><thead><tr><th>Grupo</th><th class="r">Vendido</th><th class="r">%</th><th class="r">Ganancia</th></tr></thead><tbody>
        @foreach ($grupos as $g => $x)<tr><td>{{ $g }}</td><td class="r">{{ $N($x['v']) }}</td><td class="r">{{ $R['bruto'] ? round($x['v'] / $R['bruto'] * 100) : 0 }}%</td><td class="r">{{ $x['cub'] ? $N($x['cub'] - $x['c']) : 'sin costo' }}</td></tr>@endforeach
    </tbody></table>
@endif

@if ($prods->isNotEmpty())
    <h3>Lo que más te deja</h3>
    <table class="tab"><thead><tr><th>Producto o servicio</th><th class="r">Cant.</th><th class="r">Vendido</th><th class="r">Ganancia</th></tr></thead><tbody>
        @foreach ($prods->take(12) as $x)<tr><td>{{ $x['n'] }}</td><td class="r">{{ Contadores::numero($x['cant']) }}</td><td class="r">{{ $N($x['v']) }}</td><td class="r">{{ $x['gan'] !== null ? $N($x['gan']) : 'sin costo' }}</td></tr>@endforeach
    </tbody></table>
@endif

@if ($vendRows->isNotEmpty())
    <h3>Por vendedor</h3>
    <table class="tab"><thead><tr><th>Vendedor</th><th class="r">Vendido</th><th class="r">Ventas</th><th class="r">Descuentos</th><th class="r">Anuladas</th><th class="r">Caja</th></tr></thead><tbody>
        @foreach ($vendRows as $n => $x)<tr><td>{{ $n }}</td><td class="r">{{ $N($x['v']) }}</td><td class="r">{{ $x['n'] }}</td><td class="r">{{ $N($x['desc']) }}</td><td class="r">{{ $x['anul'] ? $x['anul'].' ('.$N($x['anulM']).')' : '—' }}</td><td class="r">{{ $x['dif'] ? ($x['dif'] > 0 ? '+' : '-').$N(abs($x['dif'])) : 'cuadra' }}</td></tr>@endforeach
    </tbody></table>
@endif

@if ($R['gastos'])
    <h3>Gastos por concepto</h3>
    <table class="kv">@foreach (collect($R['gasC'])->sortDesc() as $c => $v){!! $fila($c, $S($v)) !!}@endforeach</table>
@endif

<h3>Cómo te pagan</h3>
<table class="kv">@foreach ($R['met'] as $m => $v)@if ($v){!! $fila($neg->nombreMetodo($m), $S($v)) !!}@endif @endforeach</table>
