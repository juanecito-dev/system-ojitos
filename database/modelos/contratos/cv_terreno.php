<?php

/* Contrato: 'titulo', 'intro' y 'firmas' se arman con Blade; cada cláusula tiene su nivel (n) y su texto (t).
   El motor agrega las cláusulas comunes, la numeración, el cierre, los testigos, ciudad y fecha. */
return [
    'id' => 'cv_terreno', 'nombre' => 'Compraventa de terreno', 'icono' => '🏡', 'cobro' => 'pagina', 'seccion' => 'contratos', 'orden' => 1,
    'contrato' => true, 'niveles' => true,
    'desc' => 'Venta de lote o terreno entre particulares, con linderos, precio y saneamiento.',
    'nota' => 'Es un contrato privado válido entre las partes. Para inscribir la propiedad en SUNARP debe elevarse a escritura pública ante notario. Si el vendedor es casado y el terreno es bien de la sociedad conyugal, debe firmar también su cónyuge (art. 315 del Código Civil).',
    'campos' => [
        ['t' => 'persona', 'id' => 'vend', 'label' => 'Vendedor', 'cony' => true],
        ['t' => 'persona', 'id' => 'comp', 'label' => 'Comprador', 'cony' => true],
        ['t' => 'h', 'label' => 'El terreno'],
        ['id' => 'ubic', 'label' => 'Ubicación (dirección, Mz., Lote, sector)', 'req' => true, 'full' => true, 'ph' => 'Ej.: Jr. Los Pinos Mz. B Lote 12, sector Castillo Grande'],
        ['grupo' => 'ubic'],
        ['id' => 'area', 'label' => 'Área (m²)', 'type' => 'num', 'req' => true],
        ['id' => 'frente', 'label' => 'Por el frente', 'ph' => '10.00 ml, con Jr. Los Pinos'],
        ['id' => 'der', 'label' => 'Por la derecha entrando', 'ph' => '25.00 ml, con el Lote 11'],
        ['id' => 'izq', 'label' => 'Por la izquierda entrando', 'ph' => '25.00 ml, con el Lote 13'],
        ['id' => 'fondo', 'label' => 'Por el fondo', 'ph' => '10.00 ml, con el Lote 5'],
        ['id' => 'antec', 'label' => '¿Cómo lo adquirió el vendedor?', 'type' => 'select', 'def' => 'compraventa a su anterior propietario',
            'opts' => ['compraventa a su anterior propietario' => 'Por compraventa', 'herencia (sucesión intestada o testamento)' => 'Por herencia', 'título de propiedad otorgado por COFOPRI' => 'Título de COFOPRI',
                'adjudicación' => 'Por adjudicación', 'posesión continua, pacífica y pública, acreditada con constancia de posesión' => 'Constancia de posesión']],
        ['id' => 'partida', 'label' => 'Partida registral SUNARP (si tiene)', 'ph' => 'Opcional'],
        ['t' => 'h', 'label' => 'Precio y entrega'],
        ['grupo' => 'precio'],
        ['id' => 'entrega', 'label' => 'Fecha de entrega del terreno', 'type' => 'date', 'def' => '@hoy'],
        ['grupo' => 'gastos'],
    ],
    'titulo' => 'CONTRATO PRIVADO DE COMPRAVENTA DE TERRENO',
    'intro' => "{{ \$h::introPartes('contrato de compraventa', \$h->persona('vend'), 'EL VENDEDOR', \$h->persona('comp'), 'EL COMPRADOR') }}",
    'firmas' => "{{ \$h->firmasCony('vend', 'EL VENDEDOR') }}\n{{ \$h->firmasCony('comp', 'EL COMPRADOR') }}",
    'sin_comunes' => ['decl'],
    'clausulas' => [
        ['id' => 'antec', 'h' => 'ANTECEDENTES', 'n' => 1, 't' => <<<'B'
EL VENDEDOR es propietario del terreno ubicado en {{ $h->x('ubic') }}, distrito de {{ $h->x('dist') }}, provincia de {{ $h->x('prov') }}, departamento de {{ $h->x('dep') }}, con un área de {{ $h->area('area') }}, comprendido dentro de los siguientes linderos y medidas perimétricas: por el frente, {{ $h->x('frente') }}; por la derecha entrando, {{ $h->x('der') }}; por la izquierda entrando, {{ $h->x('izq') }}; y por el fondo, {{ $h->x('fondo') }}. El referido inmueble fue adquirido por EL VENDEDOR mediante {{ $h->x('antec') }}{{ $h->hay('partida') ? ', y se encuentra inscrito en la Partida Electrónica N° '.$h->v('partida').' del Registro de Predios de la Superintendencia Nacional de los Registros Públicos (SUNARP)' : '' }}.
B],
        ['id' => 'objeto', 'h' => 'OBJETO', 'n' => 1, 't' => 'Por el presente contrato, EL VENDEDOR da en venta real y enajenación perpetua a favor de EL COMPRADOR el inmueble descrito en la cláusula anterior, comprendiendo la transferencia sus aires, usos, costumbres, servidumbres y todo cuanto de hecho y por derecho le corresponde, sin reserva ni limitación alguna.'],
        ['id' => 'precio', 'h' => 'PRECIO Y FORMA DE PAGO', 'n' => 1, 't' => '{{ $h->clPrecio() }}'],
        ['id' => 'equiv', 'h' => 'EQUIVALENCIA', 'n' => 2, 't' => 'Las partes declaran que entre el precio pactado y el valor del inmueble existe la más justa y perfecta equivalencia, y que celebran el presente contrato de manera libre y voluntaria, sin que medie error, dolo, violencia ni intimidación que pudiera invalidarlo.'],
        ['id' => 'saneam', 'h' => 'SANEAMIENTO', 'n' => 2, 't' => 'EL VENDEDOR declara que sobre el inmueble materia de venta no pesa carga, gravamen, hipoteca, embargo, medida judicial o extrajudicial, ni proceso alguno que limite su libre disposición, y se obliga al saneamiento por evicción, por vicios ocultos y por hecho propio, conforme a los artículos 1484 y siguientes del Código Civil.'],
        ['id' => 'entrega', 'h' => 'ENTREGA Y TRIBUTOS', 'n' => 1, 't' => <<<'B'
EL VENDEDOR entrega la posesión del inmueble a EL COMPRADOR el {{ $h->fecha('entrega') }}, libre de ocupantes, junto con los documentos que sustentan su derecho de propiedad.{{ $nv >= 2 ? ' El impuesto predial del presente año es de cargo de EL VENDEDOR; los arbitrios municipales serán de cargo de EL VENDEDOR hasta la fecha de entrega y, desde entonces, de EL COMPRADOR. El impuesto de alcabala, de corresponder, será asumido por EL COMPRADOR.' : '' }}
B],
        ['id' => 'deudas', 'h' => 'DEUDAS Y SERVICIOS', 'n' => 3, 't' => 'EL VENDEDOR declara que el inmueble no registra deudas por impuesto predial, arbitrios municipales ni servicios de agua y energía eléctrica hasta la fecha de entrega. Cualquier deuda originada antes de esa fecha que apareciera posteriormente será de su exclusiva cuenta, obligándose a pagarla dentro de los diez (10) días de requerido.'],
        ['id' => 'riesgo', 'h' => 'TRANSFERENCIA DEL RIESGO', 'n' => 3, 't' => 'A partir de la entrega del inmueble, EL COMPRADOR asume el riesgo de su pérdida o deterioro no imputable a las partes, conforme al artículo 1567 del Código Civil.'],
        ['id' => 'formal', 'h' => 'FORMALIZACIÓN', 'n' => 2, 't' => 'EL VENDEDOR se obliga a otorgar la escritura pública de compraventa y a suscribir todos los documentos que sean necesarios para su inscripción en los Registros Públicos, cuando EL COMPRADOR lo solicite, conforme al artículo 1412 del Código Civil. Los gastos notariales y registrales serán asumidos por {{ $h->x(\'gastos\') }}.'],
        ['id' => 'incump', 'h' => 'INCUMPLIMIENTO', 'n' => 3, 't' => '{{ $h->incumplimientoVenta() }}'],
    ],
];
