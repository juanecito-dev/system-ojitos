<?php

use App\Livewire\Vender;
use App\Models\Actividad;
use App\Models\Cliente;
use App\Models\Venta;
use App\Models\VentaAnulada;
use Livewire\Livewire;

beforeEach(function () {
    negocioDePrueba();
    usuario('jeremy')->rol->update(['desc_max' => 200]);   // Jeremy: descuento de hasta S/ 2 sin autorización
});

function pedido(array $lineas): array
{
    return array_map(fn ($l) => ['pid' => producto($l[0])->id, 'nombre' => producto($l[0])->nombre, 'det' => '', 'cant' => $l[1], 'precio' => $l[2] ?? producto($l[0])->opciones->first()->precio] + ($l[3] ?? []), $lineas);
}

it('abre el punto de venta', function () {
    entrarComo('jeremy');
    $this->get('/vender')->assertOk()->assertSee('Buscar: lapicero');
});

it('cobra el pedido armado en el navegador', function () {
    entrarComo('jeremy');
    Livewire::test(Vender::class)->set('orden', pedido([['bn_a4', 10], ['u_lapicero', 2]]))
        ->call('abrirCobro')->assertSet('cobrando', true)->assertSee('Cobrar S/ 3.50')
        ->call('recibido', 500)->call('cobrar', false)
        ->assertSet('cobrando', false)->assertSet('orden', [])->assertDispatched('toast');

    $v = Venta::first();
    expect($v->total)->toBe(350)->and($v->pago)->toBe(500)->and($v->vendedor)->toBe('Jeremy');
});

it('no se puede bajar un precio desde el navegador sin que cuente como descuento', function () {
    entrarComo('alex');
    // alguien manda B/N A4 a 0.05 (el precio es 0.15): el servidor lo toma como precio cambiado
    Livewire::test(Vender::class)->set('orden', pedido([['bn_a4', 10, 5]]))->call('abrirCobro')->call('cobrar', false);

    $l = Venta::with('items')->first()->items->first();
    expect($l->precio)->toBe(5)->and($l->precio_lista)->toBe(15);
});

it('un descuento sobre el tope del rol pide el PIN de un administrador', function () {
    entrarComo('jeremy');
    $c = Livewire::test(Vender::class)->set('orden', pedido([['s_empastado', 1, 2000]]))->call('abrirCobro')
        ->call('descuento', 500)->call('cobrar', false);

    expect($c->get('autz'))->not->toBeNull()->and($c->get('autz')['soloAdmin'])->toBeTrue();
    expect(Venta::count())->toBe(0);

    // PIN equivocado: no pasa
    $c->set('autzPin', '0000')->call('confirmarAutorizacion')->assertSet('autzError', 'PIN incorrecto');
    expect(Venta::count())->toBe(0);

    $c->set('autzPin', '2580')->call('confirmarAutorizacion');
    expect(Venta::count())->toBe(1)->and(Venta::first()->descuento)->toBe(500)
        ->and(Actividad::where('tipo', 'autoriza')->count())->toBe(1);
});

it('un descuento dentro del tope no pide autorización', function () {
    entrarComo('jeremy');
    Livewire::test(Vender::class)->set('orden', pedido([['s_empastado', 1, 2000]]))->call('abrirCobro')
        ->call('descuento', 100)->call('cobrar', false)->assertSet('autz', null);
    expect(Venta::first()->descuento)->toBe(100);
});

it('fiar sin permiso pide autorización y recuerda lo ya autorizado', function () {
    $j = entrarComo('jeremy');
    $j->rol->sincronizarPermisos(['vender', 'ventas', 'caja']);   // sin fiar ni descuentos
    $cli = Cliente::create(['uid' => 'c1', 'nombre' => 'Rosa']);
    $c = Livewire::test(Vender::class)->set('orden', pedido([['s_empastado', 1, 2000]]))->call('abrirCobro')
        ->call('elegirCliente', $cli->id)->call('metodo', 'fiado')->call('descuento', 100)->call('cobrar', false);
    expect($c->get('autz')['permiso'])->toBe('fiar');
    $c->set('autzPin', '2580')->call('confirmarAutorizacion');
    expect($c->get('autz')['permiso'])->toBe('descuentos');   // ahora el descuento
    $c->set('autzPin', '2580')->call('confirmarAutorizacion');
    expect(Venta::count())->toBe(1)->and(Venta::first()->metodo)->toBe('fiado');
});

it('deshacer una venta la anula y devuelve el pedido a la caja', function () {
    entrarComo('jeremy');
    $c = Livewire::test(Vender::class)->set('orden', pedido([['bn_a4', 10]]))->call('abrirCobro')->call('cobrar', false);
    $uid = Venta::first()->uid;
    $c->call('deshacerVenta', $uid);
    expect(Venta::count())->toBe(0)->and($c->get('orden')[0]['cant'])->toBe(10)
        ->and(VentaAnulada::first()->tipo)->toBe('deshecha');
});

it('registra un cliente nuevo por su celular', function () {
    entrarComo('jeremy');
    Livewire::test(Vender::class)->set('orden', pedido([['bn_a4', 1]]))->call('abrirCobro')
        ->set('clienteQ', '987 654 321')->call('registrarCliente');
    expect(Cliente::first()->nombre)->toBe('Cliente 4321')->and(Cliente::first()->celular)->toBe('987654321');
});

it('descarga el ticket en PDF', function () {
    entrarComo('jeremy');
    Livewire::test(Vender::class)->set('orden', pedido([['bn_a4', 10]]))->call('abrirCobro')->call('cobrar', true);
    $v = Venta::first();
    $r = $this->get(route('ticket.pdf', $v->uid));
    $r->assertOk();
    expect($r->headers->get('content-type'))->toBe('application/pdf');
    $this->getJson(route('ticket.filas', $v->uid))->assertOk()->assertJsonFragment(['t' => 'NOTA DE VENTA']);
});
