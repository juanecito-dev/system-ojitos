<?php

use App\Livewire\Inventario;
use App\Livewire\InventarioToma;
use App\Models\Producto;
use App\Models\StockMovimiento;
use App\Models\TomaConteo;
use App\Services\ErrorNegocio;
use App\Services\Inventario as Inv;
use App\Services\Stock;
use App\Services\Ventas;
use Livewire\Livewire;

beforeEach(fn () => negocioDePrueba());

function util(): Producto
{
    return Producto::with('opciones')->where('rapido', true)->whereHas('opciones')->orderBy('orden')->firstOrFail();
}

function venderProducto(Producto $p, float $cant): void
{
    app(Ventas::class)->registrar(usuario('jeremy'), ['lineas' => [['producto_id' => $p->id, 'producto_uid' => $p->uid, 'nombre' => $p->nombre, 'cantidad' => $cant, 'precio' => $p->opciones->first()->precio]], 'metodo' => 'efectivo']);
}

it('una entrada suma al stock y recalcula el costo promedio', function () {
    $u = entrarComo('alex');
    $p = util();
    $p->update(['costo' => null]);
    $inv = app(Inv::class);

    expect($inv->entrada($p, 10, 'Librería Central', 100, $u))->toBe(10.0);
    expect($inv->entrada($p->fresh(), 10, null, 200, $u))->toBe(20.0)
        ->and($p->fresh()->costo)->toBe(150.0);

    $this->travel(1)->seconds();
    venderProducto($p, 3);
    expect(app(Stock::class)->de($p->id))->toBe(17.0);
});

it('contar con diferencia pide el motivo y los faltantes son pérdida del mes', function () {
    $u = entrarComo('alex');
    $p = util();
    $p->update(['costo' => 50]);
    $inv = app(Inv::class);
    $inv->contar($p, 20, null, $u);   // primera vez: empieza a controlar

    expect(fn () => $inv->contar($p, 18, null, $u))->toThrow(ErrorNegocio::class);
    $inv->contar($p, 18, 'Robo o pérdida', $u);
    expect(app(Stock::class)->de($p->id))->toBe(18.0)->and($inv->perdidasMes())->toBe(100.0);

    $inv->contar($p, 17, 'Error de conteo', $u);
    expect($inv->perdidasMes())->toBe(100.0);   // los errores de conteo no son pérdida
});

it('un insumo baja solo con cada venta del servicio y guarda fracciones de céntimo', function () {
    $u = entrarComo('alex');
    $hoja = app(Inv::class)->crearInsumo(['nombre' => 'Papel bond A4', 'um' => 'hojas', 'stock' => '1000', 'un' => 'Millar', 'f' => '1000', 'costo' => '25', 'min' => '200'], $u);
    expect($hoja->oculto)->toBeTrue()->and($hoja->fresh()->costo)->toBe(2.5)->and($hoja->unidadCompra())->toBe(['n' => 'Millar', 'f' => 1000.0]);

    $copia = producto('bn_a4');
    Livewire::test(Inventario::class)->call('abrirConsumo', $copia->id)->call('agregarFila')
        ->set('filas.0.id', $hoja->id)->set('filas.0.v', '1')->call('guardarConsumo');
    expect($copia->insumos()->count())->toBe(1)->and($copia->fresh()->costoReal())->toBe(2.5);

    $this->travel(1)->seconds();
    venderProducto($copia, 30);
    expect(app(Stock::class)->de($hoja->id))->toBe(970.0);
});

it('«1 rinde» guarda la fracción que gasta cada servicio', function () {
    entrarComo('alex');
    $toner = app(Inv::class)->crearInsumo(['nombre' => 'Tóner', 'um' => 'tóner', 'stock' => '2'], usuario('alex'));
    $copia = producto('bn_a4');
    Livewire::test(Inventario::class)->call('abrirConsumo', $copia->id)->call('agregarFila')
        ->set('filas.0.id', $toner->id)->set('filas.0.modo', 'rinde')->set('filas.0.v', '7000')->call('guardarConsumo');
    expect(round($copia->insumos()->first()->cantidad * 7000, 3))->toBe(1.0);
});

it('el kárdex muestra cada movimiento con su saldo, de cualquier fecha', function () {
    $u = entrarComo('alex');
    $p = util();
    $inv = app(Inv::class);
    $this->travelTo(now()->subDays(60));
    $inv->contar($p, 10, null, $u);
    $this->travel(1)->minutes();
    venderProducto($p, 2);
    $this->travelBack();
    $inv->entrada($p, 5, null, null, $u);

    $k = $inv->kardex($p, today()->subDays(29)->toDateString(), today()->toDateString());
    expect($k)->toHaveCount(1)->and($k[0]['saldo'])->toBe(13.0);

    $todo = $inv->kardex($p, '2000-01-01', today()->toDateString());
    expect(collect($todo)->pluck('saldo')->all())->toBe([13.0, 8.0, 10.0])
        ->and($todo[1]['que'])->toStartWith('Venta');
});

it('la toma de inventario se guarda en el servidor y se aplica junta', function () {
    $u = entrarComo('alex');
    $p = util();
    $p->update(['codigos_barra' => '7750001']);
    app(Inv::class)->contar($p, 10, null, $u);

    $t = Livewire::test(InventarioToma::class)->set('buscar', '7750001')->call('escanear')->set('buscar', '7750001')->call('escanear');
    expect(TomaConteo::first()->cantidad)->toBe(2.0);

    entrarComo('jeremy');   // otro usuario sigue desde otro equipo
    Livewire::test(InventarioToma::class)->call('fijar', $p->id, '8')->assertSee('faltan 2')->call('pedir', 'aplicar')->call('aplicar');
    expect(app(Stock::class)->de($p->id))->toBe(8.0)->and(TomaConteo::count())->toBe(0)
        ->and(StockMovimiento::where('motivo', 'Toma de inventario')->first()->diferencia)->toBe(-2.0);
});

it('la pantalla muestra lo que hay y quién no tiene permiso de costos no ve el dinero', function () {
    $u = entrarComo('alex');
    $p = util();
    $p->update(['costo' => 40]);
    app(Inv::class)->contar($p, 3, null, $u);
    Livewire::test(Inventario::class)->assertSee($p->nombre)->assertSee('Mercadería a precio de costo')->assertSee('S/ 1.20');
    Livewire::test(Inventario::class)->set('filtro', 'reponer')->assertSee($p->nombre);   // 3 es el mínimo por defecto

    entrarComo('jeremy');
    Livewire::test(Inventario::class)->assertSee($p->nombre)->assertDontSee('Mercadería a precio de costo');
    $this->get(route('inventario.valorizado'))->assertForbidden();
});

it('el inventario valorizado sale en PDF', function () {
    $u = entrarComo('alex');
    app(Inv::class)->contar(util(), 5, null, $u);
    $this->get(route('inventario.valorizado'))->assertOk()->assertHeader('content-type', 'application/pdf');
});

it('calcula para cuántos días alcanza al ritmo de las últimas 2 semanas', function () {
    $u = entrarComo('alex');
    $p = util();
    app(Inv::class)->contar($p, 100, null, $u);
    $this->travel(1)->seconds();
    venderProducto($p, 28);   // 2 por día
    $v = app(Inv::class)->velocidad();
    expect($v[$p->id])->toBe(2.0)->and(Inv::diasAlcanza(72, $v[$p->id]))->toBe(36);
});
