{{-- El currículum para Word (cvWordHTML y cvmWord del sistema anterior). En Word no va la foto. --}}
@php
    use App\Redaccion\Curriculum as CV;
    $seg = fn ($segs) => collect($segs)->map(function ($s) {
        $h = e($s[0]);
        if (! empty($s[2])) { $h = '<i>'.$h.'</i>'; }
        return ! empty($s[1]) ? '<b>'.$h.'</b>' : $h;
    })->join('');
    $P = fn ($al, $css, $html) => '<p class=MsoNormal align='.$al.' style="text-align:'.$al.';'.$css.'">'.$html.'</p>';
    $mod = $diseno === 'cv_moderno';
    $H = $diseno === 'cv_harvard'; $M = $diseno === 'cv_medio';
    if ($mod) { $D = CV::moderno($d); $K = $D['col']; $FAM = $D['tema']['fuente'] === 'serif' ? '"Times New Roman",serif' : 'Arial,Helvetica,sans-serif'; }
    else { $FAM = $H ? '"Times New Roman",serif' : 'Calibri,Arial,sans-serif'; $ACC = $M ? '#1F4E79' : '#111111'; }
@endphp
<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:w="urn:schemas-microsoft-com:office:word" xmlns="http://www.w3.org/TR/REC-html40"><head><meta charset="utf-8"><title>Currículum vitae</title>
<!--[if gte mso 9]><xml><w:WordDocument><w:View>Print</w:View><w:Zoom>100</w:Zoom></w:WordDocument></xml><![endif]-->
<style>@page WordSection1{size:21.0cm 29.7cm;margin:{{ $mod ? '1.0cm 1.0cm 1.0cm 1.0cm' : '1.6cm 1.8cm 1.6cm 1.8cm' }}} div.WordSection1{page:WordSection1} p.MsoNormal{margin:0;font-family:{!! $FAM !!};font-size:{{ $H ? 11 : 10 }}pt;color:#1a1a1a} td{font-family:{!! $FAM !!};font-size:{{ $H ? 11 : 10 }}pt;padding:0} table{border-collapse:collapse;mso-table-lspace:0;mso-table-rspace:0}</style></head><body lang=ES-PE><div class=WordSection1>
@if (! $mod)
@foreach (CV::bloques($d, $diseno) as $b)
@switch ($b['k'])
@case('cvname'){!! $P($M ? 'left' : 'center', 'margin:0;font-size:'.($H ? 20 : 19).'pt;color:'.$ACC, '<b>'.e($b['t']).'</b>') !!}@if ($b['sub'] !== ''){!! $P($M ? 'left' : 'center', 'margin:0;font-size:11.5pt;color:#444', e($b['sub'])) !!}@endif
@break
@case('cvcontact'){!! $P($M ? 'left' : 'center', 'margin:2pt 0 10pt 0;font-size:9.5pt;padding-bottom:4pt;border-bottom:solid '.$ACC.' 1.0pt', e($b['t'])) !!}
@break
@case('cvh'){!! $P('left', 'margin:10pt 0 4pt 0;font-size:10.5pt;color:'.$ACC.';border-bottom:solid '.$ACC.' .75pt;padding-bottom:1pt', '<b>'.e(mb_strtoupper($b['t'])).'</b>') !!}
@break
@case('cvrow')
@case('cvsub')@if (collect([...$b['l'], ...$b['r']])->contains(fn ($s) => trim($s[0]) !== ''))<table width="100%" border=0 cellspacing=0 cellpadding=0 style="margin:{{ $b['k'] === 'cvrow' ? '4pt' : '0' }} 0 {{ $b['k'] === 'cvsub' ? '3pt' : '0' }} 0"><tr><td valign=top style="padding:0">{!! $P('left', 'margin:0', $seg($b['l'])) !!}</td><td valign=top align=right style="white-space:nowrap;padding:0">{!! $P('right', 'margin:0', collect($b['r'])->contains(fn ($s) => trim($s[0]) !== '') ? $seg($b['r']) : '') !!}</td></tr></table>@endif
@break
@case('bul'){!! $P('justify', 'margin:0 0 1pt 14pt;text-indent:-10pt', '•&nbsp;&nbsp;'.$seg($b['s'])) !!}
@break
@case('cvsum'){!! $P('justify', 'margin:0 0 8pt 0;line-height:120%', $seg($b['s'])) !!}
@break
@case('cvp'){!! $P('left', 'margin:0 0 1pt 0', $seg($b['s'])) !!}
@break
@endswitch
@endforeach
@else
@php
    $C = $K['c'];
    $p = fn ($css, $html) => '<p class=MsoNormal style="'.$css.'">'.$html.'</p>';
    $h = fn ($t) => $p('margin:14pt 0 6pt 0;font-size:12.5pt;color:'.$C.';letter-spacing:1.5pt;border-bottom:solid '.$C.' 1.5pt;padding-bottom:2pt', '<b>'.e(mb_strtoupper($t)).'</b>');
    $B = fn ($html) => $p('margin:0 0 3pt 12pt;text-indent:-10pt', '•&nbsp;&nbsp;'.$html);
    $I = fn ($html) => $p('margin:0 0 2pt 12pt', $html);
    $rich = fn ($t) => $seg(CV::tema($t));
    $fechas = fn ($x) => CV::fechas($x['desde'] ?? '', $x['hasta'] ?? '');
    $side = ''; $main = '';
    if ($D['contacto']) { $side .= $h('Contacto').collect($D['contacto'])->map(fn ($v, $k) => $p('margin:0 0 4pt 0', '<b style="color:'.$C.'">'.['tel' => 'Tel.', 'mail' => 'Correo', 'web' => 'Web', 'pin' => 'Dirección'][$k].':</b> '.e($v)))->join(''); }
    if ($D['personales']) { $side .= $h('Datos personales').collect($D['personales'])->map(fn ($v, $k) => $p('margin:0 0 3pt 0', '<b>'.e($k).':</b> '.e($v)))->join(''); }
    if ($D['hab']) { $side .= $h('Habilidades').collect($D['hab'])->map(fn ($t) => $B($rich($t)))->join(''); }
    if ($D['idiomas']) { $side .= $h('Idiomas').collect($D['idiomas'])->map(fn ($t) => $B(e($t)))->join(''); }
    if ($D['edu']) { $side .= $h('Educación').collect($D['edu'])->map(fn ($e) => $B('<b>'.e(CV::v($e, 'grado') ?: CV::v($e, 'inst')).'</b>').(CV::v($e, 'grado') !== '' && CV::v($e, 'inst') !== '' ? $I(e(CV::v($e, 'inst'))) : '').($fechas($e) !== '' ? $I(e($fechas($e))) : ''))->join(''); }
    if ($D['perfil']) { $main .= $h('Acerca de mí').collect($D['perfil'])->map(fn ($x) => $p('margin:0 0 6pt 0;text-align:justify', e($x)))->join(''); }
    if ($D['exp']) { $main .= $h('Experiencia laboral').collect($D['exp'])->map(fn ($e) => $B('<b>'.e(mb_strtoupper(CV::v($e, 'cargo'))).'</b>').(CV::v($e, 'emp') !== '' || CV::v($e, 'lugar') !== '' ? $I(e(implode(' – ', array_filter([CV::v($e, 'emp'), CV::v($e, 'lugar')])))) : '').($fechas($e) !== '' ? $I(e($fechas($e))) : '').collect(CV::lineas($e['fun'] ?? ''))->map(fn ($x) => $p('margin:0 0 3pt 12pt;text-align:justify', $rich($x)))->join('').$p('margin:0', '&nbsp;'))->join(''); }
    if ($D['cert']) { $main .= $h('Cursos y certificaciones').collect($D['cert'])->map(fn ($c) => $B('<b>'.e(CV::v($c, 'nom')).'</b>').(CV::v($c, 'inst') !== '' || CV::v($c, 'fecha') !== '' ? $I(e(implode(' · ', array_filter([CV::v($c, 'inst'), CV::v($c, 'fecha')])))) : ''))->join(''); }
    if ($D['refs']) { $main .= $h('Referencias').collect($D['refs'])->map(fn ($t) => $B(e($t)))->join(''); }
    [$n1, $n2] = $D['nombre'];
    $der = $D['tema']['lado'] === 'der';
    $sideTd = '<td width="40%" valign=top style="background:'.$K['s'].';padding:16pt 14pt">'.$side.'</td>';
    $mainTd = '<td width="60%" valign=top style="padding:16pt 16pt">'.$main.'</td>';
    $nameTd = '<td width="60%" valign=middle style="padding:18pt 16pt">'.$p('margin:0;font-size:30pt;line-height:105%;color:'.$C, '<b>'.e($n1).($n2 !== '' ? '<br>'.e($n2) : '').'</b>').($D['titulo'] !== '' ? $p('margin:8pt 0 0 0;font-size:10pt;letter-spacing:2pt;color:'.$C, '<b>'.e(mb_strtoupper($D['titulo'])).'</b>') : '').'</td>';
    $blankTd = '<td width="40%" style="padding:0">&nbsp;</td>';
@endphp
<table width="100%" border=0 cellspacing=0 cellpadding=0 style="background:{{ $K['h'] }}"><tr>{!! $der ? $nameTd.$blankTd : $blankTd.$nameTd !!}</tr></table>
<table width="100%" border=0 cellspacing=0 cellpadding=0><tr>{!! $der ? $mainTd.$sideTd : $sideTd.$mainTd !!}</tr></table>
@endif
</div></body></html>
