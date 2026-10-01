<?php

return [
    'id' => 'cv_medio', 'nombre' => 'Currículum con foto', 'icono' => '🧑‍💼', 'cobro' => 'cv', 'seccion' => 'cv', 'orden' => 51, 'tipo' => 'cv', 'ciudadFecha' => false,
    'desc' => 'Diseño con color, foto y datos personales.',
    'nota' => 'La foto sale en la pantalla y en el PDF. En el Word no se incluye: si el cliente lo quiere editable con foto, agrégala en Word con Insertar › Imagen.',
    'campos' => (require __DIR__.'/_cv.inc')(true),
];
