<?php

use App\Livewire\Inicio;
use App\Models\Cliente;
use App\Models\ClienteMovimiento;
use App\Services\Clientes;
use App\Services\Ventas;
use Livewire\Livewire;

beforeEach(function () {
    $this->travelTo('2026-10-01 09:00:00');
    negocioDePrueba();
});

function deudaCuadra(): void
{
    expect(app(Clientes::class)->recalcularSaldos())->toBe([]);
}

it('después de fiados, pagos, deudas anteriores, anulaciones y clientes unidos, lo que debe cada uno cuadra con sus movimientos', function () {
    $u = entrarComo('alex');
    $srv = app(Clientes::class);
    $cs = collect(range(1, 4))->map(fn ($i) => Cliente::create(['uid' => "c$i", 'nombre' => "Cliente $i", 'celular' => '98765432'.$i]));
    $ventas = [];
    mt_srand(7);
    for ($i = 0; $i < 50; $i++) {
        $this->travel(mt_rand(1, 120))->seconds();
        $c = $cs->random()->fresh();
        if (! $c) {
            continue;
        }
        match (mt_rand(1, 6)) {
            1, 2 => $ventas[] = app(Ventas::class)->registrar($u, ['metodo' => 'fiado', 'cliente_id' => $c->id,
                'lineas' => [['producto_id' => producto('bn_a4')->id, 'nombre' => 'B/N A4', 'cantidad' => mt_rand(10, 200), 'precio' => 15]]]),
            3 => $c->saldo() > 0 ? $srv->registrarPago($c, mt_rand(1, $c->saldo()), 'efectivo', $u) : $srv->anotarDeuda($c, mt_rand(100, 900), $u),
            4 => $c->saldo() > 0 ? app(Ventas::class)->registrar($u, ['metodo' => 'efectivo', 'cliente_id' => $c->id, 'abono' => $c->saldo(),
                'lineas' => [['producto_id' => producto('bn_a4')->id, 'nombre' => 'B/N A4', 'cantidad' => 1, 'precio' => 15]]]) : null,
            5 => $ventas ? app(Ventas::class)->anular(array_pop($ventas), $u) : null,
            6 => ($m = ClienteMovimiento::whereNull('venta_uid')->where('tipo', 'abono')->inRandomOrder()->first()) ? $srv->borrarMovimiento($m) : null,
        };
        deudaCuadra();
    }
    // y al unir dos clientes, su deuda se suma
    [$a, $b] = [$cs[0]->fresh(), $cs[1]->fresh()];
    $total = $a->saldo() + $b->saldo();
    $srv->unir($a, $b);
    expect($a->saldo())->toBe($total);
    deudaCuadra();
});

it('Inicio muestra lo que deben sin sumar todos los movimientos', function () {
    $u = entrarComo('alex');
    $c = Cliente::create(['uid' => 'c1', 'nombre' => 'Rosa']);
    app(Clientes::class)->anotarDeuda($c, 2500, $u);

    Livewire::test(Inicio::class)->assertViewHas('deben', 2500)->assertViewHas('nDeben', 1);
    expect(app(Clientes::class)->saldos())->toBe([$c->id => 2500]);
});
