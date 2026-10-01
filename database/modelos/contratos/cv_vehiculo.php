<?php

return [
    'id' => 'cv_vehiculo', 'nombre' => 'Compraventa de vehículo', 'icono' => '🛺', 'cobro' => 'pagina', 'seccion' => 'contratos', 'orden' => 2,
    'contrato' => true, 'niveles' => true,
    'desc' => 'Auto, camioneta, mototaxi Bajaj o motocicleta, con datos técnicos y responsabilidades.',
    'nota' => 'Este contrato deja constancia del acuerdo y del pago entre las partes. Para que la transferencia se inscriba en SUNARP, vendedor y comprador deben firmar el Acta de Transferencia Vehicular ante notario.',
    'campos' => [
        ['id' => 'tipo', 'label' => 'Tipo de vehículo', 'type' => 'select', 'def' => 'Mototaxi',
            'opts' => ['Mototaxi' => 'Mototaxi (Bajaj u otra)', 'Motocicleta' => 'Motocicleta', 'Automóvil' => 'Automóvil', 'Camioneta' => 'Camioneta', 'Vehículo' => 'Otro']],
        ['t' => 'persona', 'id' => 'vend', 'label' => 'Vendedor', 'cony' => true],
        ['t' => 'persona', 'id' => 'comp', 'label' => 'Comprador', 'cony' => true],
        ['t' => 'h', 'label' => 'Datos del vehículo (tarjeta de propiedad)'],
        ['id' => 'marca', 'label' => 'Marca', 'req' => true, 'ph' => 'Ej.: Bajaj'],
        ['id' => 'modelo', 'label' => 'Modelo', 'ph' => 'Ej.: RE 4S'],
        ['id' => 'anio', 'label' => 'Año de fabricación', 'type' => 'num'],
        ['id' => 'color', 'label' => 'Color'],
        ['id' => 'placa', 'label' => 'Placa de rodaje', 'req' => true],
        ['id' => 'motor', 'label' => 'N° de motor', 'req' => true],
        ['id' => 'serie', 'label' => 'N° de serie / VIN / chasis', 'req' => true],
        ['id' => 'tive', 'label' => 'N° de Tarjeta de Identificación Vehicular', 'ph' => 'Opcional'],
        ['id' => 'acc', 'label' => 'Accesorios que se entregan', 'full' => true, 'ph' => 'Ej.: llanta de repuesto, herramientas, toldo',
            'frases' => ['llanta de repuesto', 'juego de herramientas', 'toldo y cortinas', 'extintor', 'botiquín', 'gata y llave de ruedas']],
        ['t' => 'h', 'label' => 'Precio y entrega'],
        ['grupo' => 'precio'],
        ['id' => 'entrega', 'label' => 'Fecha de entrega del vehículo', 'type' => 'date', 'def' => '@hoy'],
        ['id' => 'hora', 'label' => 'Hora de entrega', 'ph' => 'Ej.: 10:00 a. m.'],
        ['grupo' => 'gastos'],
    ],
    'titulo' => "CONTRATO PRIVADO DE COMPRAVENTA DE {{ ['Mototaxi' => 'MOTOTAXI', 'Motocicleta' => 'MOTOCICLETA', 'Automóvil' => 'VEHÍCULO AUTOMOTOR', 'Camioneta' => 'VEHÍCULO AUTOMOTOR (CAMIONETA)'][\$d['tipo'] ?? ''] ?? 'VEHÍCULO' }}",
    'intro' => "{{ \$h::introPartes('contrato de compraventa', \$h->persona('vend'), 'EL VENDEDOR', \$h->persona('comp'), 'EL COMPRADOR') }}",
    'firmas' => "{{ \$h->firmasCony('vend', 'EL VENDEDOR') }}\n{{ \$h->firmasCony('comp', 'EL COMPRADOR') }}",
    'clausulas' => [
        ['id' => 'antec', 'h' => 'ANTECEDENTES', 'n' => 1, 't' => <<<'B'
@php $tipo = ($d['tipo'] ?? '') ?: 'Vehículo'; $fe = in_array($tipo, ['Mototaxi', 'Motocicleta', 'Camioneta'], true); @endphp
EL VENDEDOR es propietario de {{ $fe ? 'la' : 'el' }} {{ mb_strtolower($tipo) }} que tiene las siguientes características:
[li] Clase: {{ $tipo }}
[li] Marca: {{ $h->x('marca') }}
[li] Modelo: {{ $h->x('modelo') }}
[li] Año de fabricación: {{ $h->x('anio') }}
[li] Color: {{ $h->x('color') }}
[li] Placa de rodaje: **{{ $h->up('placa') }}**
[li] N° de motor: {{ $h->up('motor') }}
[li] N° de serie / VIN: {{ $h->up('serie') }}
@if ($h->hay('tive'))
[li] Tarjeta de Identificación Vehicular N°: {{ $h->v('tive') }}
@endif
B],
        ['id' => 'objeto', 'h' => 'OBJETO', 'n' => 1, 't' => "Por el presente contrato, EL VENDEDOR da en venta a favor de EL COMPRADOR el vehículo descrito en la cláusula anterior, en adelante EL VEHÍCULO, incluyendo sus llaves{{ \$h->hay('acc') ? ' y los siguientes accesorios: '.\$h->t('acc') : '' }}."],
        ['id' => 'precio', 'h' => 'PRECIO Y FORMA DE PAGO', 'n' => 1, 't' => '{{ $h->clPrecio() }}'],
        ['id' => 'estado', 'h' => 'ESTADO DEL VEHÍCULO', 'n' => 2, 't' => 'EL COMPRADOR declara haber revisado EL VEHÍCULO y lo recibe en el estado de conservación y funcionamiento en que se encuentra, el cual conoce y acepta. Sin perjuicio de ello, EL VENDEDOR responde por los vicios ocultos conforme a ley.'],
        ['id' => 'saneam', 'h' => 'SANEAMIENTO', 'n' => 2, 't' => 'EL VENDEDOR declara que EL VEHÍCULO es de su exclusiva propiedad y que sobre él no pesa gravamen, embargo, garantía mobiliaria, orden de captura, medida judicial o administrativa alguna que limite su libre disposición, obligándose al saneamiento por evicción conforme a ley.'],
        ['id' => 'entrega', 'h' => 'ENTREGA Y RESPONSABILIDADES', 'n' => 1, 't' => "EL VENDEDOR entrega EL VEHÍCULO a EL COMPRADOR el {{ \$h->fecha('entrega') }}{{ \$h->hay('hora') ? ', a las '.\$h->v('hora') : '' }}. Son de exclusiva responsabilidad de EL VENDEDOR las papeletas, multas, deudas, tributos, accidentes y cualquier obligación civil, penal o administrativa relacionada con EL VEHÍCULO originadas hasta dicho momento; y de EL COMPRADOR, las que se originen a partir de él."],
        ['id' => 'docs', 'h' => 'DOCUMENTOS QUE SE ENTREGAN', 'n' => 3, 't' => 'Con EL VEHÍCULO, EL VENDEDOR entrega a EL COMPRADOR la Tarjeta de Identificación Vehicular, el certificado del Seguro Obligatorio de Accidentes de Tránsito (SOAT) y el certificado de inspección técnica vehicular, de tenerlos vigentes, así como los demás documentos que sustentan su propiedad.'],
        ['id' => 'papeletas', 'h' => 'PAPELETAS Y REQUISITORIAS', 'n' => 3, 't' => 'EL VENDEDOR declara que a la fecha EL VEHÍCULO no registra papeletas pendientes de pago ni requisitorias. Si aparecieran papeletas, multas o requisitorias por hechos ocurridos antes de la entrega, EL VENDEDOR se obliga a regularizarlas por su cuenta dentro de los diez (10) días de requerido, respondiendo por los daños que su demora ocasione a EL COMPRADOR.'],
        ['id' => 'riesgo', 'h' => 'TRANSFERENCIA DEL RIESGO', 'n' => 3, 't' => 'A partir de la entrega, EL COMPRADOR asume el riesgo de pérdida o deterioro de EL VEHÍCULO no imputable a las partes, conforme al artículo 1567 del Código Civil, así como la responsabilidad por su uso y circulación.'],
        ['id' => 'formal', 'h' => 'FORMALIZACIÓN', 'n' => 2, 't' => "Las partes se obligan a suscribir el Acta de Transferencia Vehicular ante notario público para la inscripción de la transferencia en el Registro de Propiedad Vehicular de la SUNARP, en un plazo no mayor de quince (15) días desde la firma del presente. EL VENDEDOR entregará la Tarjeta de Identificación Vehicular y los demás documentos necesarios. Los gastos notariales y registrales serán asumidos por {{ \$h->x('gastos') }}."],
        ['id' => 'incump', 'h' => 'INCUMPLIMIENTO', 'n' => 3, 't' => '{{ $h->incumplimientoVenta() }}'],
    ],
];
