<?php

use App\Livewire\Clientes;
use App\Livewire\Entrar;
use App\Livewire\Pedidos;
use App\Livewire\Ventas as PantallaVentas;
use App\Models\Cliente;
use App\Models\Concerns\PerteneceANegocio;
use App\Models\Documento;
use App\Models\Negocio;
use App\Models\Usuario;
use App\Models\Venta;
use App\Services\AltaNegocio;
use App\Services\Compras;
use App\Services\Pedidos as ServicioPedidos;
use App\Services\Ventas;
use App\Support\NegocioActual;
use App\Support\SinNegocio;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;

/** todos los modelos que se filtran por negocio */
function modelosDelNegocio(): array
{
    return collect(glob(app_path('Models/*.php')))
        ->map(fn ($f) => 'App\\Models\\'.basename($f, '.php'))
        ->filter(fn ($c) => in_array(PerteneceANegocio::class, class_uses_recursive($c), true))
        ->values()->all();
}

/** el negocio «ojitos» con un cliente, una venta, un pedido y un documento; termina con «otro» como negocio actual */
function dosNegocios(): array
{
    $a = negocioDePrueba('ojitos');
    $cli = Cliente::create(['uid' => 'cli-a', 'nombre' => 'Rosa de Ojitos', 'celular' => '987654321']);
    $v = app(Ventas::class)->registrar(usuario('alex'), ['metodo' => 'efectivo', 'cliente_id' => $cli->id,
        'lineas' => [['producto_id' => producto('bn_a4')->id, 'nombre' => 'B/N A4', 'cantidad' => 2, 'precio' => 15]]]);
    $p = app(ServicioPedidos::class)->guardar(null, ['etapa' => 'proceso', 'cliente' => ['nombre' => 'Rosa de Ojitos', 'cel' => '987654321', 'doc' => '', 'inst' => ''],
        'items' => [['pid' => producto('anillado')->id, 'nombre' => 'Anillado', 'cant' => 1, 'precio' => 300]], 'fecha_entrega' => today()->addDay()->toDateString()], usuario('alex'));
    $d = Documento::create(['uid' => 'doc-a', 'modelo' => 'solicitud', 'titulo' => 'Solicitud de Rosa de Ojitos', 'estado' => 'borrador']);
    $b = negocioDePrueba('otro');

    return compact('a', 'b', 'cli', 'v', 'p', 'd');
}

it('sin negocio elegido, ninguna tabla del negocio se puede leer ni escribir', function () {
    negocioDePrueba();
    app(NegocioActual::class)->set(null);

    foreach (modelosDelNegocio() as $m) {
        expect(fn () => $m::query()->exists())->toThrow(SinNegocio::class);
    }
    expect(fn () => Cliente::create(['uid' => 'x', 'nombre' => 'Sin negocio']))->toThrow(SinNegocio::class);

    // la plataforma lo pide de forma explícita
    expect(app(NegocioActual::class)->plataforma(fn () => Cliente::query()->count()))->toBe(0);
});

it('cada tabla con negocio_id tiene su filtro por negocio', function () {
    negocioDePrueba();
    $conFiltro = collect(modelosDelNegocio())->map(fn ($m) => (new $m)->getTable())->all();
    // modulos_negocio solo se lee desde el propio negocio ($negocio->modulos)
    $porRelacion = ['modulos_negocio'];

    $tablas = collect(Schema::getTables())->pluck('name')->filter(fn ($t) => Schema::hasColumn($t, 'negocio_id'));
    expect($tablas->diff($conFiltro)->diff($porRelacion)->values()->all())->toBe([]);

    foreach (modelosDelNegocio() as $m) {
        expect($m::query()->toSql())->toContain('negocio_id');
    }
});

it('las pantallas de un negocio no muestran nada del otro', function () {
    dosNegocios();
    entrarComo('alex');   // el alex de «otro»

    foreach (['/', '/ventas', '/clientes', '/pedidos', '/facturacion', '/reportes', '/caja', '/redaccion'] as $url) {
        $this->get($url)->assertOk()->assertDontSee('Rosa de Ojitos');
    }
    Livewire::test(PantallaVentas::class)->assertDontSee('Rosa de Ojitos');
    Livewire::test(Clientes::class)->set('q', 'Rosa')->assertDontSee('Rosa de Ojitos');
    Livewire::test(Pedidos::class)->assertDontSee('Rosa de Ojitos');

    // control: en su propio negocio, sí se ve
    app(NegocioActual::class)->set(Negocio::where('slug', 'ojitos')->first());
    entrarComo('alex');
    Livewire::test(Clientes::class)->set('q', 'Rosa')->assertSee('Rosa de Ojitos');
    Livewire::test(Pedidos::class)->assertSee('Rosa de Ojitos');
});

