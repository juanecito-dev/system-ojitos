<?php

return [
    'id' => 'cv_moderno', 'nombre' => 'Currículum moderno', 'icono' => '🎨', 'cobro' => 'cv', 'seccion' => 'cv', 'orden' => 53, 'tipo' => 'cv', 'ciudadFecha' => false,
    'desc' => 'Diseño a dos columnas con foto circular. Cambia el color, la letra y más con un toque.',
    'nota' => 'Después de generarlo podrás elegir el color, la letra, la forma de la foto y de qué lado va la columna. Si el contenido no entra en una hoja, prueba el tamaño «Compacto».',
    'campos' => (require __DIR__.'/_cv.inc')(true),
];
