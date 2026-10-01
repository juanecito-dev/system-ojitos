<?php

use App\Livewire\Caja;
use App\Livewire\Compras;
use App\Livewire\Inicio;
use App\Livewire\Inventario;
use App\Models\CajaMovimiento;
use App\Models\Compra;
use App\Models\Producto;
use App\Models\Proveedor;
use App\Services\Compras as Srv;
use App\Services\ErrorNegocio;
use App\Services\Inventario as Inv;
use App\Services\Stock;
use App\Support\Avisos;
use Livewire\Livewire;

beforeEach(fn () => negocioDePrueba());

function unUtil(): Producto
{
    return Producto::with('opciones')->where('rapido', true)->whereHas('opciones')->orderBy('orden')->firstOrFail();
}

function proveedor(string $nombre = 'Librería Central'): Proveedor
{
    return app(Srv::class)->guardarProveedor(null, $nombre, '', '962506202');
}

function comprar(Producto $p, float $cajas, float $factor, float $costoCaja, array $d = [], ?array $pago = null): Compra
{
    return app(Srv::class)->guardar(null, $d + ['proveedor_id' => proveedor()->id, 'numero' => 'F001-'.random_int(1, 99999), 'condicion' => 'credito', 'vence' => today()->addDays(30)->toDateString()],
        [['producto_id' => $p->id, 'cantidad' => $cajas, 'unidad' => 'Caja', 'factor' => $factor, 'costo_unitario' => $costoCaja]], usuario('alex'), $pago);
}

it('una compra a crédito suma al stock por caja y recalcula el costo promedio', function () {
    entrarComo('alex');
    $p = unUtil();
    $p->update(['costo' => null]);
    app(Inv::class)->contar($p, 10, null, usuario('alex'));
    $p->update(['costo' => 50]);

    $c = comprar($p, 2, 12, 1200);   // 2 cajas de 12 a S/ 12 la caja = S/ 1 cada una
    expect($c->total)->toBe(2400)->and($c->saldo())->toBe(2400)
        ->and(app(Stock::class)->de($p->id))->toBe(34.0)
        ->and(round($p->fresh()->costo, 4))->toBe(round((10 * 50 + 24 * 100) / 34, 4));
});

it('al contado en efectivo sale de la caja, y eliminar la compra deshace todo', function () {
    entrarComo('alex');
    $p = unUtil();
    $p->update(['costo' => 40]);
    $c = comprar($p, 1, 10, 500, ['condicion' => 'contado'], ['efectivo', true]);
    expect($c->saldo())->toBe(0)->and(CajaMovimiento::first()->concepto)->toBe('Pago a proveedor')
        ->and(CajaMovimiento::first()->monto)->toBe(500)->and(app(Stock::class)->de($p->id))->toBe(10.0);

    app(Srv::class)->eliminar($c->fresh(['pagos', 'proveedor']), usuario('alex'));
    expect(Compra::count())->toBe(0)->and(CajaMovimiento::count())->toBe(0)
        ->and(app(Stock::class)->de($p->id))->toBe(0.0)->and($p->fresh()->costo)->toBe(40.0);
});

it('corregir una compra rehace el stock y no deja un total menor a lo pagado', function () {
    entrarComo('alex');
    $p = unUtil();
    $c = comprar($p, 2, 12, 1200);
    app(Srv::class)->pagar($c, 1000, 'yape', false, usuario('alex'));
    expect($c->fresh('pagos')->saldo())->toBe(1400)->and(CajaMovimiento::count())->toBe(0);

    $linea = [['producto_id' => $p->id, 'cantidad' => 1, 'unidad' => 'Caja', 'factor' => 12, 'costo_unitario' => 1200]];
    app(Srv::class)->guardar($c->fresh(), ['proveedor_id' => $c->proveedor_id, 'numero' => $c->numero, 'condicion' => 'credito'], $linea, usuario('alex'));
    expect(app(Stock::class)->de($p->id))->toBe(12.0)->and($c->fresh()->total)->toBe(1200);

    $barato = [['producto_id' => $p->id, 'cantidad' => 1, 'unidad' => 'Caja', 'factor' => 12, 'costo_unitario' => 500]];
    expect(fn () => app(Srv::class)->guardar($c->fresh(), ['proveedor_id' => $c->proveedor_id, 'condicion' => 'credito'], $barato, usuario('alex')))
        ->toThrow(ErrorNegocio::class, 'lo ya pagado');
});

