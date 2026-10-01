<?php

return [
    'id' => 'compromiso', 'nombre' => 'Compromiso de pago', 'icono' => '🧾', 'cobro' => 'pagina', 'seccion' => 'contratos', 'orden' => 7,
    'contrato' => true, 'niveles' => true,
    'desc' => 'Reconocimiento de deuda con cronograma de cuotas: para deudas de compras, préstamos o servicios.',
    'nota' => 'Deja constancia de la deuda y de cómo se pagará. Para mayor seguridad, legaliza las firmas ante notario. Si se quiere cobrar judicialmente de forma más rápida, conviene además que el deudor firme una letra de cambio o un pagaré.',
    'campos' => [
        ['t' => 'persona', 'id' => 'deu', 'label' => 'Deudor (quien debe)'],
        ['t' => 'persona', 'id' => 'acr', 'label' => 'Acreedor (a quien se le debe)'],
        ['t' => 'h', 'label' => 'La deuda'],
        ['id' => 'monto', 'label' => 'Monto de la deuda (S/)', 'type' => 'money', 'req' => true],
        ['id' => 'origen', 'label' => 'Por qué se debe', 'type' => 'area', 'req' => true, 'full' => true, 'ayuda' => 'Sigue a «por concepto de».',
            'ph' => 'Ej.: le prestó plata en marzo para su negocio y no le devolvió',
            'frases' => ['un préstamo de dinero otorgado en efectivo', 'la compra de mercadería al crédito', 'servicios prestados y no pagados', 'la compra de un vehículo', 'el alquiler de un inmueble por los meses de ']],
        ['grupo' => 'pago_cuotas'],
        ['id' => 'penal', 'label' => 'Penalidad por día de atraso (S/, opcional)', 'type' => 'money'],
        ['t' => 'persona', 'id' => 'fia', 'label' => 'Fiador', 'si' => 'cl:fiador'],
    ],
    'titulo' => 'COMPROMISO DE PAGO Y RECONOCIMIENTO DE DEUDA',
    'intro' => "{{ \$h::introPartes('compromiso de pago y reconocimiento de deuda', \$h->persona('deu'), 'EL DEUDOR', \$h->persona('acr'), 'EL ACREEDOR') }}",
    'firmas' => <<<'B'
{{ $h->firma('deu', 'EL DEUDOR') }}
{{ $h->firma('acr', 'EL ACREEDOR') }}
@if ($cl('fiador') && $h->hay('fia_nombre'))
{{ $h->firma('fia', 'EL FIADOR') }}
@endif
B,
    'sin_comunes' => ['fuerza'],
    'clausulas' => [
        ['id' => 'recon', 'h' => 'RECONOCIMIENTO DE DEUDA', 'n' => 1, 't' => "EL DEUDOR reconoce adeudar a EL ACREEDOR la suma de **{{ \$h->monto('monto') }}** por concepto de {{ \$h->hay('origen') ? \$h->sin('origen', 'por concepto de') : '[COMPLETAR]' }}, deuda que declara cierta y líquida, y que no ha sido pagada a la fecha."],
        ['id' => 'pago', 'h' => 'COMPROMISO Y CRONOGRAMA DE PAGO', 'n' => 1, 't' => "{{ \$h->pagoTxt(\$h->c('monto'), 'EL DEUDOR', 'EL ACREEDOR') }}\n@foreach (\$h->cuotas(\$h->c('monto')) as \$c)\n[li] {{ \$c }}\n@endforeach"],
        ['id' => 'lugar', 'h' => 'LUGAR Y FORMA DE PAGO', 'n' => 2, 't' => "{{ \$h->lugarTxt('EL ACREEDOR') }}"],
        ['id' => 'mora', 'h' => 'PENALIDAD POR ATRASO', 'n' => 2, 't' => "@if (\$h->c('penal') > 0)\nPor cada día de atraso en el pago de {{ (\$d['forma'] ?? '') === 'unica' ? 'la deuda' : 'cualquier cuota' }}, EL DEUDOR pagará a EL ACREEDOR una penalidad de {{ \$h->monto('penal') }}, conforme a los artículos 1341 y siguientes del Código Civil.\n@endif"],
        ['id' => 'vencAnt', 'h' => 'VENCIMIENTO ANTICIPADO', 'n' => 3, 't' => "@if ((\$d['forma'] ?? '') !== 'unica')\nSi EL DEUDOR dejara de pagar dos (2) cuotas consecutivas, EL ACREEDOR podrá dar por vencidos todos los plazos y exigir el pago inmediato del total del saldo pendiente, más las penalidades que correspondan.\n@endif"],
        ['id' => 'noNov', 'h' => 'CONSERVACIÓN DE LA DEUDA', 'n' => 3, 't' => 'El presente compromiso no extingue ni sustituye la obligación original, sino que establece la forma de su pago; en caso de incumplimiento, EL ACREEDOR conserva todos los derechos que la ley le confiere para su cobro.'],
        ['id' => 'fiador', 'h' => 'FIADOR SOLIDARIO', 'opt' => true, 't' => "Interviene en el presente documento {{ \$h->persona('fia') }}, a quien en adelante se le denominará **EL FIADOR**, quien se constituye en fiador solidario de EL DEUDOR por el pago total de la deuda, renunciando expresamente al beneficio de excusión, conforme a los artículos 1868 y siguientes del Código Civil."],
    ],
];
