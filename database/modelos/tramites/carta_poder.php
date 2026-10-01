<?php

return [
    'id' => 'carta_poder', 'nombre' => 'Carta poder', 'icono' => '✍️', 'cobro' => 'doc', 'seccion' => 'cartas', 'orden' => 21,
    'desc' => 'Para que otra persona haga un trámite, recoja documentos o cobre en su nombre.',
    'nota' => 'Muchas entidades (bancos, AFP, ONP, SUNARP, municipalidades) exigen que la carta poder tenga la firma legalizada ante notario o fedatario. Recomienda al cliente consultar antes en la entidad.',
    'campos' => [
        ['t' => 'persona', 'id' => 'pod', 'label' => 'Poderdante (quien da el poder)', 'sinEc' => true],
        ['t' => 'persona', 'id' => 'apo', 'label' => 'Apoderado (quien recibe el poder)', 'sinEc' => true],
        ['id' => 'entidad', 'label' => 'Ante qué entidad', 'full' => true, 'ph' => 'Ej.: la Municipalidad Provincial de Leoncio Prado'],
        ['id' => 'facultad', 'label' => 'Para qué (facultades)', 'type' => 'area', 'req' => true, 'full' => true, 'ph' => 'Ej.: recoger mi certificado de estudios',
            'ayuda' => 'Empieza con un verbo: recoger, realizar, cobrar, firmar…',
            'frases' => ['recoger en mi nombre documentos y/o certificados', 'realizar todos los trámites necesarios, presentar y firmar solicitudes, y recabar la documentación que corresponda',
                'cobrar y recibir en mi nombre el monto que me corresponde, firmando los documentos necesarios', 'recoger mi Documento Nacional de Identidad (DNI)',
                'representarme en la reunión de padres de familia y firmar el acta correspondiente']],
        ['id' => 'vigencia', 'label' => 'Válida hasta (opcional)', 'type' => 'date'],
    ],
    'plantilla' => <<<'B'
[title] CARTA PODER
[p] Yo, {{ $h->persona('pod', sinTrato: true, sinEc: true) }}, por medio del presente documento otorgo **PODER** a favor de {{ $h->persona('apo', sinTrato: true, sinEc: true) }}, para que en mi nombre y representación pueda {{ $h->hay('facultad') ? $h->t('facultad') : '[COMPLETAR]' }}{{ $h->hay('entidad') ? ', ante '.$h->t('entidad') : '' }}.
[p] El presente poder se otorga {{ $h->hay('vigencia') ? 'con vigencia hasta el '.$h->fecha('vigencia') : 'para el fin antes indicado' }}, conforme a las normas sobre representación del Código Civil, pudiendo ser revocado en cualquier momento. Asumo la plena validez de los actos que realice mi apoderado dentro de los alcances de este poder.
[p] Firmo el presente documento en señal de conformidad.
[right] {{ $h->x('ciudad') }}, {{ $h->fecha('fecha') }}.
{{ $h->firma('pod', 'Poderdante') }}
{{ $h->firma('apo', 'Apoderado (acepto el poder)') }}
B,
];
