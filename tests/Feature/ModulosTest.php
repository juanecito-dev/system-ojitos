<?php

use App\Events\VentaAnulada;
use App\Events\VentaRegistrada;
use App\Livewire\Vender;
use App\Models\Cliente;
use App\Models\Documento;
use App\Models\Pedido;
use App\Models\Venta;
use App\Services\Clientes;
use App\Services\Pedidos;
use App\Services\Redaccion;
use App\Services\Ventas;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;

beforeEach(fn () => negocioDePrueba());

it('al registrar y anular una venta se avisa una vez, con sus líneas, para que cada módulo haga lo suyo', function () {
    $vistos = [];
    Event::listen(VentaRegistrada::class, function (VentaRegistrada $e) use (&$vistos) {
        $vistos[] = ['registrada', $e->venta->items->count()];
    });
    Event::listen(VentaAnulada::class, function (VentaAnulada $e) use (&$vistos) {
        $vistos[] = ['anulada', $e->tipo];
        $e->avisos[] = 'Aviso de un módulo.';
    });

    $v = app(Ventas::class)->registrar(usuario('alex'), ['metodo' => 'efectivo',
        'lineas' => [['producto_id' => producto('bn_a4')->id, 'nombre' => 'B/N A4', 'cantidad' => 2, 'precio' => 15], ['producto_id' => producto('u_lapicero')->id, 'nombre' => 'Lapicero', 'cantidad' => 1, 'precio' => 100]]]);
    $aviso = app(Ventas::class)->anular($v, usuario('alex'));

    expect($vistos)->toBe([['registrada', 2], ['anulada', 'anulada']])->and($aviso)->toContain('Aviso de un módulo.');
});

it('si un módulo falla al recibir el aviso, la venta no se guarda', function () {
    Event::listen(VentaRegistrada::class, fn () => throw new RuntimeException('el módulo falló'));

    expect(fn () => app(Ventas::class)->registrar(usuario('alex'), ['metodo' => 'efectivo',
        'lineas' => [['producto_id' => producto('bn_a4')->id, 'nombre' => 'B/N A4', 'cantidad' => 2, 'precio' => 15]]]))->toThrow(RuntimeException::class);
    expect(Venta::conAnuladas()->count())->toBe(0);
});

it('Redacción cobra con el producto que tiene su rol, aunque cambie su código', function () {
    $p = producto('s_contrato');
    expect($p->rol)->toBe('redaccion_pagina');
    $p->update(['uid' => 'contrato-propio', 'nombre' => 'Redacción de contrato']);

    expect(Redaccion::producto('pagina')?->id)->toBe($p->id)
        ->and(Redaccion::producto('ejemplar')?->uid)->toBe('bn_a4');
});

it('solo los productos de Redacción pueden cobrar un documento en la caja', function () {
    entrarComo('alex');
    $doc = Documento::create(['uid' => 'doc-1', 'modelo' => 'solicitud', 'titulo' => 'Solicitud', 'estado' => 'borrador']);
    $orden = [
        ['pid' => producto('s_solicitud')->id, 'nombre' => 'Solicitud', 'det' => '', 'cant' => 1, 'precio' => producto('s_solicitud')->opciones->first()->precio, 'doc' => 'doc-1'],
        ['pid' => producto('u_lapicero')->id, 'nombre' => 'Lapicero', 'det' => '', 'cant' => 1, 'precio' => 100, 'doc' => 'doc-1'],
    ];
    Livewire::test(Vender::class)->set('orden', $orden)->call('abrirCobro')->call('cobrar', false);

    $l = Venta::first()->items;
    expect($l[0]->origen_tipo)->toBe('documento')->and($l[0]->origen_uid)->toBe('doc-1')
        ->and($l[1]->origen_tipo)->toBeNull()->and($doc->fresh()->estado)->toBe('cobrado');
});

it('al unir dos clientes, sus pedidos y documentos pasan al que queda', function () {
    $a = Cliente::create(['uid' => 'a', 'nombre' => 'Rosa Díaz', 'celular' => '987654321']);
    $b = Cliente::create(['uid' => 'b', 'nombre' => 'Rosa D.']);
    $p = app(Pedidos::class)->guardar(null, ['etapa' => 'cotizado', 'cliente' => ['nombre' => 'Rosa D.', 'cel' => '', 'doc' => '', 'inst' => ''],
        'items' => [['pid' => producto('anillado')->id, 'nombre' => 'Anillado', 'cant' => 1, 'precio' => 300]], 'fecha_entrega' => today()->addDay()->toDateString()], usuario('alex'));
    Pedido::whereKey($p->id)->update(['cliente_id' => $b->id]);
    Documento::create(['uid' => 'd1', 'modelo' => 'solicitud', 'titulo' => 'Solicitud', 'estado' => 'borrador', 'cliente_id' => $b->id]);

    app(Clientes::class)->unir($a, $b);
    expect(Pedido::where('cliente_id', $a->id)->count())->toBe(1)->and(Documento::where('cliente_id', $a->id)->count())->toBe(1);
});