it('los PDF, tickets y documentos del otro negocio no se pueden abrir con su código', function () {
    $x = dosNegocios();
    entrarComo('alex');   // el alex de «otro»

    $this->get(route('ticket.pdf', $x['v']->uid))->assertNotFound();
    $this->get(route('ticket.filas', $x['v']->uid))->assertNotFound();
    $this->get(route('clientes.estado', $x['cli']->uid))->assertNotFound();
    $this->get(route('pedidos.proforma', $x['p']->uid))->assertNotFound();
    $this->get(route('pedidos.orden', $x['p']->uid))->assertNotFound();
    $this->get(route('redaccion.pdf', $x['d']->uid))->assertNotFound();
    $this->get(route('redaccion.word', $x['d']->uid))->assertNotFound();
    $this->get(route('redaccion.doc', $x['d']->uid))->assertNotFound();
    $this->get('/vender?pedido='.$x['p']->uid)->assertOk()->assertDontSee('Rosa de Ojitos');

    // control: el dueño sí los abre
    app(NegocioActual::class)->set($x['a']);
    entrarComo('alex');
    $this->get(route('ticket.pdf', $x['v']->uid))->assertOk();
    $this->get(route('clientes.estado', $x['cli']->uid))->assertOk();
    $this->get(route('pedidos.proforma', $x['p']->uid))->assertOk();
    $this->get(route('redaccion.doc', $x['d']->uid))->assertOk();
});

it('un negocio suspendido saca a quien estaba adentro y no deja entrar', function () {
    $neg = negocioDePrueba();
    entrarComo('jeremy');
    $this->get('/')->assertOk();

    $neg->update(['activo' => false]);
    $this->get('/')->assertRedirect(route('login'))->assertSessionHas('toast', Negocio::SUSPENDIDO);
    $this->assertGuest();

    // aunque el equipo tenga guardado su código, la entrada no lo ofrece
    $this->withUnencryptedCookie('negocio', $neg->slug)->get('/entrar')->assertSee('Código del negocio');
    Livewire::test(Entrar::class)->set('codigo', $neg->slug)->call('elegirNegocio')->assertSet('negocioId', null);
});

it('si el negocio se suspende con la pantalla de entrada abierta, no deja ingresar', function () {
    $neg = negocioDePrueba();
    $c = Livewire::withCookies(['negocio' => $neg->slug])->test(Entrar::class)->set('codigo', $neg->slug)->call('elegirNegocio');
    $neg->update(['activo' => false]);

    $c->set('rol', 'vendedor')->set('usuario', 'jeremy')->set('pin', '1470')->call('ingresar')->assertSet('error', Negocio::SUSPENDIDO);
    $this->assertGuest();
});

it('las filas hijas (líneas, pagos, opciones) guardan su negocio, también al dar de alta un negocio nuevo', function () {
    app(NegocioActual::class)->set(null);
    $neg = app(AltaNegocio::class)->crear(['slug' => 'nuevo', 'nombre' => 'Nuevo', 'rubro' => 'imprenta'], ['nombre' => 'Ana', 'usuario' => 'ana', 'pin' => '2580']);
    expect(DB::table('producto_opciones')->whereNull('negocio_id')->count())->toBe(0)
        ->and(DB::table('producto_opciones')->where('negocio_id', $neg->id)->count())->toBeGreaterThan(0);

    // venta con comprobante, pedido con adelanto y compra pagada: ninguna fila hija queda sin negocio
    app(NegocioActual::class)->set($neg);
    $ana = Usuario::where('usuario', 'ana')->first();
    app(Ventas::class)->registrar($ana, ['metodo' => 'efectivo', 'lineas' => [['producto_id' => producto('bn_a4')->id, 'nombre' => 'B/N A4', 'cantidad' => 2, 'precio' => 15]],
        'cpe' => ['tipo' => '03', 'emitida' => true, 'serie' => 'EB01', 'numero' => '1']]);
    app(ServicioPedidos::class)->guardar(null, ['etapa' => 'proceso', 'cliente' => ['nombre' => 'Rosa', 'cel' => '', 'doc' => '', 'inst' => ''],
        'items' => [['pid' => producto('anillado')->id, 'nombre' => 'Anillado', 'cant' => 1, 'precio' => 300]], 'fecha_entrega' => today()->addDay()->toDateString()], $ana, 100);
    $prov = app(Compras::class)->guardarProveedor(null, 'Proveedor', '', '962506202');
    $c = app(Compras::class)->guardar(null, ['proveedor_id' => $prov->id, 'numero' => 'F1', 'condicion' => 'credito', 'vence' => today()->addDays(30)->toDateString()],
        [['producto_id' => producto('u_lapicero')->id, 'cantidad' => 1, 'unidad' => 'Caja', 'factor' => 12, 'costo_unitario' => 1200]], $ana);
    app(Compras::class)->pagar($c, 1200, 'efectivo', false, $ana);

    foreach (['venta_items', 'comprobante_items', 'pedido_items', 'pedido_pagos', 'pedido_historial', 'compra_items', 'compra_pagos'] as $t) {
        expect(DB::table($t)->count())->toBeGreaterThan(0, $t)
            ->and(DB::table($t)->where(fn ($q) => $q->whereNull('negocio_id')->orWhere('negocio_id', '!=', $neg->id))->count())->toBe(0, $t);
    }
});
