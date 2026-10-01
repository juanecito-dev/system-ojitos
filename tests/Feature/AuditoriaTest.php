<?php

/*
 * Pruebas de la auditoría del sistema (29/09/2026): datos mal escritos, fechas imposibles y reglas del negocio
 * que se podían saltar. Cada prueba dice qué pasaba antes.
 */

use App\Livewire\Ajustes;
use App\Livewire\AjustesMaquinas;
use App\Livewire\Clientes;
use App\Livewire\Redaccion as RedaccionLista;
use App\Livewire\RedaccionDocumento;
use App\Livewire\Reportes;
use App\Livewire\Usuarios;
use App\Livewire\Vender;
use App\Models\Cliente;
use App\Models\Documento;
use App\Models\Maquina;
use App\Models\Pedido;
use App\Models\Rol;
use App\Redaccion\Catalogo;
use App\Services\Clientes as ServicioClientes;
use App\Services\Compras as ServicioCompras;
use App\Services\Comprobantes;
use App\Services\Contadores;
use App\Services\ErrorNegocio;
use App\Services\Pedidos as ServicioPedidos;
use App\Services\Ventas;
use App\Support\NegocioActual;
use Livewire\Livewire;

beforeEach(fn () => negocioDePrueba());

function auditoriaPedido(): Pedido
{
    return app(ServicioPedidos::class)->guardar(null, ['etapa' => 'proceso', 'cliente' => ['nombre' => 'Rosa Quispe', 'cel' => '987654321'],
        'items' => [['pid' => producto('anillado')->id, 'nombre' => 'Anillado', 'cant' => 2, 'precio' => 300]]], usuario('alex'));
}

// ------------------------------------------------------------ fechas imposibles

it('una fecha imposible en la dirección no rompe los reportes ni las descargas', function () {
    entrarComo('alex');
    // antes: 2026-13-45 hacía caer la pantalla con un error del servidor
    Livewire::withQueryParams(['r' => 'custom', 'desde' => '2026-13-45', 'hasta' => '2026-02-31'])->test(Reportes::class)->assertOk();
    $this->get(route('compras.csv', '2026-13'))->assertNotFound();
    $this->get(route('facturacion.csv', '2026-00'))->assertNotFound();
    $this->get(route('ventas.csv', '2026-02-31'))->assertNotFound();
    $this->get(route('ventas', ['dia' => '2026-02-31']))->assertOk();
});

it('una compra no puede ser de una fecha futura ni vencer antes de hacerse', function () {
    $srv = app(ServicioCompras::class);
    $prov = $srv->guardarProveedor(null, 'Librería Central', '', '');
    $base = ['proveedor_id' => $prov->id, 'condicion' => 'credito', 'total' => 5000];
    expect(fn () => $srv->guardar(null, $base + ['fecha' => today()->addDays(3)->toDateString()], [], usuario('alex')))
        ->toThrow(ErrorNegocio::class, 'futura');
    expect(fn () => $srv->guardar(null, $base + ['fecha' => today()->toDateString(), 'vence' => today()->subDays(5)->toDateString()], [], usuario('alex')))
        ->toThrow(ErrorNegocio::class, 'vencimiento');
    // una fecha imposible queda en la de hoy
    $c = $srv->guardar(null, $base + ['fecha' => '2026-02-31'], [], usuario('alex'));
    expect($c->fecha->toDateString())->toBe(today()->toDateString());
});

// ------------------------------------------------------------ configuración del negocio

