{{-- Gráfico de barras (barras() del sistema anterior). $pts: [['t' => título, 'v' => céntimos, 'l' => etiqueta, 'hi' => resaltar]] --}}
@php
    $W = 720; $H = 220; $pl = 48; $pb = 26; $pt = 10; $n = count($pts);
    $max = max(100, ...array_map(fn ($d) => $d['v'], $pts ?: [['v' => 0]]));
    $step = 10 ** floor(log10($max / 100)) * 100; $nice = ceil($max / $step) * $step; $bw = ($W - $pl - 8) / max(1, $n);
@endphp
<svg viewBox="0 0 {{ $W }} {{ $H }}" role="img" aria-label="{{ $titulo ?? 'Ventas' }}" style="width:100%;height:auto;display:block">
    @foreach ([0, .5, 1] as $f)
        @php $y = $H - $pb - $f * ($H - $pb - $pt); @endphp
        <line x1="{{ $pl }}" x2="{{ $W - 4 }}" y1="{{ $y }}" y2="{{ $y }}" style="stroke:var(--line)"/>
        <text x="{{ $pl - 6 }}" y="{{ $y + 4 }}" text-anchor="end" style="fill:var(--soft);font-size:11px">{{ number_format($nice * $f / 100, 0, '.', ',') }}</text>
    @endforeach
    @foreach ($pts as $i => $d)
        @php $h = $d['v'] / $nice * ($H - $pb - $pt); $x = $pl + $i * $bw + $bw * 0.15; @endphp
        <rect x="{{ round($x, 1) }}" y="{{ round($H - $pb - $h, 1) }}" width="{{ round($bw * 0.7, 1) }}" height="{{ round(max(0, $h), 1) }}" rx="3" style="fill:{{ ! empty($d['hi']) ? 'var(--magenta)' : ($color ?? 'var(--cyan)') }}"><title>{{ $d['t'] }}: {{ \App\Support\Dinero::s($d['v']) }}</title></rect>
        @if ($n <= 12 || $i % (int) ceil($n / 10) === 0 || $i === $n - 1)
            <text x="{{ round($pl + $i * $bw + $bw / 2, 1) }}" y="{{ $H - 8 }}" text-anchor="middle" style="fill:var(--soft);font-size:11px">{{ $d['l'] }}</text>
        @endif
    @endforeach
</svg>
