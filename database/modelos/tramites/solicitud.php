<?php

return [
    'id' => 'solicitud', 'nombre' => 'Solicitud', 'icono' => '📝', 'cobro' => 'doc', 'seccion' => 'cartas', 'orden' => 20,
    'desc' => 'Para colegios, municipalidades, universidades, empresas y entidades públicas.',
    'nota' => 'Toda persona tiene derecho a presentar peticiones por escrito ante la autoridad (art. 2, inciso 20, de la Constitución). Escribe el pedido con tus palabras o toca una frase lista; si es largo o complicado, usa «Encargar a Claude».',
    'campos' => [
        ['id' => 'sumilla', 'label' => 'Sumilla (asunto)', 'req' => true, 'full' => true, 'ph' => 'Ej.: Solicito constancia de estudios',
            'frases' => ['Solicito constancia de estudios', 'Solicito certificado de estudios', 'Solicito justificación de inasistencias', 'Solicito licencia por motivos de salud', 'Solicito copia certificada de documentos', 'Solicito fraccionamiento de deuda']],
        ['id' => 'cargo', 'label' => 'Dirigida a (cargo)', 'req' => true, 'ph' => 'Ej.: Director(a)', 'frases' => ['Director(a)', 'Alcalde', 'Gerente General', 'Jefe(a) de la Oficina de Registros Académicos', 'Decano(a)']],
        ['id' => 'autoridad', 'label' => 'Nombre de la autoridad', 'ph' => 'Opcional'],
        ['id' => 'inst', 'label' => 'Institución', 'req' => true, 'full' => true, 'ph' => 'Ej.: I.E. N° 32004 San Pedro'],
        ['t' => 'persona', 'id' => 'sol', 'label' => 'Solicitante', 'sinEc' => true],
        ['id' => 'cond', 'label' => 'En calidad de', 'full' => true, 'ph' => 'Ej.: padre de familia del alumno Juan Pérez, del 3.° grado "B"',
            'frases' => ['ex alumno(a) de la institución', 'padre de familia del alumno(a) ', 'estudiante del ciclo ', 'trabajador(a) de la institución', 'vecino(a) del distrito']],
        ['id' => 'pedido', 'label' => 'Lo que solicita (una idea por párrafo)', 'type' => 'area', 'req' => true, 'full' => true, 'modo' => 'parrafos',
            'ph' => 'Escribe con tus palabras. Ej.: necesita constancia de estudios de 2024 para una beca',
            'frases' => [
                'Que, habiendo culminado mis estudios en su institución, requiero el documento indicado en la sumilla para realizar trámites personales.',
                'Que, por motivos de salud debidamente acreditados, no pude asistir en las fechas indicadas, por lo que solicito se tenga por justificada mi inasistencia.',
                'Que, requiero el documento solicitado para presentarlo ante otra institución, por lo que pido se me expida a la brevedad posible.',
                'Que, cumplo con adjuntar los requisitos establecidos en el Texto Único de Procedimientos Administrativos (TUPA) de su institución.',
            ]],
        ['id' => 'anexos', 'label' => 'Anexos (uno por línea)', 'type' => 'area', 'full' => true, 'ph' => "Copia de DNI\nRecibo de pago", 'frases' => ['Copia de DNI', 'Recibo de pago por derecho de trámite', 'Certificado médico']],
    ],
    'plantilla' => <<<'B'
[sum] **SUMILLA:** {{ $h->up('sumilla') }}
[left] **SEÑOR(A) {{ $h->up('cargo') }}{{ $h->hay('autoridad') ? ' '.$h->up('autoridad') : '' }} DE {{ $h->up('inst') }}:**
[p] {{ $h->persona('sol', sinTrato: true, sinEc: true) }}{{ $h->hay('cond') ? ', en calidad de '.$h->t('cond') : '' }}, ante usted, con el debido respeto, me presento y expongo:
@forelse ($h->parrafosQue('pedido') as $t)
[p] {{ $t }}
@empty
[p] Que, [COMPLETAR]
@endforelse
[left] **POR LO EXPUESTO:**
[p] Pido a usted acceder a mi solicitud por ser de justicia.
@if ($h->lineas('anexos'))
[left] **ANEXOS:**
@foreach ($h->lineas('anexos') as $i => $a)
[li] 1-{{ chr(65 + $i) }}. {{ $a }}
@endforeach
@endif
[right] {{ $h->x('ciudad') }}, {{ $h->fecha('fecha') }}.
{{ $h->firma('sol', 'Solicitante') }}
B,
];
