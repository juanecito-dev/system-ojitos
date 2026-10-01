<?php

return [
    'id' => 'autorizacion', 'nombre' => 'Autorización', 'icono' => '✅', 'cobro' => 'doc', 'seccion' => 'cartas', 'orden' => 22,
    'desc' => 'Autoriza a otra persona a recoger, recibir, retirar o hacer algo en su nombre.',
    'nota' => 'Para viajes de menores de edad sin uno o ambos padres se necesita un permiso notarial de viaje; una autorización simple no es suficiente.',
    'campos' => [
        ['t' => 'persona', 'id' => 'aut', 'label' => 'Quien autoriza', 'sinEc' => true],
        ['t' => 'persona', 'id' => 'bene', 'label' => 'Persona autorizada', 'sinEc' => true],
        ['id' => 'finalidad', 'label' => 'Para qué lo autoriza', 'type' => 'area', 'req' => true, 'full' => true, 'ph' => 'Ej.: recoja en mi nombre mi DNI',
            'ayuda' => 'Sigue a «para que»: recoja, reciba, retire, realice…',
            'frases' => ['recoja en mi nombre', 'reciba en mi nombre', 'retire en mi nombre', 'realice en mi nombre el trámite de', 'recoja a mi menor hijo(a) de la institución educativa']],
        ['id' => 'entidad', 'label' => 'Ante quién (opcional)', 'full' => true, 'ph' => 'Ej.: RENIEC, agencia Tingo María'],
        ['id' => 'vigencia', 'label' => 'Válida hasta (opcional)', 'type' => 'date'],
    ],
    'plantilla' => <<<'B'
[title] AUTORIZACIÓN
[p] Yo, {{ $h->persona('aut', sinTrato: true, sinEc: true) }}, mediante el presente documento **AUTORIZO** a {{ $h->persona('bene', sinTrato: true, sinEc: true) }}, para que {{ $h->hay('finalidad') ? $h->t('finalidad') : '[COMPLETAR]' }}{{ $h->hay('entidad') ? ', ante '.$h->t('entidad') : '' }}{{ $h->hay('vigencia') ? ', autorización válida hasta el '.$h->fecha('vigencia') : '' }}.
[p] Asumo plena responsabilidad por los actos que la persona autorizada realice dentro de los alcances de la presente autorización.
[p] Firmo el presente documento en señal de conformidad.
[right] {{ $h->x('ciudad') }}, {{ $h->fecha('fecha') }}.
{{ $h->firma('aut', 'Autorizante') }}
{{ $h->firma('bene', 'Autorizado(a)') }}
B,
];
