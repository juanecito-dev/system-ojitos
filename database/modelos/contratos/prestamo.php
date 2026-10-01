<?php

return [
    'id' => 'prestamo', 'nombre' => 'Préstamo de dinero', 'icono' => '💵', 'cobro' => 'pagina', 'seccion' => 'contratos', 'orden' => 6,
    'contrato' => true, 'niveles' => true,
    'desc' => 'Mutuo entre personas: monto, intereses, cuotas con cronograma y fiador si se quiere.',
    'nota' => 'El préstamo de dinero (mutuo) está regulado en los artículos 1648 y siguientes del Código Civil. Si se pactan intereses, la tasa no debe superar la máxima que fija el Banco Central de Reserva del Perú para operaciones fuera del sistema financiero. Para cobrar judicialmente con más rapidez, además del contrato conviene firmar una letra de cambio o un pagaré. Legaliza las firmas ante notario para darle fecha cierta.',
    'campos' => [
        ['t' => 'persona', 'id' => 'mte', 'label' => 'Quien presta (mutuante)'],
        ['t' => 'persona', 'id' => 'mta', 'label' => 'Quien recibe el préstamo (mutuatario)'],
        ['t' => 'h', 'label' => 'El préstamo'],
        ['id' => 'monto', 'label' => 'Monto prestado (S/)', 'type' => 'money', 'req' => true],
        ['id' => 'entregaP', 'label' => 'Cómo se entrega el dinero', 'type' => 'select', 'def' => 'firma', 'opts' => ['firma' => 'En efectivo, a la firma', 'deposito' => 'Por depósito o transferencia']],
        ['id' => 'destino', 'label' => 'Para qué es el préstamo (opcional)', 'full' => true, 'ph' => 'Ej.: para comprar mercadería de su bodega', 'ayuda' => 'Sigue a «será destinado a».',
            'frases' => ['la compra de mercadería para su negocio', 'gastos de salud', 'la compra de un vehículo', 'la construcción o mejora de su vivienda', 'gastos de estudios']],
        ['id' => 'interes', 'label' => 'Intereses', 'type' => 'select', 'def' => 'no', 'opts' => ['no' => 'Sin intereses', 'si' => 'Con interés mensual']],
        ['id' => 'tasa', 'label' => 'Interés mensual (%)', 'type' => 'num', 'req' => true, 'si' => 'interes=si', 'ph' => 'Ej.: 2'],
        ['t' => 'h', 'label' => 'Devolución'],
        ['grupo' => 'pago_cuotas'],
        ['id' => 'penal', 'label' => 'Penalidad por día de atraso (S/, opcional)', 'type' => 'money'],
        ['t' => 'persona', 'id' => 'fia', 'label' => 'Fiador', 'si' => 'cl:fiador'],
    ],
    'titulo' => 'CONTRATO DE PRÉSTAMO DE DINERO (MUTUO)',
    'intro' => "{{ \$h::introPartes('contrato de préstamo de dinero (mutuo)', \$h->persona('mte'), 'EL MUTUANTE', \$h->persona('mta'), 'EL MUTUATARIO') }}",
    'firmas' => <<<'B'
{{ $h->firma('mte', 'EL MUTUANTE') }}
{{ $h->firma('mta', 'EL MUTUATARIO') }}
@if ($cl('fiador') && $h->hay('fia_nombre'))
{{ $h->firma('fia', 'EL FIADOR') }}
@endif
B,
    'clausulas' => [
        ['id' => 'objeto', 'h' => 'OBJETO', 'n' => 1, 't' => "Por el presente contrato, EL MUTUANTE entrega en préstamo a EL MUTUATARIO la suma de **{{ \$h->monto('monto') }}**, {{ (\$d['entregaP'] ?? '') === 'deposito' ? 'mediante depósito o transferencia bancaria, cuya constancia forma parte del presente contrato' : 'en dinero en efectivo, a la firma del presente documento, que EL MUTUATARIO declara recibir a su entera satisfacción' }}; obligándose EL MUTUATARIO a devolver una cantidad igual en la forma y plazos pactados, conforme al artículo 1648 del Código Civil."],
        ['id' => 'destino', 'h' => 'DESTINO DEL PRÉSTAMO', 'n' => 2, 't' => "@if (\$h->hay('destino'))\nEL MUTUATARIO declara que el dinero recibido será destinado a {{ \$h->sin('destino', 'para') }}.\n@endif"],
        ['id' => 'interes', 'h' => 'INTERESES', 'n' => 1, 't' => "@php \$tot = \$h->prestamoTotal(); @endphp\n{{ (\$d['interes'] ?? '') === 'si' ? 'El presente préstamo genera un interés compensatorio del '.\$h->x('tasa').'% mensual, que por todo el plazo asciende a '.\$h::montoTxt(\$tot - \$h->c('monto')).', de modo que EL MUTUATARIO devolverá en total la suma de **'.\$h::montoTxt(\$tot).'**. Las partes declaran que dicha tasa no excede la máxima permitida por el Banco Central de Reserva del Perú para operaciones entre personas ajenas al sistema financiero.' : 'El presente préstamo no genera intereses compensatorios.' }}"],
        ['id' => 'devol', 'h' => 'DEVOLUCIÓN Y CRONOGRAMA', 'n' => 1, 't' => "{{ \$h->pagoTxt(\$h->prestamoTotal(), 'EL MUTUATARIO', 'EL MUTUANTE') }}\n@foreach (\$h->cuotas(\$h->prestamoTotal()) as \$c)\n[li] {{ \$c }}\n@endforeach"],
        ['id' => 'lugar', 'h' => 'LUGAR Y FORMA DE PAGO', 'n' => 2, 't' => "{{ \$h->lugarTxt('EL MUTUANTE') }} EL MUTUATARIO podrá adelantar pagos en cualquier momento, reduciéndose proporcionalmente los intereses, de haberlos."],
        ['id' => 'mora', 'h' => 'PENALIDAD POR ATRASO', 'n' => 2, 't' => "@if (\$h->c('penal') > 0)\nPor cada día de atraso en el pago de {{ (\$d['forma'] ?? '') === 'unica' ? 'la deuda' : 'cualquier cuota' }}, EL MUTUATARIO pagará a EL MUTUANTE una penalidad de {{ \$h->monto('penal') }}, conforme a los artículos 1341 y siguientes del Código Civil.\n@endif"],
        ['id' => 'vencAnt', 'h' => 'VENCIMIENTO ANTICIPADO', 'n' => 3, 't' => "@if ((\$d['forma'] ?? '') !== 'unica')\nSi EL MUTUATARIO dejara de pagar dos (2) cuotas consecutivas, EL MUTUANTE podrá dar por vencidos todos los plazos y exigir el pago inmediato del total del saldo pendiente, más las penalidades que correspondan.\n@endif"],
        ['id' => 'recon', 'h' => 'RECONOCIMIENTO', 'n' => 3, 't' => 'EL MUTUATARIO reconoce expresamente la existencia de la deuda derivada del presente contrato y se obliga a no invocar ningún hecho que pretenda desconocerla. Cada pago constará en un recibo firmado por EL MUTUANTE o en la constancia de la operación bancaria o digital.'],
        ['id' => 'fiador', 'h' => 'FIADOR SOLIDARIO', 'opt' => true, 't' => "Interviene en el presente contrato {{ \$h->persona('fia') }}, a quien en adelante se le denominará **EL FIADOR**, quien se constituye en fiador solidario de EL MUTUATARIO por todas las obligaciones que este asume en el presente contrato, renunciando expresamente al beneficio de excusión, conforme a los artículos 1868 y siguientes del Código Civil."],
    ],
];
