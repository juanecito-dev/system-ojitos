<?php

return [
    'id' => 'dj', 'nombre' => 'Declaración jurada', 'icono' => '🙋', 'cobro' => 'doc', 'seccion' => 'declaraciones', 'orden' => 30,
    'desc' => 'Domicilio, convivencia, soltería, ingresos, pérdida de documentos u otra, con una o dos personas.',
    'nota' => 'La declaración jurada se presume verdadera (principio de presunción de veracidad del TUO de la Ley N° 27444). Declarar datos falsos ante una entidad es delito (art. 411 del Código Penal). Algunas entidades piden firma legalizada o huella digital.',
    'campos' => [
        ['id' => 'tipo', 'label' => 'Qué declara', 'type' => 'select', 'def' => 'domicilio',
            'opts' => ['domicilio' => 'Domicilio', 'convivencia' => 'Convivencia (unión de hecho)', 'solteria' => 'Soltería', 'ingresos' => 'Ingresos económicos', 'perdida' => 'Pérdida de documentos', 'otro' => 'Otra declaración']],
        ['t' => 'persona', 'id' => 'dec', 'label' => 'Quien declara'],
        ['id' => 'dos', 'label' => 'Declaran dos personas', 'type' => 'check', 'def' => false, 'full' => true, 'si' => 'tipo!=convivencia'],
        ['t' => 'persona', 'id' => 'dec2', 'label' => 'Segunda persona que declara', 'si' => 'tipo=convivencia || dos'],
        ['t' => 'h', 'label' => 'La declaración'],
        ['id' => 'tiempo', 'label' => 'Desde cuándo vive ahí (opcional)', 'si' => 'tipo=domicilio', 'ph' => 'Ej.: desde hace cinco años', 'frases' => ['desde hace más de cinco años', 'desde hace un año', 'desde mi nacimiento']],
        ['id' => 'desde', 'label' => 'Desde cuándo conviven', 'type' => 'date', 'req' => true, 'si' => 'tipo=convivencia'],
        ['id' => 'hijos', 'label' => 'Hijos en común (opcional)', 'si' => 'tipo=convivencia', 'ph' => 'Ej.: dos hijos, Ana y Luis Pérez Ríos'],
        ['id' => 'actividad', 'label' => 'A qué se dedica', 'req' => true, 'full' => true, 'si' => 'tipo=ingresos', 'ph' => 'Ej.: vendo verduras en el mercado de lunes a sábado',
            'ayuda' => 'Sigue a «me dedico a».',
            'frases' => ['la venta de abarrotes en mi bodega', 'la venta de productos agrícolas en el mercado', 'el servicio de mototaxi', 'la agricultura, cultivando', 'trabajos eventuales de construcción civil']],
        ['id' => 'ingreso', 'label' => 'Ingreso mensual aproximado (S/)', 'type' => 'money', 'req' => true, 'si' => 'tipo=ingresos'],
        ['id' => 'docPerd', 'label' => 'Qué documento perdió', 'req' => true, 'full' => true, 'si' => 'tipo=perdida', 'ph' => 'Ej.: mi DNI y mi tarjeta de débito del BCP',
            'frases' => ['mi Documento Nacional de Identidad (DNI)', 'mi licencia de conducir', 'mi tarjeta de propiedad vehicular', 'mi carné universitario']],
        ['id' => 'circ', 'label' => 'Cuándo y dónde (opcional)', 'full' => true, 'si' => 'tipo=perdida', 'ph' => 'Ej.: el sábado en la feria, creo que se me cayó',
            'frases' => ['ocurrida en circunstancias que desconozco', 'ocurrida en la vía pública, en la ciudad de Tingo María', 'ocurrida durante un viaje interprovincial']],
        ['id' => 'detalle', 'label' => 'Qué más debe decir (con tus palabras)', 'type' => 'area', 'full' => true, 'modo' => 'parrafos',
            'ph' => 'Opcional. Ej.: que no tiene casa propia y vive con sus padres',
            'frases' => ['Que, no cuento con vivienda propia y resido en el domicilio de mis padres.', 'Que, no percibo ingresos de ninguna entidad pública ni privada.',
                'Que, no registro antecedentes penales, judiciales ni policiales.', 'Que, tengo a mi cargo a mis menores hijos, quienes dependen económicamente de mí.']],
        ['id' => 'finalidad', 'label' => 'Para presentar ante (opcional)', 'full' => true, 'ph' => 'Ej.: el Banco de la Nación, para un crédito',
            'frases' => ['la municipalidad, para el trámite que corresponda', 'el Banco de la Nación', 'la institución educativa', 'el Programa Juntos']],
    ],
    'plantilla' => <<<'B'
@php
    $dos = ($d['tipo'] ?? '') === 'convivencia' || $h->on('dos');
    $pl = fn ($s, $p) => $dos ? $p : $s;
    $t0 = ['domicilio' => 'DE DOMICILIO', 'convivencia' => 'DE CONVIVENCIA', 'solteria' => 'DE SOLTERÍA', 'ingresos' => 'DE INGRESOS', 'perdida' => 'POR PÉRDIDA DE DOCUMENTOS'][$d['tipo'] ?? ''] ?? '';
    $f = $h->fem('dec');
    $ps = [];
    if (($d['tipo'] ?? '') === 'domicilio') { $ps[] = 'Que, '.$pl('mi domicilio real y actual se encuentra', 'nuestro domicilio real y actual se encuentra').' ubicado en '.rtrim($h->x('dec_dom'), '.').($h->hay('tiempo') ? ', donde '.$pl('resido', 'residimos').' '.$h->t('tiempo') : '').'.'; }
    if (($d['tipo'] ?? '') === 'convivencia') { $ps[] = 'Que, mantenemos una unión de hecho de manera pública, continua y estable desde el '.$h->fecha('desde').', haciendo vida en común en el domicilio ubicado en '.rtrim($h->x('dec_dom'), '.').', encontrándonos libres de impedimento matrimonial'.($h->hay('hijos') ? ', y habiendo procreado '.$h->t('hijos') : '').'.'; }
    if (($d['tipo'] ?? '') === 'solteria') { $ps[] = 'Que, '.$pl('mi estado civil actual es el de '.($f ? 'soltera' : 'soltero'), 'nuestro estado civil actual es el de solteros').', no habiendo contraído matrimonio civil con persona alguna, en el Perú ni en el extranjero.'; }
    if (($d['tipo'] ?? '') === 'ingresos') { $ps[] = 'Que, me dedico a '.($h->hay('actividad') ? $h->sin('actividad', 'me dedico a') : '[COMPLETAR]').', actividad por la cual percibo un ingreso mensual aproximado de '.$h->monto('ingreso').'.'; }
    if (($d['tipo'] ?? '') === 'perdida') { $ps[] = 'Que, he sufrido la pérdida de '.($h->hay('docPerd') ? $h->t('docPerd') : '[COMPLETAR]').($h->hay('circ') ? ', '.$h->t('circ') : '').', razón por la cual formulo la presente declaración para los fines que correspondan.'; }
    array_push($ps, ...$h->parrafosQue('detalle'));
    if (! $ps) { $ps[] = 'Que, [COMPLETAR]'; }
@endphp
[title] DECLARACIÓN JURADA {{ $t0 }}
[p] {{ $dos ? 'Nosotros, ' : 'Yo, ' }}{{ $h->persona('dec', sinTrato: true) }}{{ $dos ? '; y '.$h->persona('dec2', sinTrato: true) : '' }}, {{ $pl('declaro', 'declaramos') }} bajo juramento lo siguiente:
@foreach ($ps as $t)
[p] {{ $t }}
@endforeach
@if ($h->hay('finalidad'))
[p] {{ $pl('Formulo', 'Formulamos') }} la presente declaración para ser presentada ante {{ $h->t('finalidad') }}.
@endif
[p] {{ $pl('Declaro', 'Declaramos') }} que la información consignada es verdadera, en aplicación del principio de presunción de veracidad del Texto Único Ordenado de la Ley N° 27444, Ley del Procedimiento Administrativo General, y {{ $pl('me someto', 'nos sometemos') }} a las responsabilidades civiles, penales y administrativas que correspondan en caso de falsedad, conforme al artículo 411 del Código Penal.
[right] {{ $h->x('ciudad') }}, {{ $h->fecha('fecha') }}.
{{ $h->firma('dec', 'Declarante') }}
@if ($dos)
{{ $h->firma('dec2', 'Declarante') }}
@endif
B,
];