it('la configuración no guarda un RUC, celular, serie u opción inválidos', function () {
    entrarComo('alex');
    $neg = app(NegocioActual::class)->obligatorio();
    $w = Livewire::test(Ajustes::class);
    foreach ([['ruc', '12345678901'], ['ruc', 'abc'], ['celular', '12345'], ['yapeCel', '812345678'], ['serieB', 'E001'], ['serieF', 'EB01'],
        ['serieB', 'X1'], ['regimen', 'inventado'], ['igv', 'medio'], ['tkAncho', '100'], ['autoLock', '7'], ['meta', 'mucho'], ['ult_EB01', '12a']] as [$k, $v]) {
        $w->call('fijar', $k, $v);
    }
    $neg->refresh();
    expect($neg->ruc)->toBe('10745784548')->and($neg->celular)->toBe('942219622')->and($neg->ajuste('serieB', 'EB01'))->toBe('EB01')
        ->and($neg->ajuste('regimen', 'rer'))->toBe('rer')->and($neg->ajuste('tkAncho', '80'))->toBe('80')->and($neg->ajuste('meta'))->toBeNull();

    $w->call('fijar', 'celular', '+51 962 506 202')->call('fijar', 'serieB', 'eb02')->call('fijar', 'meta', '1,500')->call('fijar', 'ruc', '20100070970');
    $neg->refresh();
    expect($neg->celular)->toBe('962506202')->and($neg->ajuste('serieB'))->toBe('EB02')->and($neg->ajuste('meta'))->toBe('1500.00')->and($neg->ruc)->toBe('20100070970');
});

// ------------------------------------------------------------ comprobantes

it('no registra un comprobante con una serie o número que SUNAT no daría', function () {
    $v = app(Ventas::class)->registrar(usuario('jeremy'), ['metodo' => 'efectivo', 'lineas' => [
        ['producto_id' => producto('s_empastado')->id, 'nombre' => 'Empastado', 'cantidad' => 1, 'precio' => 2000]]]);
    $x = collect(app(Comprobantes::class)->pendientes())->firstWhere('kind', 'venta');
    $srv = app(Comprobantes::class);
    expect(fn () => $srv->emitirPendiente($x, 'E001', '12', null))->toThrow(ErrorNegocio::class, 'EB01')   // serie de facturas en una boleta
        ->and(fn () => $srv->emitirPendiente($x, 'EB1', '12', null))->toThrow(ErrorNegocio::class)
        ->and(fn () => $srv->emitirPendiente($x, 'EB01', '123456789', null))->toThrow(ErrorNegocio::class, '8 cifras');
    $c = $srv->emitirPendiente($x, 'eb01', '446', null);
    expect($c->etiqueta())->toBe('EB01-446')->and($v->fresh()->boleta)->toBeTrue();

    // al cobrar con «ya la emití» pasa lo mismo
    expect(fn () => app(Ventas::class)->registrar(usuario('jeremy'), ['metodo' => 'efectivo', 'lineas' => [
        ['producto_id' => producto('s_empastado')->id, 'nombre' => 'Empastado', 'cantidad' => 1, 'precio' => 2000]],
        'cpe' => ['tipo' => '03', 'pide' => true, 'emitida' => true, 'serie' => 'F001', 'numero' => '447']]))->toThrow(ErrorNegocio::class, 'serie');
});

// ------------------------------------------------------------ fiados, pedidos y caja

it('no deja cobrar a un cliente más de lo que debe', function () {
    $c = Cliente::create(['uid' => 'c1', 'nombre' => 'Rosa', 'celular' => '987654321']);
    $srv = app(ServicioClientes::class);
    expect(fn () => $srv->registrarPago($c, 5000, 'efectivo', usuario('alex')))->toThrow(ErrorNegocio::class, 'No tiene deuda');
    $srv->anotarDeuda($c, 3000, usuario('alex'));
    // antes: aceptaba 500 en vez de 50 y el cliente quedaba con saldo a favor
    expect(fn () => $srv->registrarPago($c, 50000, 'efectivo', usuario('alex')))->toThrow(ErrorNegocio::class, 'S/ 30.00');
    $srv->registrarPago($c, 3000, 'efectivo', usuario('alex'));
    expect($c->saldo())->toBe(0);
});

it('el pago de un pedido no puede quedar «fiado» y los cambios de etapa respetan el orden', function () {
    $p = auditoriaPedido();
    $srv = app(ServicioPedidos::class);
    // antes: desde el navegador se podía mandar «fiado» como método del adelanto, sin el permiso de fiar
    expect(fn () => $srv->registrarPago($p, 200, 'fiado', 'Pago a cuenta', usuario('jeremy')))->toThrow(ErrorNegocio::class);
    $srv->rechazar($p);
    expect($p->fresh()->etapa)->toBe('proceso');       // un encargo en proceso no se «rechaza»
    $srv->reabrir($p);
    expect($p->fresh()->etapa)->toBe('proceso');       // solo se reabre lo rechazado
});

