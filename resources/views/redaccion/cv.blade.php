{{--
    El currículum en sus 4 diseños (cvPDF y cvmHTML del sistema anterior). La misma vista sirve para la pantalla y para el PDF:
    todo en milímetros y con tablas, que dompdf dibuja bien. $d = datos del formulario, $diseno = cv_simple | cv_medio | cv_harvard | cv_moderno.
--}}
@php
    use App\Redaccion\Curriculum as CV;
    $seg = fn ($segs) => collect($segs)->map(function ($s) {
        $h = str_replace('[COMPLETAR]', '<mark>[COMPLETAR]</mark>', e($s[0]));
        if (! empty($s[2])) { $h = '<i>'.$h.'</i>'; }
        return ! empty($s[1]) ? '<b>'.$h.'</b>' : $h;
    })->join('');
    $pdf = ! empty($pdf);
@endphp
@if ($diseno !== 'cv_moderno')
    @php
        $H = $diseno === 'cv_harvard'; $M = $diseno === 'cv_medio';
        $acc = $M ? '#1F4E79' : '#111111';
        $bloques = CV::bloques($d, $diseno);
        $foto = $M ? CV::foto($d) : null;
        [$nombre, $contacto] = [$bloques[0], $bloques[1]];
    @endphp
    <div class="cvx {{ $H ? 'harv' : '' }}">
    <style>
        .cvx { font-family: {!! $H ? '"Times New Roman", Times, serif' : 'Helvetica, Arial, sans-serif' !!}; font-size: {{ $H ? 11 : 10 }}pt; line-height: 1.38; color: #111; }
        .cvx table { border-collapse: collapse; width: 100%; background: none; border: 0; border-radius: 0; overflow: visible; }
        .cvx td { padding: 0; vertical-align: top; border: 0; font-size: inherit; text-align: left; }
        .cvx .cv-nm { font-size: {{ $H ? 20 : 19 }}pt; font-weight: bold; line-height: 1.2; text-align: {{ $M ? 'left' : 'center' }}; color: {{ $acc }}; }
        .cvx .cv-tt { font-size: 11.5pt; color: {{ $M ? '#464646' : '#3c3c3c' }}; text-align: {{ $M ? 'left' : 'center' }}; margin-top: .5mm; }
        .cvx .cv-ct { font-size: 9.5pt; color: #323232; text-align: {{ $M ? 'left' : 'center' }}; margin: 1mm 0 1.5mm; }
        .cvx .cv-rule { border-top: {{ $M ? '.6mm' : '.35mm' }} solid {{ $acc }}; margin: 0 0 4mm; height: 0; }
        .cvx .cv-h { font-size: 10.5pt; font-weight: bold; text-transform: uppercase; color: {{ $acc }}; border-bottom: {{ $M ? '.5mm' : '.3mm' }} solid {{ $acc }}; margin: 3.5mm 0 2.4mm; padding-bottom: .6mm; }
        .cvx .cv-row { margin-top: 1mm; }
        .cvx .cv-row td.cv-r, .cvx .cv-sub td.cv-r, .cvx td.cv-fotoc { text-align: right; white-space: nowrap; padding-left: 4mm; }
        .cvx .cv-sub { font-size: {{ ($H ? 11 : 10) - .5 }}pt; margin-bottom: 1.2mm; }
        .cvx .cv-bul { padding-left: 5mm; text-align: justify; margin-bottom: .6mm; position: relative; }
        .cvx .cv-bul .cv-pt { position: absolute; left: 1.2mm; top: 0; }
        .cvx .cv-sum { text-align: justify; margin-bottom: 2.5mm; }
        .cvx .cv-p { margin-bottom: .6mm; }
        .cvx .cv-foto { width: 27mm; height: 34mm; border: .2mm solid #ccc; }
    </style>
    <table><tr>
        <td>
            <div class="cv-nm">{!! $seg([[$nombre['t'], false, false]]) !!}</div>
            @if ($nombre['sub'] !== '')<div class="cv-tt">{{ $nombre['sub'] }}</div>@endif
            <div class="cv-ct">{!! $seg([[$contacto['t'], false, false]]) !!}</div>
        </td>
        @if ($foto)<td class="cv-fotoc" style="width:29mm"><img class="cv-foto" src="{{ $foto }}" alt="Foto"></td>@endif
    </tr></table>
    <div class="cv-rule"></div>
    @foreach (array_slice($bloques, 2) as $b)
        @switch ($b['k'])
            @case('cvh')<div class="cv-h">{{ $b['t'] }}</div>@break
            @case('cvrow')
            @case('cvsub')
                @if (collect([...$b['l'], ...$b['r']])->contains(fn ($s) => trim($s[0]) !== ''))
                <table class="{{ $b['k'] === 'cvrow' ? 'cv-row' : 'cv-sub' }}"><tr><td>{!! $seg($b['l']) !!}</td>@if (collect($b['r'])->contains(fn ($s) => trim($s[0]) !== ''))<td class="cv-r">{!! $seg($b['r']) !!}</td>@endif</tr></table>
                @endif
                @break
            @case('bul')<div class="cv-bul"><span class="cv-pt">•</span>{!! $seg($b['s']) !!}</div>@break
            @case('cvsum')<div class="cv-sum">{!! $seg($b['s']) !!}</div>@break
            @case('cvp')<div class="cv-p">{!! $seg($b['s']) !!}</div>@break
        @endswitch
    @endforeach
    </div>
@else
    @php
        $D = CV::moderno($d); $T = $D['tema']; $K = $D['col'];
        $z = $T['tam'] === 'compacto' ? .9 : 1;
        $der = $T['lado'] === 'der';
        $conFoto = $T['foto'] !== 'sin';
        $foto = $conFoto ? CV::fotoRecortada($D['foto'], $T['foto']) : null;
        [$n1, $n2] = $D['nombre'];
        $larga = max(mb_strlen($n1), mb_strlen($n2));
        $nsz = $larga > 16 ? 25 : ($larga > 12 ? 29 : 33);
        $rich = fn ($t) => $seg(CV::tema($t));
        $fechas = fn ($x) => CV::fechas($x['desde'] ?? '', $x['hasta'] ?? '');
    @endphp
    <div class="cvm2">
    <style>
        .cvm2 { font-family: {!! $T['fuente'] === 'serif' ? '"Times New Roman", Times, serif' : 'Helvetica, Arial, sans-serif' !!}; font-size: {{ round(9.6 * $z, 2) }}pt; line-height: 1.36; color: #191919; }
        .cvm2 table { border-collapse: collapse; width: 100%; background: none; border: 0; border-radius: 0; overflow: visible; }
        .cvm2 td { padding: 0; vertical-align: top; border: 0; font-size: inherit; text-align: left; }
        .cvm2 .cv-band td { background: {{ $K['h'] }}; height: 64mm; vertical-align: middle; }
        .cvm2 td.cv-phc { width: 86mm; text-align: center; }
        .cvm2 .cv-ph { width: 51mm; height: 51mm; }
        .cvm2 .cv-ini { width: 51mm; height: 51mm; line-height: 51mm; margin: 0 auto; background: {{ $K['c'] }}; color: #fff; font-weight: bold; font-size: 30pt; text-align: center; border-radius: {{ $T['foto'] === 'cuadrado' ? '4mm' : '26mm' }}; }
        .cvm2 td.cv-nmc { padding: 0 12mm 0 {{ $conFoto ? '12mm' : '14mm' }}; color: {{ $K['c'] }}; }
        .cvm2 .cv-n { font-weight: bold; line-height: 1.08; font-size: {{ $nsz }}pt; }
        .cvm2 .cv-tt { margin-top: 4mm; font-weight: bold; font-size: 10pt; letter-spacing: .35mm; text-transform: uppercase; }
        .cvm2 td.cv-side { width: 61mm; background: {{ $K['s'] }}; padding: 12mm 12mm 10mm 13mm; }
        .cvm2 td.cv-main { padding: 12mm 14mm 10mm 12mm; }
        .cvm2 .cv-lat { position: absolute; top: 64mm; width: 61mm; padding: 12mm 12mm 0 13mm; }
        .cvm2 .cv-princ { padding: 12mm 14mm 0 12mm; }
        .cvm2 .cv-h { color: {{ $K['c'] }}; font-weight: bold; font-size: {{ round(12.2 * $z, 2) }}pt; letter-spacing: .6mm; text-transform: uppercase; margin: 4mm 0 4.5mm; padding-bottom: 2.2mm; border-bottom: .95mm solid {{ $K['c'] }}; width: 15mm; white-space: nowrap; }
        .cvm2 .cv-h.cv-first { margin-top: 0; }
        .cvm2 .cv-ctc td { padding-bottom: 2.4mm; vertical-align: middle; }
        .cvm2 .cv-ctc img { width: 7mm; height: 7mm; }
        .cvm2 .cv-li { padding-left: 5mm; position: relative; margin-bottom: {{ round(1 * $z, 2) }}mm; }
        .cvm2 .cv-li .cv-pt { position: absolute; left: .6mm; top: 0; font-weight: bold; }
        .cvm2 .cv-gris { color: #464646; }
        .cvm2 .cv-sm { font-size: .9em; color: #464646; font-style: italic; }
        .cvm2 .cv-jus { text-align: justify; }
        .cvm2 .cv-gap { height: {{ round(3 * $z, 2) }}mm; }
    </style>
    @php
        $fotoTd = $conFoto ? '<td class="cv-phc">'.($foto ? '<img class="cv-ph" src="'.$foto.'" alt="Foto">' : '<div class="cv-ini">'.e($D['ini']).'</div>').'</td>' : '';
        $nombreTd = '<td class="cv-nmc"><div class="cv-n">'.$seg([[$n1, false, false]]).($n2 !== '' ? '<br>'.e($n2) : '').'</div>'.($D['titulo'] !== '' ? '<div class="cv-tt">'.e($D['titulo']).'</div>' : '').'</td>';
        $primera = true;
        $h = function ($t) use (&$primera) { $x = '<div class="cv-h'.($primera ? ' cv-first' : '').'">'.e($t).'</div>'; $primera = false; return $x; };
    @endphp
    <table class="cv-band"><tr>{!! $der ? $nombreTd.$fotoTd : $fotoTd.$nombreTd !!}</tr></table>
    @php
        ob_start();
    @endphp
        @if ($D['contacto'])
            {!! $h('Contacto') !!}
            <table class="cv-ctc">@foreach ($D['contacto'] as $k => $v)<tr><td style="width:10mm"><img src="{{ CV::icono($k, $K['c']) }}" alt=""></td><td>{{ $v }}</td></tr>@endforeach</table>
        @endif
        @if ($D['personales'])
            {!! $h('Datos personales') !!}
            @foreach ($D['personales'] as $k => $v)<div style="margin-bottom:.6mm"><b>{{ $k }}:</b> {{ $v }}</div>@endforeach
        @endif
        @if ($D['hab'])
            {!! $h('Habilidades') !!}
            @foreach ($D['hab'] as $t)<div class="cv-li"><span class="cv-pt">•</span>{!! $rich($t) !!}</div>@endforeach
        @endif
        @if ($D['idiomas'])
            {!! $h('Idiomas') !!}
            @foreach ($D['idiomas'] as $t)<div class="cv-li"><span class="cv-pt">•</span>{{ $t }}</div>@endforeach
        @endif
        @if ($D['edu'])
            {!! $h('Educación') !!}
            @foreach ($D['edu'] as $e)
                <div class="cv-li"><span class="cv-pt">•</span><b>{{ CV::v($e, 'grado') ?: CV::v($e, 'inst') }}</b>
                    @if (CV::v($e, 'grado') !== '' && CV::v($e, 'inst') !== '')<div>{{ CV::v($e, 'inst') }}</div>@endif
                    @if ($fechas($e) !== '')<div class="cv-gris">{{ $fechas($e) }}</div>@endif
                    @foreach (CV::lineas($e['det'] ?? '') as $x)<div class="cv-sm">{{ $x }}</div>@endforeach</div>
                <div class="cv-gap"></div>
            @endforeach
        @endif
    @php
        $side = ob_get_clean(); $primera = true; ob_start();
    @endphp
        @if ($D['perfil'])
            {!! $h('Acerca de mí') !!}
            @foreach ($D['perfil'] as $p)<div class="cv-jus" style="margin-bottom:2.4mm">{{ $p }}</div>@endforeach
        @endif
        @if ($D['exp'])
            {!! $h('Experiencia laboral') !!}
            @foreach ($D['exp'] as $e)
                <div class="cv-li"><span class="cv-pt">•</span><b>{{ mb_strtoupper(CV::v($e, 'cargo')) }}</b>
                    @if (CV::v($e, 'emp') !== '' || CV::v($e, 'lugar') !== '')<div>{{ implode(' – ', array_filter([CV::v($e, 'emp'), CV::v($e, 'lugar')])) }}</div>@endif
                    @if ($fechas($e) !== '')<div class="cv-gris" style="margin-bottom:1.8mm">{{ $fechas($e) }}</div>@endif
                    @foreach (CV::lineas($e['fun'] ?? '') as $x)<div class="cv-jus" style="margin-bottom:1.2mm">{!! $rich($x) !!}</div>@endforeach</div>
                <div class="cv-gap"></div>
            @endforeach
        @endif
        @if ($D['cert'])
            {!! $h('Cursos y certificaciones') !!}
            @foreach ($D['cert'] as $c)
                <div class="cv-li"><span class="cv-pt">•</span><b>{{ CV::v($c, 'nom') }}</b>
                    @if (CV::v($c, 'inst') !== '' || CV::v($c, 'fecha') !== '')<div class="cv-gris">{{ implode(' · ', array_filter([CV::v($c, 'inst'), CV::v($c, 'fecha')])) }}</div>@endif</div>
            @endforeach
        @endif
        @if ($D['refs'])
            {!! $h('Referencias') !!}
            @foreach ($D['refs'] as $t)<div class="cv-li"><span class="cv-pt">•</span>{{ $t }}</div>@endforeach
        @endif
    @php
        $main = ob_get_clean();
        $sideTd = '<td class="cv-side">'.$side.'</td>';
        $mainTd = '<td class="cv-main">'.$main.'</td>';
    @endphp
    @if ($pdf)
        {{-- en el PDF la columna lateral va fija y la principal sigue en la hoja siguiente si no entra (una fila de tabla no se parte entre hojas) --}}
        <div class="cv-lat" style="{{ $der ? 'right' : 'left' }}:0">{!! $side !!}</div>
        <div class="cv-princ" style="margin-{{ $der ? 'right' : 'left' }}:86mm">{!! $main !!}</div>
    @else
        <table class="cv-body"><tr>{!! $der ? $mainTd.$sideTd : $sideTd.$mainTd !!}</tr></table>
    @endif
    </div>
@endif
