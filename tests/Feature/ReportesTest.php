<?php

use App\Livewire\Caja;
use App\Livewire\Reportes;
use App\Models\CajaMovimiento;
use App\Models\Producto;
use App\Services\Compras;
use App\Services\Inventario;
use App\Services\Reportes as Srv;
use App\Services\Ventas;
use App\Support\Texto;
use Carbon\Carbon;
use Livewire\Livewire;

beforeEach(fn () => negocioDePrueba());

function ventaReporte(array $lineas, string $metodo = 'efectivo', string $quien = 'jeremy'): void
{
    app(Ventas::class)->registrar(usuario($quien), ['lineas' => $lineas, 'metodo' => $metodo]);
}

function lineaReporte(string $uid, float $cant, int $precio, ?float $costo = null, bool $tercero = false): array
{
    $p = producto($uid);

    return ['producto_id' => $p->id, 'producto_uid' => $uid, 'nombre' => $p->nombre, 'cantidad' => $cant, 'precio' => $precio, 'costo' => $costo, 'tercero' => $tercero];
}

function gasto(int $monto, string $concepto = 'Papel', string $tipo = 'gasto'): void
{
    CajaMovimiento::create(['uid' => Texto::nuevoUid(), 'fecha' => today()->toDateString(), 'tipo' => $tipo, 'concepto' => $concepto, 'monto' => $monto,
        'metodo' => 'efectivo', 'usuario_id' => usuario('alex')->id, 'vendedor' => 'Alex Simon Santiago', 'ocurrido_at' => now()]);
}

it('calcula la ganancia real: vendido − costo − gastos − pérdidas, sin las tasas de terceros', function () {
    entrarComo('alex');
    ventaReporte([lineaReporte('bn_a4', 100, 15, 2.5), lineaReporte('col_a4', 10, 100)]);            // S/ 25, costo S/ 2.50 solo en B/N
    ventaReporte([lineaReporte('bn_a4', 20, 15, 2.5), lineaReporte('col_a4', 1, 1000, null, true)], 'yape');   // S/ 3 + tasa S/ 10
    gasto(500);
    gasto(2000, 'Para el banco', 'retiro');

    $R = app(Srv::class)->calcular(today()->toDateString(), today()->toDateString());
    expect($R['ventas'])->toBe(2800)->and($R['tasas'])->toBe(1000)->and($R['n'])->toBe(2)
        ->and($R['cogs'])->toBe(300.0)->and($R['cobertura'])->toBe(64)   // 1800 de 2800 con costo
        ->and($R['gastos'])->toBe(500)->and($R['ganancia'])->toBe(2800 - 300 - 500)
        ->and($R['met'])->toBe(['efectivo' => 2500, 'yape' => 300])
        ->and($R['retiros'])->toBe(2000)->and($R['vend']['Jeremy']['v'])->toBe(2800)
        ->and(array_key_first($R['heat']))->toBe(now()->dayOfWeek.'-'.now()->hour);
});

it('el dinero que entró y salió cuenta fiados cobrados, pagos a proveedores y pérdidas', function () {
    $u = entrarComo('alex');
    $p = Producto::with('opciones')->where('rapido', true)->whereHas('opciones')->orderBy('orden')->first();
    $p->update(['costo' => 100]);
    app(Inventario::class)->contar($p, 10, null, $u);
    app(Inventario::class)->contar($p, 8, 'Robo o pérdida', $u);
    $prov = app(Compras::class)->guardarProveedor(null, 'Tai Loy', '', '');
    $c = app(Compras::class)->guardar(null, ['proveedor_id' => $prov->id, 'condicion' => 'credito'], [['producto_id' => $p->id, 'cantidad' => 1, 'factor' => 1, 'costo_unitario' => 700]], $u);
    app(Compras::class)->pagar($c, 700, 'efectivo', true, $u);

    $R = app(Srv::class)->calcular(today()->toDateString(), today()->toDateString());
    expect($R['perd'])->toBe(200.0)->and($R['pagProv'])->toBe(700)->and($R['pagCaja'])->toBe(0)
        ->and($R['sale'])->toBe(700)->and($R['ganancia'])->toBe(-200);
});

it('elige el periodo y con qué compararlo', function () {
    $this->travelTo(Carbon::parse('2026-09-29 10:00'));
    expect(Srv::rango('mes'))->toBe(['2026-09-01', '2026-09-29'])
        ->and(Srv::previo('2026-09-01', '2026-09-29', 'mes'))->toBe(['2026-08-01', '2026-08-29'])
        ->and(Srv::previo('2026-09-23', '2026-09-29', '7'))->toBe(['2026-09-16', '2026-09-22'])
        ->and(Srv::previo('2026-09-01', '2026-09-29', 'mes', 'anio'))->toBe(['2025-09-01', '2025-09-29'])
        ->and(Srv::previo('2026-01-01', '2026-09-29', 'anio'))->toBe(['2025-01-01', '2025-09-29'])
        ->and(Srv::rango('mespas'))->toBe(['2026-08-01', '2026-08-31']);
});

it('la pantalla muestra el resultado y compara con el año pasado', function () {
    entrarComo('alex');
    ventaReporte([lineaReporte('bn_a4', 100, 15, 2.5)]);
    $this->travelTo(now()->subYear());
    ventaReporte([lineaReporte('bn_a4', 50, 15, 2.5)]);
    $this->travelBack();

    Livewire::test(Reportes::class)->assertSee('Ganancia')->assertSee('S/ 15.00')
        ->set('comparar', 'anio')->assertSee('▲ 100% vs. el año pasado')
        ->set('rango', 'anio')->assertSee('Mes a mes');
});

it('quien no ve costos no ve la ganancia ni el PDF', function () {
    $j = entrarComo('jeremy');
    $j->rol->sincronizarPermisos(['vender', 'caja', 'reportes']);
    ventaReporte([lineaReporte('bn_a4', 100, 15, 2.5)]);
    Livewire::test(Reportes::class)->assertSee('Vendido')->assertDontSee('Costo de lo vendido')->assertDontSee('Reporte en PDF');
    $this->get(route('reportes.pdf'))->assertForbidden();
});

it('descarga el reporte en PDF y las ventas del periodo en Excel', function () {
    entrarComo('alex');
    ventaReporte([lineaReporte('bn_a4', 100, 15, 2.5)]);
    $hoy = today()->toDateString();
    $this->get(route('reportes.pdf', ['desde' => $hoy, 'hasta' => $hoy]))->assertOk()->assertHeader('content-type', 'application/pdf');
    $this->get(route('reportes.ventas', ['desde' => $hoy, 'hasta' => $hoy]))->assertOk()->assertSee('B/N A4')->assertSee('15.00');
});

it('arma el resumen del día para WhatsApp en Caja', function () {
    entrarComo('alex');
    ventaReporte([lineaReporte('bn_a4', 100, 15)]);
    ventaReporte([lineaReporte('bn_a4', 20, 15)], 'yape');
    gasto(500);
    $txt = app(Srv::class)->resumenDia(today()->toDateString());
    expect($txt)->toContain('Vendido: S/ 18.00 (2 ventas)')->toContain('Yape: S/ 3.00')->toContain('Gastos: S/ 5.00')->toContain('SUNAT: boleta de cierre por S/ 3.00');
    Livewire::test(Caja::class)->assertSee('Mandarme el resumen del día por WhatsApp');

    entrarComo('jeremy');
    Livewire::test(Caja::class)->assertDontSee('Mandarme el resumen');
});
