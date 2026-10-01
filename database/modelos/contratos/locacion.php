<?php

return [
    'id' => 'locacion', 'nombre' => 'Contrato por servicio', 'icono' => '🤝', 'cobro' => 'pagina', 'seccion' => 'contratos', 'orden' => 5,
    'contrato' => true, 'niveles' => true,
    'desc' => 'Locación de servicios: trabajos independientes, asesorías, obras menores, diseño, limpieza, etc.',
    'nota' => 'Es un contrato civil (arts. 1764 al 1770 del Código Civil): el locador trabaja con autonomía, sin horario ni subordinación. Ojo: si en la práctica cumple horario fijo, recibe órdenes y trabaja de forma permanente, SUNAFIL o un juez puede considerarlo una relación laboral (principio de primacía de la realidad), aunque el contrato diga lo contrario.',
    'campos' => [
        ['id' => 'tipoCom', 'label' => 'Quien contrata (el comitente) es', 'type' => 'select', 'def' => 'natural', 'opts' => ['natural' => 'Una persona', 'empresa' => 'Una empresa o negocio con RUC']],
        ['id' => 'empresa', 'label' => 'Razón social de la empresa', 'full' => true, 'req' => true, 'si' => 'tipoCom=empresa', 'ph' => 'Ej.: Comercial Huallaga E.I.R.L.'],
        ['id' => 'ruc', 'label' => 'RUC de la empresa', 'num' => true, 'req' => true, 'si' => 'tipoCom=empresa'],
        ['id' => 'domEmp', 'label' => 'Domicilio de la empresa', 'full' => true, 'req' => true, 'si' => 'tipoCom=empresa'],
        ['id' => 'repCargo', 'label' => 'Cargo del representante', 'def' => 'Gerente General', 'si' => 'tipoCom=empresa', 'ph' => 'Ej.: Gerente General, Titular'],
        ['t' => 'persona', 'id' => 'com', 'label' => 'Comitente (o su representante, si es empresa)', 'sinEc' => true, 'sinDom' => true],
        ['id' => 'com_dom', 'label' => 'Domicilio del comitente', 'full' => true, 'req' => true, 'si' => 'tipoCom!=empresa'],
        ['t' => 'persona', 'id' => 'loc', 'label' => 'Locador (quien presta el servicio)', 'sinEc' => true],
        ['id' => 'loc_prof', 'label' => 'Profesión u oficio del locador (opcional)', 'ph' => 'Ej.: técnico electricista'],
        ['id' => 'loc_ruc', 'label' => 'RUC del locador (opcional)', 'num' => true],
        ['t' => 'h', 'label' => 'El servicio'],
        ['id' => 'servicio', 'label' => 'Qué servicio va a prestar', 'type' => 'area', 'req' => true, 'full' => true, 'ayuda' => 'Sigue a «el siguiente servicio:».',
            'ph' => 'Ej.: instalación eléctrica completa de un local comercial de 80 m², incluyendo tablero, tomacorrientes e iluminación',
            'frases' => ['instalación eléctrica completa de ', 'pintado de interiores y exteriores de ', 'limpieza y mantenimiento de ', 'asesoría contable y tributaria mensual', 'diseño e impresión de ', 'construcción de ']],
        ['id' => 'lugar', 'label' => 'Dónde se presta (opcional)', 'full' => true, 'ph' => 'Ej.: Jr. Tito Jaime N° 245, Tingo María'],
        ['id' => 'entregables', 'label' => 'Entregables o resultados (opcional, uno por línea)', 'type' => 'area', 'full' => true, 'modo' => 'lista', 'ph' => "Informe final\nPlanos en PDF",
            'frases' => ['informe final del servicio', 'obra terminada y limpia', 'planos y memoria descriptiva', 'archivos digitales del diseño']],
        ['id' => 'inicio', 'label' => 'Fecha de inicio', 'type' => 'date', 'def' => '@hoy'],
        ['id' => 'fin', 'label' => 'Fecha de término', 'type' => 'date', 'req' => true],
        ['t' => 'h', 'label' => 'Pago'],
        ['id' => 'modo', 'label' => 'Cómo se paga', 'type' => 'select', 'def' => 'total', 'opts' => ['total' => 'Un monto por todo el servicio', 'mensual' => 'Un monto mensual']],
        ['id' => 'monto', 'label' => 'Monto (S/)', 'type' => 'money', 'req' => true],
        ['id' => 'pagoT', 'label' => 'Cuándo se paga', 'type' => 'select', 'def' => 'fin', 'si' => 'modo!=mensual', 'opts' => ['fin' => 'Todo al terminar, con conformidad', 'partes' => 'Adelanto y saldo al terminar']],
        ['id' => 'adelanto', 'label' => 'Adelanto (S/)', 'type' => 'money', 'si' => 'modo!=mensual && pagoT=partes'],
        ['id' => 'dia', 'label' => 'Día de pago de cada mes', 'type' => 'num', 'def' => '30', 'si' => 'modo=mensual'],
        ['id' => 'recibo', 'label' => 'El locador emitirá recibo por honorarios', 'type' => 'check', 'def' => true, 'full' => true],
        ['id' => 'mat', 'label' => 'Materiales y herramientas', 'type' => 'select', 'def' => 'EL LOCADOR', 'opts' => ['EL LOCADOR' => 'Los pone el locador (incluidos en el pago)', 'EL COMITENTE' => 'Los pone quien contrata']],
        ['t' => 'h', 'label' => 'Otras condiciones'],
        ['id' => 'aviso', 'label' => 'Días de aviso para terminar antes', 'type' => 'num', 'def' => '15'],
        ['id' => 'penal', 'label' => 'Penalidad por día de retraso (S/, opcional)', 'type' => 'money'],
    ],
    'titulo' => 'CONTRATO DE LOCACIÓN DE SERVICIOS',
    'intro' => <<<'B'
@php
    $emp = ($d['tipoCom'] ?? '') === 'empresa';
    $comitente = $emp ? '**'.$h->up('empresa').'**, con RUC N° '.$h->x('ruc').', con domicilio en '.$h->x('domEmp').', debidamente representada por su '.$h->x('repCargo').', '.$h->don('com').' **'.$h->up('com_nombre').'**, identificad'.$h->o('com').' con DNI N° '.$h->x('com_dni')
        : $h->persona('com', sinEc: true);
@endphp
{{ $h::introPartes('contrato de locación de servicios', $comitente, 'EL COMITENTE', $h->persona('loc', sinEc: true).($h->hay('loc_ruc') ? ', con RUC N° '.$h->v('loc_ruc') : '').($h->hay('loc_prof') ? ', de profesión u oficio '.$h->v('loc_prof') : ''), 'EL LOCADOR') }}
B,
    'firmas' => <<<'B'
@if (($d['tipoCom'] ?? '') === 'empresa')
{{ $h::firmaTxt($h->up('com_nombre'), $h->x('com_dni'), 'EL COMITENTE (p. '.$h->up('empresa').')') }}
@else
{{ $h->firma('com', 'EL COMITENTE') }}
@endif
{{ $h->firma('loc', 'EL LOCADOR') }}
B,
    'clausulas' => [
        ['id' => 'antec', 'h' => 'ANTECEDENTES', 'n' => 2, 't' => "EL COMITENTE requiere contratar los servicios de una persona que cuente con la experiencia y los medios necesarios para realizar el servicio que se describe en la cláusula siguiente. EL LOCADOR declara contar con la capacidad, la experiencia y los medios necesarios para prestarlo{{ \$h->hay('loc_prof') ? ', en su condición de '.\$h->v('loc_prof') : '' }}."],
        ['id' => 'objeto', 'h' => 'OBJETO', 'n' => 1, 't' => "Por el presente contrato, EL LOCADOR se obliga a prestar a EL COMITENTE, de manera personal y con autonomía, el siguiente servicio: {{ \$h->hay('servicio') ? \$h->t('servicio') : '[COMPLETAR]' }}{{ \$h->hay('lugar') ? ', en '.\$h->t('lugar') : '' }}; a cambio de la retribución pactada en el presente contrato, conforme al artículo 1764 del Código Civil."],
        ['id' => 'entregables', 'h' => 'ENTREGABLES', 'n' => 1, 't' => "@if (\$h->lineas('entregables'))\nComo resultado del servicio, EL LOCADOR entregará a EL COMITENTE lo siguiente: {{ \$h::enumerar(\$h->lineas('entregables')) }}.\n@endif"],
        ['id' => 'plazo', 'h' => 'PLAZO', 'n' => 1, 't' => "El servicio se prestará desde el {{ \$h->fecha('inicio') }} hasta el {{ \$h->fecha('fin') }}. El plazo podrá ampliarse solo por acuerdo escrito de ambas partes."],
        ['id' => 'pago', 'h' => 'RETRIBUCIÓN Y FORMA DE PAGO', 'n' => 1, 't' => <<<'B'
@php
    $monto = $h->c('monto');
    if (($d['modo'] ?? '') === 'mensual') {
        $pago = 'La retribución mensual por el servicio es de **'.$h::montoTxt($monto).'**, que EL COMITENTE pagará a más tardar el día '.$h->x('dia').' de cada mes, por los servicios prestados en dicho mes. De ser el caso, el primer y el último mes se pagarán en proporción a los días de servicio efectivamente prestados.';
    } elseif (($d['pagoT'] ?? '') === 'partes') {
        $a = $h->c('adelanto'); $sal = $monto > 0 && $a > 0 ? $monto - $a : 0;
        $pago = 'La retribución total por el servicio es de **'.$h::montoTxt($monto).'**, que EL COMITENTE pagará de la siguiente manera: a) '.$h::montoTxt($a).' como adelanto, a la firma del presente contrato, que EL LOCADOR declara recibir; y b) el saldo de '.$h::montoTxt($sal).', a la culminación del servicio, previa conformidad de EL COMITENTE.';
    } else {
        $pago = 'La retribución total por el servicio es de **'.$h::montoTxt($monto).'**, que EL COMITENTE pagará a la culminación del servicio, previa conformidad.';
    }
@endphp
{{ $pago }} Dicho monto comprende todos los conceptos que EL LOCADOR pudiera requerir para cumplir el servicio{{ ($d['mat'] ?? '') === 'EL COMITENTE' ? ', con excepción de los materiales y herramientas, que serán proporcionados por EL COMITENTE.' : ', incluidos los materiales y herramientas necesarios, que son de cargo de EL LOCADOR.' }}{{ $h->on('recibo') ? ' Para cada pago, EL LOCADOR emitirá el recibo por honorarios electrónico correspondiente, y EL COMITENTE efectuará las retenciones que, de ser el caso, correspondan conforme a ley.' : ' EL LOCADOR firmará una constancia por cada pago recibido.' }}
B],
        ['id' => 'naturaleza', 'h' => 'NATURALEZA DEL CONTRATO', 'n' => 1, 't' => 'El presente contrato es de naturaleza civil y se rige por los artículos 1764 al 1770 del Código Civil. EL LOCADOR presta el servicio con autonomía técnica y sin subordinación a EL COMITENTE, organizando su tiempo y la forma de ejecutarlo dentro de lo acordado. En consecuencia, entre las partes no existe relación laboral, y EL LOCADOR asume sus propias obligaciones tributarias y, de ser el caso, de seguridad social.'],
        ['id' => 'oblLoc', 'h' => 'OBLIGACIONES DE EL LOCADOR', 'n' => 2, 't' => 'Son obligaciones de EL LOCADOR: a) prestar el servicio de manera personal, con diligencia y conforme a lo acordado, pudiendo valerse de auxiliares bajo su dirección y responsabilidad, conforme al artículo 1766 del Código Civil; b) cumplir los plazos pactados; c) informar a EL COMITENTE sobre el avance del servicio cuando este lo solicite; y d) responder por los daños que cause por su culpa en la ejecución del servicio.'],
        ['id' => 'oblCom', 'h' => 'OBLIGACIONES DE EL COMITENTE', 'n' => 2, 't' => 'Son obligaciones de EL COMITENTE: a) pagar la retribución en la forma y oportunidad pactadas; b) brindar a EL LOCADOR la información, las facilidades y el acceso que sean necesarios para prestar el servicio; y c) dar su conformidad o formular sus observaciones por escrito dentro de los cinco (5) días siguientes a la culminación o entrega del servicio; vencido ese plazo sin observaciones, el servicio se tendrá por conforme.'],
        ['id' => 'penal', 'h' => 'PENALIDAD', 'n' => 2, 't' => "@if (\$h->c('penal') > 0)\nSi EL LOCADOR se retrasa injustificadamente en la ejecución o entrega del servicio, pagará a EL COMITENTE una penalidad de {{ \$h->monto('penal') }} por cada día de retraso, hasta un máximo equivalente al diez por ciento (10%) de la retribución {{ (\$d['modo'] ?? '') === 'mensual' ? 'mensual' : 'total' }}, monto que podrá descontarse de los pagos pendientes, conforme a los artículos 1341 y siguientes del Código Civil.\n@endif"],
        ['id' => 'confid', 'h' => 'CONFIDENCIALIDAD', 'n' => 2, 't' => 'EL LOCADOR se obliga a guardar reserva sobre toda la información de EL COMITENTE a la que tenga acceso con motivo del servicio, y a no divulgarla ni utilizarla para fines distintos al presente contrato, obligación que se mantendrá vigente aun después de terminado el contrato.'],
        ['id' => 'propiedad', 'h' => 'PROPIEDAD DE LOS RESULTADOS', 'opt' => true, 't' => 'Los trabajos, documentos y demás resultados del servicio serán de propiedad de EL COMITENTE una vez pagada la retribución, quien podrá usarlos libremente para sus fines, sin perjuicio de los derechos morales que la ley reconoce a su autor.'],
        ['id' => 'seguridad', 'h' => 'SEGURIDAD Y DAÑOS A TERCEROS', 'n' => 3, 't' => 'EL LOCADOR adoptará las medidas de seguridad necesarias para ejecutar el servicio y será responsable de los daños que, por su culpa o la de sus auxiliares, se causen a EL COMITENTE o a terceros durante su ejecución.'],
        ['id' => 'noExcl', 'h' => 'NO EXCLUSIVIDAD', 'n' => 3, 't' => 'EL LOCADOR podrá prestar servicios a otras personas durante la vigencia del presente contrato, siempre que ello no afecte el cumplimiento oportuno del servicio pactado ni suponga el uso de información reservada de EL COMITENTE.'],
        ['id' => 'termino', 'h' => 'TERMINACIÓN ANTICIPADA Y RESOLUCIÓN', 'n' => 2, 't' => "@php \$av = (int) (\$d['aviso'] ?? 0); @endphp\nCualquiera de las partes podrá dar por terminado el presente contrato antes de su vencimiento, comunicándolo por escrito a la otra con una anticipación no menor de {{ \$av ? \$h::letras(\$av).' ('.\$av.')' : 'quince (15)' }} días calendario, en cuyo caso EL COMITENTE pagará los servicios efectivamente prestados hasta esa fecha. Asimismo, si una de las partes incumple sus obligaciones, la otra podrá requerirle por escrito que las cumpla en un plazo no menor de quince (15) días, bajo apercibimiento de que, en caso contrario, el contrato quede resuelto, conforme al artículo 1429 del Código Civil."],
    ],
];
