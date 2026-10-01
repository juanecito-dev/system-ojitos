<?php

use App\Livewire\Inventario as PantallaInventario;
use App\Models\Producto;
use App\Models\StockSaldo;
use App\Services\Compras;
use App\Services\Inventario;
use App\Services\Pedidos;
use App\Services\Stock;
use App\Services\Ventas;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

beforeEach(function () {
    $this->travelTo('2026-10-01 09:00:00');
    negocioDePrueba();
});

/** el stock guardado tiene que ser igual al que sale del historial */
function stockCuadra(): void
{
    $st = app(Stock::class);
    $real = array_map(fn ($n) => round($n, 3), $st->calcular());
    expect($st->todos())->toEqualCanonicalizing($real);
}

function venderUno(Producto $p, float $cant, string $u = 'alex')
{
    return app(Ventas::class)->registrar(usuario($u), ['metodo' => 'efectivo',
        'lineas' => [['producto_id' => $p->id, 'producto_uid' => $p->uid, 'nombre' => $p->nombre, 'cantidad' => $cant, 'precio' => 15]]]);
}

it('después de cualquier mezcla de ventas, entradas, conteos, anulaciones, pedidos y compras, el stock guardado cuadra con el historial', function () {
    $u = entrarComo('alex');
    $inv = app(Inventario::class);
    $hoja = $inv->crearInsumo(['nombre' => 'Papel bond A4', 'um' => 'hojas', 'stock' => '1000'], $u);
    $toner = $inv->crearInsumo(['nombre' => 'Tóner', 'um' => 'tóner', 'stock' => '3'], $u);
    $copia = producto('bn_a4');
    $lap = producto('u_lapicero');
    $inv->guardarInsumos($copia, [[$hoja->id, 1], [$toner->id, 1 / 7000]]);
    app(Stock::class)->fijar($lap->id, 50);

    mt_srand(2026);
    $ventas = [];
    for ($i = 0; $i < 60; $i++) {
        $this->travel(mt_rand(1, 90))->seconds();
        match (mt_rand(1, 9)) {
            1, 2, 3 => $ventas[] = venderUno(mt_rand(0, 1) ? $copia : $lap, mt_rand(1, 40)),
            4 => $inv->entrada(mt_rand(0, 1) ? $hoja : $lap, mt_rand(1, 500), 'Llegó', null, $u),
            5 => $inv->contar(mt_rand(0, 1) ? $hoja : $lap, mt_rand(0, 900), 'Error de conteo', $u),
            6 => $ventas ? app(Ventas::class)->anular(array_pop($ventas), $u) : null,
            7 => $inv->guardarInsumos($copia, [[$hoja->id, mt_rand(1, 3)], [$toner->id, 1 / 7000]]),
            8 => (function () use ($lap, $u) {
                $p = app(Pedidos::class)->guardar(null, ['etapa' => 'proceso', 'cliente' => ['nombre' => 'Rosa', 'cel' => '', 'doc' => '', 'inst' => ''],
                    'items' => [['pid' => $lap->id, 'nombre' => 'Lapicero', 'cant' => 3, 'precio' => 100]], 'fecha_entrega' => today()->addDay()->toDateString()], $u, 300);
                app(Pedidos::class)->entregar($p->fresh(['items', 'pagos']), $u);
                if (mt_rand(0, 1)) {
                    app(Pedidos::class)->deshacerEntrega($p->fresh());
                }
            })(),
            9 => (function () use ($lap, $u) {
                $prov = app(Compras::class)->guardarProveedor(null, 'Librería', '', '962506202');
                $c = app(Compras::class)->guardar(null, ['proveedor_id' => $prov->id, 'numero' => 'F'.mt_rand(1, 99999), 'condicion' => 'credito', 'vence' => today()->addDays(30)->toDateString()],
                    [['producto_id' => $lap->id, 'cantidad' => 1, 'unidad' => 'Caja', 'factor' => 12, 'costo_unitario' => 1200]], $u);
                if (mt_rand(0, 1)) {
                    app(Compras::class)->eliminar($c->fresh(), $u);
                }
            })(),
        };
        stockCuadra();
    }
});

it('cambiar la receta de un servicio no cambia lo que ya se gastó', function () {
    $u = entrarComo('alex');
    $inv = app(Inventario::class);
    $hoja = $inv->crearInsumo(['nombre' => 'Papel bond A4', 'um' => 'hojas', 'stock' => '1000'], $u);
    $copia = producto('bn_a4');
    $inv->guardarInsumos($copia, [[$hoja->id, 1]]);
    $this->travel(1)->seconds();
    venderUno($copia, 100);
    expect(app(Stock::class)->de($hoja->id))->toBe(900.0);

    // ahora cada copia gasta 2 hojas (por ejemplo, a doble cara): lo de antes no cambia
    $inv->guardarInsumos($copia, [[$hoja->id, 2]]);
    expect(app(Stock::class)->de($hoja->id))->toBe(900.0);
    venderUno($copia, 10);
    expect(app(Stock::class)->de($hoja->id))->toBe(880.0);
    stockCuadra();
});

it('si se vende más de lo contado, el stock queda en negativo y se ve en rojo', function () {
    entrarComo('alex');
    $lap = producto('u_lapicero');
    app(Stock::class)->fijar($lap->id, 2);
    $this->travel(1)->seconds();
    venderUno($lap, 5);

    expect(app(Stock::class)->de($lap->id))->toBe(-3.0);
    Livewire::test(PantallaInventario::class)->assertSeeHtml('class="qty neg"')->assertSee('Se vendió más de lo contado');
});

it('la revisión diaria encuentra y corrige un stock guardado que no cuadra', function () {
    entrarComo('alex');
    $lap = producto('u_lapicero');
    app(Stock::class)->fijar($lap->id, 20);
    $this->travel(1)->seconds();
    venderUno($lap, 3);
    StockSaldo::where('producto_id', $lap->id)->update(['cantidad' => 99]);   // algo lo dejó mal

    Artisan::call('ojitos:revisar');
    expect(Artisan::output())->toContain('stock de Lapicero')->toContain('decía 99')->toContain('el historial dice 17')
        ->and(app(Stock::class)->de($lap->id))->toBe(17.0);

    Artisan::call('ojitos:revisar');
    expect(Artisan::output())->toContain('cuadran con su historial');
});

it('el stock se lee del saldo guardado, sin recorrer las ventas', function () {
    entrarComo('alex');
    $lap = producto('u_lapicero');
    app(Stock::class)->fijar($lap->id, 20);
    for ($i = 0; $i < 30; $i++) {
        $this->travel(1)->seconds();
        venderUno($lap, 1);
    }
    DB::enableQueryLog();
    expect(app(Stock::class)->de($lap->id))->toBe(-10.0);
    $consultas = collect(DB::getQueryLog())->pluck('query')->join(' ');
    expect($consultas)->not->toContain('venta_items')->toContain('stock_saldos');
});
