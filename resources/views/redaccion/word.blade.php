{{-- El documento para abrir en Word (docWordHTML del sistema anterior): HTML que Word lee como .doc, A4 con Times 12 --}}
@php
    $seg = fn ($segs) => collect($segs)->map(fn ($s) => ! empty($s[1]) ? '<b>'.e($s[0]).'</b>' : e($s[0]))->join('');
    $P = fn ($al, $css, $html) => '<p class=MsoNormal align='.$al.' style="text-align:'.$al.';'.$css.'">'.$html.'</p>';
    $primerP = true;
    $css = ['right' => ['right', 'margin:0 0 10pt 0;line-height:150%'], 'center' => ['center', 'margin:0 0 10pt 0;line-height:150%'],
        'left' => ['left', 'margin:0 0 3pt 0;line-height:150%'], 'sum' => ['left', 'margin:0 0 18pt 8cm;line-height:150%'],
        'li' => ['justify', 'margin:0 0 2pt 1.25cm;line-height:150%']];
@endphp
<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:w="urn:schemas-microsoft-com:office:word" xmlns="http://www.w3.org/TR/REC-html40"><head><meta charset="utf-8"><title>{{ $titulo }}</title>
<!--[if gte mso 9]><xml><w:WordDocument><w:View>Print</w:View><w:Zoom>100</w:Zoom></w:WordDocument></xml><![endif]-->
<style>@page WordSection1{size:21.0cm 29.7cm;margin:2.5cm 2.5cm 2.5cm 3.0cm} div.WordSection1{page:WordSection1} p.MsoNormal{margin:0;font-family:"Times New Roman",serif;font-size:12pt} body{font-family:"Times New Roman",serif;font-size:12pt}</style></head><body lang=ES-PE><div class=WordSection1>
@foreach ($bloques as $b)
@if ($b['k'] === 'title')
{!! $P('center', 'margin:0 0 18pt 0;line-height:150%', '<b><u><span style="font-size:14pt">'.e($b['t']).'</span></u></b>') !!}
@elseif ($b['k'] === 'sign')
<table width="100%" border=0 cellspacing=0 cellpadding=0 style="margin-top:24pt">
@foreach (array_chunk($b['f'], 2) as $fila)
<tr>@if (count($b['f']) === 1)<td width="25%"></td>@endif
@foreach ($fila as $f)<td width="50%" valign=top style="padding:54pt 8pt 12pt 8pt">{!! $P('center', 'margin:0', '_______________________________') !!}{!! $P('center', 'margin:0', '<b>'.e($f['n']).'</b>') !!}{!! $P('center', 'margin:0', 'DNI N° '.e($f['d'])) !!}{!! $P('center', 'margin:0', e($f['r'])) !!}</td>@endforeach
</tr>
@endforeach
</table>
@elseif (isset($css[$b['k']]))
{!! $P($css[$b['k']][0], $css[$b['k']][1], $seg($b['s'])) !!}
@else
{!! $P('justify', ($primerP ? 'text-indent:1.25cm;' : '').'margin:0 0 10pt 0;line-height:150%', $seg($b['s'])) !!}
@php $primerP = false; @endphp
@endif
@endforeach
</div></body></html>