it('no deja pagar más que el saldo ni repetir el número de factura', function () {
    entrarComo('alex');
    $c = comprar(unUtil(), 1, 1, 300, ['numero' => 'F001-77']);
    expect(fn () => app(Srv::class)->pagar($c, 301, 'efectivo', true, usuario('alex')))->toThrow(ErrorNegocio::class);
    expect(fn () => comprar(unUtil(), 1, 1, 300, ['numero' => 'f001-77', 'proveedor_id' => $c->proveedor_id]))->toThrow(ErrorNegocio::class, 'Ya registraste');
});

it('borrar en Caja el pago a un proveedor devuelve la deuda', function () {
    entrarComo('alex');
    $c = comprar(unUtil(), 1, 1, 300);
    app(Srv::class)->pagar($c, 300, 'efectivo', true, usuario('alex'));
    expect($c->fresh('pagos')->saldo())->toBe(0);
    Livewire::test(Caja::class)->call('borrarMovimiento', CajaMovimiento::first()->id);
    expect($c->fresh('pagos')->saldo())->toBe(300);
});

it('la pantalla registra una compra con un proveedor nuevo', function () {
    entrarComo('alex');
    $p = unUtil();
    Livewire::test(Compras::class)->call('abrirCompra')->set('c.prov', '__new')->set('c.pn', 'Tai Loy')->set('c.numero', 'B001-5')
        ->call('agregarProducto', $p->id)->set('lineas.0.cant', '3')->set('lineas.0.costo', '0.50')
        ->set('c.cond', 'contado')->set('c.met', 'efectivo')->call('guardarCompra')->assertSet('editando', false);
    $c = Compra::with('proveedor', 'items')->first();
    expect($c->proveedor->nombre)->toBe('Tai Loy')->and($c->total)->toBe(150)->and($c->saldo())->toBe(0)
        ->and(CajaMovimiento::count())->toBe(1)->and(app(Stock::class)->de($p->id))->toBe(3.0);
});

it('por reponer sugiere cuánto pedir y arma la compra del último proveedor', function () {
    entrarComo('alex');
    $p = unUtil();
    $c = comprar($p, 1, 12, 1200);
    app(Inv::class)->contar($p->fresh(), 2, 'Uso interno', usuario('alex'));

    $s = app(Srv::class)->sugerencias()->firstWhere('p.id', $p->id);
    expect($s['prov_id'])->toBe($c->proveedor_id)->and($s['cant'])->toBe(4)->and($s['costoU'])->toBe(100.0);   // mínimo 3 × 2 = 6, faltan 4

    Livewire::test(Compras::class)->set('filtro', 'reponer')->assertSee('Llegó: registrar compra')
        ->call('abrirCompra', null, (string) $c->proveedor_id)->assertSet('lineas.0.pid', $p->id)->assertSet('lineas.0.cant', '4');
});

it('guarda el historial de precios y avisa del proveedor más barato', function () {
    entrarComo('alex');
    $p = unUtil();
    comprar($p, 1, 10, 1000, ['fecha' => today()->subDays(5)->toDateString()]);
    comprar($p, 1, 10, 800, ['proveedor_id' => proveedor('Tai Loy')->id, 'fecha' => today()->subDays(3)->toDateString()]);
    comprar($p, 1, 10, 1200, ['fecha' => today()->toDateString()]);

    $h = app(Srv::class)->costosDe($p->id);
    expect($h)->toHaveCount(3)->and($h[0]['c'])->toBe(120.0)
        ->and(app(Srv::class)->infoCosto($p->id))->toContain('Último costo S/ 1.20')->toContain('más barato S/ 0.80 con Tai Loy');
    Livewire::test(Inventario::class)->call('abrirFicha', $p->id)->assertSee('Historial de precios')->assertSee('Tai Loy');
});

it('avisa en Inicio de las facturas que vencen en 3 días y en el menú', function () {
    entrarComo('alex');
    comprar(unUtil(), 1, 1, 4500, ['vence' => today()->addDays(2)->toDateString()]);
    comprar(unUtil(), 1, 1, 1000, ['vence' => today()->addDays(20)->toDateString()]);
    Livewire::test(Inicio::class)->assertSee('Facturas de proveedores')->assertSee('S/ 45.00')->assertSee('vence en 3 días');
    expect(app(Avisos::class)->menu()['compras'])->toBe(1);
});

it('descarga el registro de compras del mes', function () {
    entrarComo('alex');
    comprar(unUtil(), 2, 12, 1200, ['numero' => 'F001-123']);
    $this->get(route('compras.csv', ['mes' => today()->format('Y-m')]))->assertOk()->assertSee('F001-123')->assertSee('24.00');
});