// ------------------------------------------------------------ usuarios y roles

it('el tope de descuento en % no puede pasar de 100 ni quedar «sin tope» por error', function () {
    entrarComo('alex');
    $rol = Rol::where('es_admin', false)->first();
    $w = Livewire::test(Usuarios::class)->call('editarRol', $rol->id)->set('r.descPct', '150')->call('guardarRol');
    expect($w->get('error'))->toContain('1 a 100');
    $w->set('r.descPct', '15')->call('guardarRol');
    expect($rol->fresh()->desc_pct)->toBe(15);
});

// ------------------------------------------------------------ redacción

it('una persona con DNI incompleto en un documento no ensucia la ficha del cliente', function () {
    entrarComo('alex');
    Catalogo::sincronizar();
    Livewire::test(RedaccionDocumento::class, ['modelo' => 'autorizacion'])->set('d.aut_nombre', 'Luis Vega')->set('d.aut_dni', '1234')
        ->set('d.aut_cel', '987111222')->call('generar', true);
    $cl = Cliente::where('celular', '987111222')->first();
    expect($cl)->not->toBeNull()->and($cl->documento)->toBeNull();
});

it('una línea cualquiera de la caja no puede marcar un documento como cobrado', function () {
    entrarComo('alex');
    $doc = Documento::create(['uid' => 'd1', 'plantilla' => 'carta_poder', 'titulo' => 'Carta poder', 'estado' => 'borrador']);
    $p = producto('u_lapicero');
    Livewire::test(Vender::class)->set('orden', [['pid' => $p->id, 'nombre' => $p->nombre, 'det' => '', 'cant' => 1, 'precio' => $p->opciones->first()->precio, 'doc' => 'd1']])
        ->call('abrirCobro')->call('cobrar', false);
    expect($doc->fresh()->estado)->toBe('borrador');
});

// ------------------------------------------------------------ campos que solo aceptan lo que corresponde

it('los campos de DNI, RUC, celular, montos y PIN tienen su regla de tecleo', function () {
    entrarComo('alex');
    Catalogo::sincronizar();
    $c = Cliente::create(['uid' => 'c9', 'nombre' => 'Rosa', 'celular' => '987654321']);
    Livewire::test(Clientes::class)->call('abrir', $c->uid)->call('editarFicha')
        ->assertSeeHtml('data-solo="cel"')->assertSeeHtml('data-solo="doc"')->assertSeeHtml('data-solo="monto"');
    Livewire::test(RedaccionDocumento::class, ['modelo' => 'cv_terreno'])
        ->assertSeeHtml('data-solo="dni"')->assertSeeHtml('data-solo="cel"')->assertSeeHtml('data-solo="soles"');
    auth()->logout();
    $this->get(route('login'))->assertSee('campos.js')->assertSee('data-solo="pin"', false);
});

it('el archivo de reglas limita el DNI a 8 números, el celular a 9 y el monto a 2 decimales', function () {
    $js = file_get_contents(public_path('js/campos.js'));
    expect($js)->toContain('dni: v => digitos(v, 8)')->toContain('ruc: v => digitos(v, 11)')->toContain('pin: v => digitos(v, 6)')
        ->toContain('monto: v => numero(v, 8, 2)')->toContain('.slice(0, 9)');
});

it('la lectura de un contador no acepta números exagerados', function () {
    entrarComo('alex');
    Livewire::test(AjustesMaquinas::class)->call('agregar');
    $m = Maquina::first();
    expect(fn () => app(Contadores::class)->anotar(today()->toDateString(), $m->uid, $m->contadores[0]['id'], 12_345_678_901))
        ->toThrow(ErrorNegocio::class, '9 cifras')
        ->and(fn () => app(Contadores::class)->merma(today()->toDateString(), 'bn', 500_000))->toThrow(ErrorNegocio::class);
});

it('la lista de documentos sigue abriendo', function () {
    entrarComo('alex');
    Catalogo::sincronizar();
    Livewire::test(RedaccionLista::class)->assertOk();
});
