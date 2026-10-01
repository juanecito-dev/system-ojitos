<?php

return [
    'id' => 'renuncia', 'nombre' => 'Carta de renuncia', 'icono' => '💼', 'cobro' => 'doc', 'seccion' => 'laboral', 'orden' => 40,
    'desc' => 'Renuncia voluntaria con pedido de exoneración del preaviso y de liquidación.',
    'nota' => 'Por ley, el trabajador debe avisar su renuncia con 30 días de anticipación; el empleador puede exonerarlo de ese plazo (art. 18 del TUO del D. Leg. 728). Se entregan dos copias y se pide el cargo de recepción.',
    'campos' => [
        ['t' => 'persona', 'id' => 'tra', 'label' => 'Trabajador', 'sinEc' => true, 'sinDom' => true],
        ['id' => 'empresa', 'label' => 'Empresa o institución', 'req' => true, 'full' => true],
        ['id' => 'ruc', 'label' => 'RUC (opcional)'],
        ['id' => 'dest', 'label' => 'Dirigida a (nombre)', 'ph' => 'Opcional'],
        ['id' => 'destCargo', 'label' => 'Cargo del destinatario', 'def' => 'Gerente General', 'frases' => ['Gerente General', 'Director(a)', 'Jefe(a) de Recursos Humanos', 'Administrador(a)']],
        ['id' => 'cargo', 'label' => 'Cargo del trabajador', 'req' => true],
        ['id' => 'area', 'label' => 'Área (opcional)'],
        ['id' => 'ultimo', 'label' => 'Último día de trabajo', 'type' => 'date', 'req' => true],
        ['id' => 'motivo', 'label' => 'Motivo', 'type' => 'select', 'def' => 'motivos personales',
            'opts' => ['motivos personales' => 'Personales', 'motivos familiares' => 'Familiares', 'motivos de salud' => 'De salud', 'motivos de estudio' => 'De estudio', 'haber aceptado una nueva propuesta laboral' => 'Nueva propuesta laboral']],
        ['id' => 'exo', 'label' => 'Pedir exoneración de los 30 días de preaviso', 'type' => 'check', 'def' => true, 'full' => true],
        ['id' => 'gracias', 'label' => 'Incluir agradecimiento', 'type' => 'check', 'def' => true, 'full' => true],
    ],
    'plantilla' => <<<'B'
[right] {{ $h->x('ciudad') }}, {{ $h->fecha('fecha') }}.
[left] Señor(a):
@if ($h->hay('dest'))
[left] **{{ $h->up('dest') }}**
@endif
[left] {{ $h->x('destCargo') }}
[left] **{{ $h->up('empresa') }}**{{ $h->hay('ruc') ? ' (RUC '.$h->v('ruc').')' : '' }}
[left] Presente.-
[left] **Asunto: Renuncia voluntaria**
[left] De mi consideración:
[p] Por medio de la presente, yo, **{{ $h->up('tra_nombre') }}**, identificad{{ $h->o('tra') }} con DNI N° {{ $h->x('tra_dni') }}, quien me desempeño en el cargo de {{ $h->x('cargo') }}{{ $h->hay('area') ? ' del área de '.$h->v('area') : '' }}, presento mi **RENUNCIA VOLUNTARIA** e irrevocable al puesto que vengo ocupando, por {{ $h->x('motivo') }}, siendo mi último día de labores el {{ $h->fecha('ultimo') }}.
[p] {{ $h->on('exo') ? 'En tal sentido, de conformidad con el artículo 18 del Texto Único Ordenado del Decreto Legislativo N° 728, Ley de Productividad y Competitividad Laboral, aprobado por Decreto Supremo N° 003-97-TR, solicito se me exonere del plazo de preaviso de treinta (30) días naturales.' : 'Cumplo así con el preaviso de treinta (30) días naturales establecido en el artículo 18 del Texto Único Ordenado del Decreto Legislativo N° 728, Ley de Productividad y Competitividad Laboral, aprobado por Decreto Supremo N° 003-97-TR.' }}
[p] Asimismo, solicito que se efectúe el pago de mi liquidación de beneficios sociales y la entrega de mi certificado de trabajo, conforme a ley.
@if ($h->on('gracias'))
[p] Agradezco la confianza y las oportunidades brindadas durante el tiempo que formé parte de la institución.
@endif
[left] Atentamente,
{{ \App\Redaccion\Ayudas::firmaTxt($h->up('tra_nombre'), $h->x('tra_dni'), $h->x('cargo')) }}
B,
];
