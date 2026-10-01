<?php

/*
 * El núcleo (venta, caja, clientes, comprobantes) no conoce a los módulos de un rubro.
 * Cuando un módulo necesita enterarse de algo, escucha un aviso (App\Events) y actualiza lo suyo.
 *
 * Una regla por clase: expect([varias])->not->toUse(...) no revisa nada en esta versión de Pest.
 */
$modulos = ['App\Models\Pedido', 'App\Models\Documento', 'App\Services\Pedidos', 'App\Services\Redaccion', 'App\Redaccion', 'App\Services\Contadores'];

foreach (['App\Services\Ventas', 'App\Services\CajaDia', 'App\Services\CuadreCaja', 'App\Services\Clientes', 'App\Services\Comprobantes'] as $nucleo) {
    arch($nucleo.' no nombra a Pedidos ni a Redacción')->expect($nucleo)->not->toUse($modulos);
}

arch('los avisos del núcleo no dependen de ningún módulo')
    ->expect('App\Events')
    ->toOnlyUse(['App\Models\Venta', 'App\Models\Usuario', 'App\Models\Cliente']);
