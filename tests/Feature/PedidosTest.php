<?php

use App\Livewire\Pedidos;
use App\Livewire\Vender;
use App\Models\Cliente;
use App\Models\Pedido;
use App\Models\PlantillaUtiles;
use App\Models\StockMovimiento;
use App\Models\Venta;
use App\Services\ErrorNegocio;
use App\Services\Pedidos as ServicioPedidos;
use App\Services\Stock;
use App\Services\Ventas;
use Livewire\Livewire;

beforeEach(fn () => negocioDePrueba());

function pedidoNuevo(array $extra = [], int $adelanto = 0): Pedido
{
    $lap = producto('u_lapicero');
    $anill = producto('anillado');

    return app(ServicioPedidos::class)->guardar(null, array_replace([
        'etapa' => 'proceso', 'cliente' => ['nombre' => 'Rosa Quispe', 'cel' => '987 654 321', 'doc' => '', 'inst' => 'IE 32004'],
        'items' => [['pid' => $anill->id, 'nombre' => 'Anillado', 'cant' => 2, 'precio' => 300], ['pid' => $lap->id, 'nombre' => 'Lapicero', 'cant' => 3, 'precio' => 100]],
        'fecha_entrega' => today()->addDay()->toDateString(),
    ], $extra), usuario('alex'), $adelanto);
}

it('crea un encargo con número correlativo, guarda al cliente y cobra el adelanto como venta', function () {
    $p = pedidoNuevo([], 400);
    $p2 = pedidoNuevo();

    expect($p->numero)->toBe(1)->and($p2->numero)->toBe(2)
        ->and($p->total)->toBe(900)->and($p->pagado())->toBe(400)->and($p->saldo())->toBe(500)
        ->and(Cliente::where('celular', '987654321')->count())->toBe(1)
        ->and(Venta::first()->total)->toBe(400)->and(Venta::first()->pedido_uid)->toBe($p->uid)
        ->and($p->historial()->count())->toBe(2);
});

it('valida el pedido como el sistema anterior', function (array $datos, string $msg) {
    expect(fn () => app(ServicioPedidos::class)->guardar(null, $datos, usuario('alex')))->toThrow(ErrorNegocio::class, $msg);
})->with([
    [['cliente' => ['nombre' => ''], 'detalle' => 'x', 'monto' => 100], 'nombre del cliente'],
    [['cliente' => ['nombre' => 'Ana'], 'detalle' => ''], 'qué hay que hacer'],
    [['cliente' => ['nombre' => 'Ana'], 'detalle' => 'Planos', 'monto' => 0], 'total del trabajo'],
    [['cliente' => ['nombre' => 'Ana', 'doc' => '20123456789'], 'detalle' => 'Planos', 'monto' => 500], 'RUC no es válido'],
]);

it('cotización: aceptó con adelanto, listo, pago a cuenta y entrega cobrando el saldo en la caja', function () {
    $s = app(ServicioPedidos::class);
    $p = pedidoNuevo(['etapa' => 'cotizado']);
    expect($p->etapa)->toBe('cotizado')->and($p->cotizada)->toBeTrue();

    $s->aceptar($p, today()->addDays(2)->toDateString(), '17:00', 300, 'yape', usuario('alex'));
    $p->refresh()->load('items', 'pagos');
    expect($p->etapa)->toBe('proceso')->and($p->saldo())->toBe(600);

    $s->marcarListo($p);
    $s->registrarPago($p->fresh(['items', 'pagos']), 200, 'efectivo', 'Pago a cuenta', usuario('alex'));
    expect(fn () => $s->entregar($p->fresh(['items', 'pagos']), usuario('alex')))->toThrow(ErrorNegocio::class, 'Todavía debe');

    entrarComo('jeremy');
    Livewire::withQueryParams(['pedido' => $p->uid])->test(Vender::class)
        ->assertSet('cobrando', true)->assertSee('Saldo pedido')->call('cobrar', false);
    $p->refresh()->load('items', 'pagos');
    expect($p->etapa)->toBe('entregado')->and($p->saldo())->toBe(0)->and($p->pagos->last()->tipo)->toBe('Saldo');
});

it('no deja cambiar el monto del saldo desde el navegador', function () {
    $p = pedidoNuevo([], 100);
    entrarComo('jeremy');
    $lw = Livewire::withQueryParams(['pedido' => $p->uid])->test(Vender::class);
    $orden = $lw->get('orden');
    $orden[0]['precio'] = 10;
    $lw->set('orden', $orden)->call('cobrar', false)->assertSet('error', 'El monto del pedido cambió. Vacía la caja y vuelve a cobrarlo desde Pedidos.');
    expect($p->fresh()->etapa)->toBe('proceso');
});

