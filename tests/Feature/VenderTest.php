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

it('un precio bajado desde el navegador sin «lista» igual cuenta para el tope del rol', function () {
    entrarComo('jeremy');   // tope S/ 2
    // 1000 copias B/N a S/ 0.01 (el precio es 0.15): S/ 140 de rebaja, aunque el navegador no mande el precio normal
    $c = Livewire::test(Vender::class)->set('orden', [['pid' => producto('bn_a4')->id, 'nombre' => 'B/N A4', 'det' => '', 'cant' => 1000, 'precio' => 1]])
        ->call('abrirCobro')->call('cobrar', false);

    expect($c->get('autz'))->not->toBeNull()->and($c->get('autz')['soloAdmin'])->toBeTrue()
        ->and(Venta::count())->toBe(0);
});

it('un precio bajado sin «lista» pide autorización a quien no puede dar descuentos', function () {
    $j = entrarComo('jeremy');
    $j->rol->sincronizarPermisos(['vender', 'ventas', 'caja']);
    $c = Livewire::test(Vender::class)->set('orden', [['pid' => producto('bn_a4')->id, 'nombre' => 'B/N A4', 'det' => '', 'cant' => 10, 'precio' => 5]])
        ->call('abrirCobro')->call('cobrar', false);

    expect($c->get('autz')['permiso'])->toBe('descuentos')->and(Venta::count())->toBe(0);
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

it('lo ya autorizado deja de valer si cambia el cobro', function () {
    $j = entrarComo('jeremy');
    $j->rol->sincronizarPermisos(['vender', 'ventas', 'caja']);   // sin fiar ni descuentos
    $cli = Cliente::create(['uid' => 'c1', 'nombre' => 'Rosa']);
    $c = Livewire::test(Vender::class)->set('orden', pedido([['s_empastado', 1, 2000]]))->call('abrirCobro')
        ->call('elegirCliente', $cli->id)->call('metodo', 'fiado')->call('descuento', 100)->call('cobrar', false);
    $c->set('autzPin', '2580')->call('confirmarAutorizacion');   // autoriza el fiado de S/ 19
    expect($c->get('autz')['permiso'])->toBe('descuentos');

    // en vez de poner el PIN del descuento, cambia el descuento: lo autorizado ya no vale
    $c->call('cancelarAutorizacion')->call('descuento', 1500)->call('cobrar', false);
    expect($c->get('autz')['permiso'])->toBe('fiar')->and(Venta::count())->toBe(0);

    // y si cambia el descuento con la ventana abierta, el PIN tampoco vale
    $c->set('pay.desc', 1900)->set('autzPin', '2580')->call('confirmarAutorizacion');
    expect(Venta::count())->toBe(0)->and($c->get('autz'))->toBeNull();
});

it('si se corta la conexión y se vuelve a cobrar, no se duplica la venta', function () {
    entrarComo('jeremy');
    $llave = 'v-0123456789abcdef01234567';
    Livewire::test(Vender::class)->set('orden', pedido([['bn_a4', 10]]))->call('abrirCobro', $llave)->call('cobrar', false);
    expect(Venta::count())->toBe(1)->and(Venta::first()->uid)->toBe($llave);

    // la respuesta no llegó: el cajero recarga, el pedido sigue en el navegador con la misma llave y vuelve a cobrar
    Livewire::test(Vender::class)->set('orden', pedido([['bn_a4', 10]]))->call('abrirCobro', $llave)
        ->assertSet('cobrando', false)->assertSet('orden', [])->assertDispatched('venta-registrada');
    expect(Venta::count())->toBe(1);
});

it('el mismo cobro enviado dos veces se guarda una sola vez', function () {
    entrarComo('jeremy');
    $llave = 'v-fedcba9876543210fedcba98';
    $a = Livewire::test(Vender::class)->set('orden', pedido([['bn_a4', 10]]))->call('abrirCobro', $llave);
    $b = Livewire::test(Vender::class)->set('orden', pedido([['bn_a4', 10]]))->call('abrirCobro', $llave);
    $a->call('cobrar', false);
    $b->call('cobrar', true)->assertSet('orden', [])->assertSet('ticketUid', $llave);
    expect(Venta::count())->toBe(1);
});

it('una llave de cobro con caracteres raros se ignora', function () {
    entrarComo('jeremy');
    Livewire::test(Vender::class)->set('orden', pedido([['bn_a4', 1]]))->call('abrirCobro', "x' OR 1=1")->call('cobrar', false);
    expect(Venta::count())->toBe(1)->and(Venta::first()->uid)->not->toContain("'");
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
