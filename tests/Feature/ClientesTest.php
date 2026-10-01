<?php

use App\Livewire\Clientes;
use App\Livewire\Vender;
use App\Models\CajaMovimiento;
use App\Models\Cliente;
use App\Models\ClienteMovimiento;
use App\Models\Venta;
use App\Services\Clientes as ServicioClientes;
use App\Services\Ventas;
use Livewire\Livewire;

beforeEach(fn () => negocioDePrueba());

function cli(string $nombre = 'Rosa Díaz', ?string $cel = '987654321', array $extra = []): Cliente
{
    return Cliente::create(['uid' => 'c'.mt_rand(), 'nombre' => $nombre, 'celular' => $cel] + $extra);
}

function fiar(Cliente $c, int $precio, int $cant = 1): Venta
{
    return app(Ventas::class)->registrar(usuario('jeremy'), ['lineas' => [['producto_id' => producto('s_empastado')->id, 'nombre' => 'Empastado', 'cantidad' => $cant, 'precio' => $precio]], 'metodo' => 'fiado', 'cliente_id' => $c->id]);
}

it('muestra los clientes que deben', function () {
    $c = cli();
    fiar($c, 2000);
    entrarComo('alex');
    $this->get('/clientes')->assertOk()->assertSee('Rosa Díaz')->assertSee('S/ 20.00');
});

it('registra un pago de fiado y lo pone en la caja', function () {
    $c = cli();
    fiar($c, 2000);
    entrarComo('jeremy');
    Livewire::test(Clientes::class)->call('abrir', $c->uid)->set('pago', '15')->set('metodo', 'yape')->call('registrarPago');

    expect($c->saldo())->toBe(500)
        ->and(CajaMovimiento::first()->concepto)->toBe('Pago de fiado')
        ->and(CajaMovimiento::first()->metodo)->toBe('yape');
});

it('borrar un pago también lo quita de la caja, y pide permiso', function () {
    $c = cli();
    app(ServicioClientes::class)->anotarDeuda($c, 500, usuario('alex'));
    $m = app(ServicioClientes::class)->registrarPago($c, 500, 'efectivo', usuario('alex'));
    $j = entrarComo('jeremy');
    $j->rol->sincronizarPermisos(['clientes']);
    $lw = Livewire::test(Clientes::class)->call('abrir', $c->uid)->call('pedirBorrar', $m->id);
    expect($lw->get('autz')['permiso'])->toBe('borrar');
    $lw->set('autzPin', '2580')->call('confirmarAutorizacion')->call('borrar');
    expect(ClienteMovimiento::count())->toBe(1)->and(CajaMovimiento::count())->toBe(0)->and($c->saldo())->toBe(500);
});

it('anota una deuda anterior con autorización si no puede fiar', function () {
    $c = cli();
    $j = entrarComo('jeremy');
    $j->rol->sincronizarPermisos(['clientes']);
    Livewire::test(Clientes::class)->call('abrir', $c->uid)->call('pedirDeuda')->set('autzPin', '2580')->call('confirmarAutorizacion')
        ->assertSet('anotando', true)->set('deuda', '30')->call('anotarDeuda');
    expect($c->saldo())->toBe(3000);
});

it('sabe desde cuándo debe cada cliente', function () {
    $c = cli();
    $this->travelTo(now()->subDays(40));
    fiar($c, 1000);
    $this->travelBack();
    app(ServicioClientes::class)->registrarPago($c, 400, 'efectivo', usuario('alex'));
    fiar($c, 500);
    $desde = app(ServicioClientes::class)->deudaDesde($c);
    expect($desde->diffInDays(now(), true))->toBeGreaterThan(39);   // la deuda sigue viva desde hace 40 días

    app(ServicioClientes::class)->registrarPago($c, 1100, 'efectivo', usuario('alex'));
    expect(app(ServicioClientes::class)->deudaDesde($c))->toBeNull();
});

it('valida la ficha y no deja repetir celular ni DNI', function () {
    cli('Rosa', '987654321', ['documento' => '45678912']);
    entrarComo('alex');
    Livewire::test(Clientes::class)->call('nuevo')->set('f.cel', '987 654 321')->set('f.nombre', 'Otra')->call('guardarFicha')
        ->assertSet('error', 'Ese celular ya es de Rosa. Si es la misma persona, usa «Unir con otro».')
        ->set('f.cel', '999888777')->set('f.doc', '45678912')->call('guardarFicha')->assertSet('error', 'Ese documento ya es de Rosa. Si es la misma persona, usa «Unir con otro».')
        ->set('f.doc', '20123456789')->call('guardarFicha')->assertSet('error', 'Ese RUC no es válido: revisa los 11 dígitos.')
        ->set('f.doc', '')->set('f.limite', '50')->call('guardarFicha')->assertSet('error', '');
    expect(Cliente::where('celular', '999888777')->first()->limite_fiado)->toBe(5000);
});

it('une dos registros del mismo cliente', function () {
    $a = cli('Rosa Díaz', '987654321');
    $b = cli('Rosa D.', null, ['documento' => '45678912']);
    fiar($a, 1000);
    fiar($b, 500);
    app(ServicioClientes::class)->unir($a, $b);

    expect(Cliente::count())->toBe(1)->and($a->fresh()->saldo())->toBe(1500)->and($a->fresh()->documento)->toBe('45678912')
        ->and($a->fresh()->visitas)->toBe(2)->and(Venta::where('cliente_id', $a->id)->count())->toBe(2)
        ->and($a->fresh()->alias)->toContain($b->uid);
});

it('fiar por encima del límite del cliente pide el PIN de un administrador', function () {
    $c = cli('Rosa', '987654321', ['limite_fiado' => 3000]);
    fiar($c, 2000);
    entrarComo('jeremy');
    $p = producto('s_empastado');
    $lw = Livewire::test(Vender::class)->set('orden', [['pid' => $p->id, 'nombre' => $p->nombre, 'det' => '', 'cant' => 1, 'precio' => 1500]])
        ->call('abrirCobro')->call('elegirCliente', $c->id)->call('metodo', 'fiado')->call('cobrar', false);
    expect($lw->get('autz')['soloAdmin'])->toBeTrue()->and(Venta::count())->toBe(1);
    $lw->set('autzPin', '2580')->call('confirmarAutorizacion');
    expect(Venta::count())->toBe(2)->and($c->saldo())->toBe(3500);
});

it('descarga el estado de cuenta en PDF', function () {
    $c = cli();
    fiar($c, 2000);
    entrarComo('alex');
    $r = $this->get(route('clientes.estado', $c->uid));
    $r->assertOk();
    expect($r->headers->get('content-type'))->toBe('application/pdf');
});
