{{-- Documento redactado en A4 (docPDF del sistema anterior): Times 11.5, márgenes 25/25/25/30 mm, firmas con huella --}}
@php
    $seg = fn ($segs) => collect($segs)->map(fn ($s) => ! empty($s[1]) ? '<b>'.e($s[0]).'</b>' : e($s[0]))->join('');
    $primerP = true;
@endphp
<!DOCTYPE html>
<html lang="es"><head><meta charset="utf-8"><title>{{ $titulo }}</title>
<style>
    @page { margin: 25mm 25mm 25mm 30mm; }
    body { font-family: "Times New Roman", Times, serif; font-size: 11.5pt; line-height: 1.45; color: #111; }
    h1 { font-size: 14pt; font-weight: bold; text-align: center; text-decoration: underline; margin: 0 5mm 9mm; line-height: 1.3; }
    p { margin: 0 0 3.2mm; text-align: justify; }
    p.ind { text-indent: 12.5mm; }
    p.left { text-align: left; margin-bottom: 1.2mm; }
    p.right { text-align: right; }
    p.center { text-align: center; }
    p.li { margin: 0 0 .8mm 12.5mm; }
    p.sum { text-align: left; margin: 0 0 8mm 50%; }
    .junto { page-break-inside: avoid; }
    table.firmas { width: 100%; border-collapse: collapse; margin-top: 6mm; }
    table.firmas td { padding: 0 0 8mm; }
    table.firmas td.f { width: 36%; vertical-align: top; padding-top: 20mm; }
    table.firmas td.h { width: 14%; vertical-align: top; }
    .linea { border-top: .5pt solid #141414; width: 52mm; margin: 0 auto; }
    .quien { text-align: center; font-size: 9.5pt; line-height: 1.35; padding-top: 1.2mm; }
    .huella { width: 18mm; height: 22mm; border: .5pt solid #969696; font-size: 7pt; color: #8c8c8c; text-align: center; }
    .huella div { padding-top: 18mm; }
</style></head>
<body>
@foreach ($bloques as $i => $b)
    {{-- el último párrafo va en la misma hoja que las firmas (keepH del sistema anterior) --}}
    @if ($b['k'] !== 'sign' && $b['k'] !== 'title' && ($bloques[$i + 1]['k'] ?? '') === 'sign')<div class="junto">@endif
    @if ($b['k'] === 'title')
        <h1>{{ $b['t'] }}</h1>
    @elseif ($b['k'] === 'sign')
        <table class="firmas">
            @foreach (array_chunk($b['f'], 2) as $fila)
                <tr>
                    @if (count($b['f']) === 1)<td style="width:25%"></td>@endif
                    @foreach ($fila as $f)
                        <td class="f"><div class="linea"></div><div class="quien"><b>{{ $f['n'] }}</b><br>DNI N° {{ $f['d'] }}<br>{{ $f['r'] }}</div></td>
                        <td class="h">@if (empty($f['nh']))<div class="huella"><div>Huella</div></div>@endif</td>
                    @endforeach
                    @if (count($b['f']) === 1)<td style="width:25%"></td>@elseif (count($fila) === 1)<td class="f"></td><td class="h"></td>@endif
                </tr>
            @endforeach
        </table>
        @if ($i > 0 && ! in_array($bloques[$i - 1]['k'], ['sign', 'title'], true))</div>@endif
    @else
        @php $ind = $b['k'] === 'p' && $primerP; if ($b['k'] === 'p') { $primerP = false; } @endphp
        <p class="{{ $b['k'] }}{{ $ind ? ' ind' : '' }}">{!! $seg($b['s']) !!}</p>
    @endif
@endforeach
</body></html>
