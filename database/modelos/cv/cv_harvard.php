<?php

return [
    'id' => 'cv_harvard', 'nombre' => 'Currículum Harvard', 'icono' => '🎓', 'cobro' => 'cv', 'seccion' => 'cv', 'orden' => 52, 'tipo' => 'cv', 'ciudadFecha' => false,
    'desc' => 'Formato profesional sin foto ni datos personales, preferido por empresas y universidades.',
    'nota' => 'El formato Harvard no lleva foto, DNI, edad ni estado civil: solo contacto, perfil, educación, experiencia, certificaciones y habilidades, con fechas a la derecha.',
    'campos' => (require __DIR__.'/_cv.inc')(false),
];
