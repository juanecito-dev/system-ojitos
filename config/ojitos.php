<?php

return [

    /*
    | Instalación de un solo negocio (la PC de Ojitos): la pantalla de entrada va directo a ese negocio,
    | sin pedir su código. En la plataforma con varios negocios debe ser false: cada equipo entra una vez
    | con /n/CODIGO o escribiendo el código del negocio.
    */
    'negocio_unico' => (bool) env('OJITOS_NEGOCIO_UNICO', false),

    /* Con true, «php artisan db:seed» crea el negocio de prueba (alex / 2580 y jeremy / 1470). Nunca en el servidor real. */
    'demo' => (bool) env('OJITOS_DEMO', false),

];