it('cobrar una cotización de una vez la deja entregada, con los precios cotizados', function () {
    $p = pedidoNuevo(['etapa' => 'cotizado', 'items' => [['pid' => producto('anillado')->id, 'nombre' => 'Anillado', 'cant' => 1, 'precio' => 999]]]);
    entrarComo('alex');
    Livewire::withQueryParams(['pedido' => $p->uid])->test(Vender::class)->call('cobrar', false);
    $p->refresh();
    $v = Venta::with('items')->first();
    expect($p->etapa)->toBe('entregado')->and($p->directa)->toBeTrue()
        ->and($v->items->first()->precio)->toBe(999)->and($v->items->first()->precio_lista)->toBeNull();
});

it('al entregar salen del stock los útiles, y vuelven si se deshace', function () {
    $lap = producto('u_lapicero');
    app(Stock::class)->fijar($lap->id, 20);
    $this->travel(1)->seconds();
    $s = app(ServicioPedidos::class);
    $p = pedidoNuevo([], 900);
    $s->entregar($p->fresh(['items', 'pagos']), usuario('alex'));
    expect(app(Stock::class)->de($lap->id))->toBe(17.0);
    $s->deshacerEntrega($p->fresh());
    expect(app(Stock::class)->de($lap->id))->toBe(20.0)->and(StockMovimiento::count())->toBe(0);
});

it('anular el pago del saldo devuelve el pedido a listo', function () {
    $p = pedidoNuevo([], 100);
    entrarComo('jeremy');
    Livewire::withQueryParams(['pedido' => $p->uid])->test(Vender::class)->call('cobrar', false);
    $v = Venta::where('pedido_uid', $p->uid)->latest('id')->first();
    app(Ventas::class)->anular($v, usuario('alex'), 'Error');
    $p->refresh()->load('pagos');
    expect($p->etapa)->toBe('listo')->and($p->pagado())->toBe(100);
});

it('renovar una cotización vencida pone la fecha y los precios de hoy', function () {
    $p = pedidoNuevo(['etapa' => 'cotizado', 'fecha' => today()->subDays(20)->toDateString(), 'items' => [['pid' => producto('bn_a3')->id, 'nombre' => 'B/N A3', 'cant' => 1, 'precio' => 999]]]);
    expect($p->etapaVista())->toBe('vencido');
    app(ServicioPedidos::class)->renovar($p);
    $p->refresh()->load('items');
    expect($p->etapaVista())->toBe('cotizado')->and($p->total)->toBe(100);
});

it('guarda una cotización como lista de útiles y la vuelve a cotizar con precios de hoy', function () {
    $p = pedidoNuevo(['etapa' => 'cotizado']);
    $t = app(ServicioPedidos::class)->guardarComoLista($p, '1.er grado');
    producto('u_lapicero')->opciones->first()->update(['precio' => 150]);
    $its = app(ServicioPedidos::class)->itemsDeLista($t->fresh());
    expect(PlantillaUtiles::count())->toBe(1)->and($its[1]['precio'])->toBe(150);

    entrarComo('alex');
    Livewire::test(Pedidos::class)->call('cotizarLista', $t->uid)->assertSet('e.etapa', 'cotizado')->assertSet('e.cliente.inst', 'IE 32004')
        ->set('e.cliente.nombre', 'Papá de Luis')->call('guardar')->assertSet('error', '');
    expect(Pedido::where('etapa', 'cotizado')->count())->toBe(2);
});

it('muestra la lista, la vista de entregas y descarga proforma y orden de trabajo', function () {
    $p = pedidoNuevo(['fecha_entrega' => today()->subDay()->toDateString()]);
    entrarComo('alex');
    $this->get('/pedidos')->assertOk()->assertSee('Rosa Quispe')->assertSee('Atrasado');
    Livewire::test(Pedidos::class)->set('filtro', 'entregas')->assertSee('Atrasados');
    $this->get(route('pedidos.proforma', $p->uid))->assertOk()->assertHeader('content-type', 'application/pdf');
    $this->get(route('pedidos.orden', $p->uid))->assertOk()->assertHeader('content-type', 'application/pdf');
});

it('eliminar un pedido pide autorización a quien no puede borrar', function () {
    $p = pedidoNuevo();
    $j = entrarComo('jeremy');
    $lw = Livewire::test(Pedidos::class)->call('abrir', $p->uid)->call('pedirEliminar', $p->uid);
    expect($lw->get('autz')['permiso'])->toBe('borrar');
    $lw->set('autzPin', '2580')->call('confirmarAutorizacion')->call('eliminar');
    expect(Pedido::count())->toBe(0);
});
