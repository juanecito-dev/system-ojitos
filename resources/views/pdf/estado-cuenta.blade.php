@php use App\Support\Dinero; use App\Support\Texto; @endphp
<table class="cli" style="width:100%">
    <tr><td class="k">Cliente:</td><td><b>{{ $c->nombre }}</b></td></tr>
    @if ($c->documento)<tr><td class="k">{{ strlen($c->documento) === 11 ? 'RUC' : 'DNI' }}:</td><td>{{ $c->documento }}</td></tr>@endif
    @if ($c->celular)<tr><td class="k">Celular:</td><td>{{ Texto::fmtCel($c->celular) }}</td></tr>@endif
    @if ($c->direccion)<tr><td class="k">Dirección:</td><td>{{ $c->direccion }}</td></tr>@endif
</table>
<p class="small" style="margin:0 0 3mm">{{ $e['desde'] ? 'Movimientos desde el '.$e['desde']->format('d/m/Y').', cuando empezó la deuda actual.' : ($e['saldo'] ? 'Todos los movimientos.' : 'El cliente está al día. Últimos movimientos:') }}</p>
<table class="tab">
    <thead><tr><th style="width:22mm">Fecha</th><th>Detalle</th><th class="r" style="width:22mm">Fiado</th><th class="r" style="width:22mm">Pagó</th><th class="r" style="width:24mm">Saldo</th></tr></thead>
    <tbody>
    @foreach ($e['filas'] as $f)
        <tr><td>{{ $f['m']->ocurrido_at->format('d/m/Y') }}</td>
            <td>{{ $f['m']->tipo === 'fiado' ? ($f['m']->detalle ?: 'Fiado') : 'Pago en '.mb_strtolower($neg->nombreMetodo($f['m']->metodo)) }}</td>
            <td class="r">{{ $f['m']->tipo === 'fiado' ? Dinero::n($f['m']->monto) : '' }}</td>
            <td class="r">{{ $f['m']->tipo !== 'fiado' ? Dinero::n($f['m']->monto) : '' }}</td>
            <td class="r"><b>{{ Dinero::n($f['saldo']) }}</b></td></tr>
    @endforeach
    </tbody>
</table>
<table class="tot"><tr class="grand"><td>{{ $e['saldo'] > 0 ? 'SALDO POR PAGAR' : ($e['saldo'] < 0 ? 'SALDO A FAVOR' : 'AL DÍA') }}</td><td class="r">{{ Dinero::s(abs($e['saldo'])) }}</td></tr></table>
@if ($e['saldo'] > 0 && ($yp = $neg->ajuste('yapeCel') ?: $neg->celular))
    <p style="margin-top:6mm;color:#3c3c3c">Puede pagar en efectivo o por Yape / Plin al {{ Texto::fmtCel($yp) }} ({{ $neg->ajuste('yapeNom') ?: $neg->titular }}).</p>
@endif
