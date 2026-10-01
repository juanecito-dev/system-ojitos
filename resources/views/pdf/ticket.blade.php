@php
    $f = $ancho === 58 ? 0.8 : 1;
    $st = ['title' => ['Helvetica', 'bold', 15], 'bold' => ['Courier', 'bold', 9.5], 'big' => ['Courier', 'bold', 11.5], 'small' => ['Courier', 'normal', 7.2]];
    $m = $ancho === 58 ? 3 : 5;
@endphp
<!DOCTYPE html>
<html lang="es"><head><meta charset="utf-8"><title>{{ $titulo }}</title>
<style>
    @page { margin: {{ $m }}mm; }
    body { margin: 0; font-family: Courier, monospace; font-size: {{ 8.6 * $f }}pt; color: #111; }
    table { width: 100%; border-collapse: collapse; }
    td { padding: 0; vertical-align: top; line-height: 1.25; white-space: pre-wrap; }
    .c { text-align: center; }
    .r { text-align: right; white-space: nowrap; padding-left: 6px; }
    .hr { border-top: 1px dashed #888; height: 0; margin: 4px 0; }
    img { display: block; margin: 2px auto; }
</style></head>
<body>
@foreach ($filas as $x)
    @if (isset($x['img']))
        <img src="{{ $x['img'] }}" style="width:{{ round(($ancho - 2 * $m) * ($x['w'] ?? 0.5), 1) }}mm">
    @elseif (! empty($x['hr']))
        <div class="hr"></div>
    @else
        @php [$fam, $w, $pt] = $st[$x['st'] ?? ''] ?? ['Courier', 'normal', 8.6]; $css = "font-family:$fam;font-weight:$w;font-size:".($pt * $f).'pt'; @endphp
        @if (($x['a'] ?? '') === 'c')
            <div class="c" style="{{ $css }}">{{ $x['t'] }}</div>
        @else
            <table><tr><td style="{{ $css }}">{{ $x['t'] }}</td>@if (($x['r'] ?? '') !== '')<td class="r" style="{{ $css }}">{{ $x['r'] }}</td>@endif</tr></table>
        @endif
    @endif
@endforeach
</body></html>
