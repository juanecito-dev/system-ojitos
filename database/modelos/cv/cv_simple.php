<?php

return [
    'id' => 'cv_simple', 'nombre' => 'Currículum simple', 'icono' => '📄', 'cobro' => 'cv', 'seccion' => 'cv', 'orden' => 50, 'tipo' => 'cv', 'ciudadFecha' => false,
    'desc' => 'Formato clásico y limpio, con datos personales. Ideal para primeros empleos.',
    'nota' => 'Los datos personales (DNI, fecha de nacimiento, estado civil) son comunes en CV peruanos para empleos locales; si el cliente postula a una empresa grande, sugiérele el formato Harvard.',
    'campos' => (require __DIR__.'/_cv.inc')(false),
];
