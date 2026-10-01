<?php

use App\Http\Controllers\DocumentosController;
use App\Http\Controllers\SesionController;
use App\Http\Controllers\TicketController;
use App\Livewire;
use Illuminate\Support\Facades\Route;

// Entrada
Route::get('/primer-uso', Livewire\PrimerUso::class)->name('primer-uso');
Route::get('/n/{slug}', [SesionController::class, 'recordarNegocio'])->name('negocio.recordar');
Route::middleware('guest')->group(function () {
    Route::get('/entrar', Livewire\Entrar::class)->name('login');
});
Route::post('/salir', [SesionController::class, 'salir'])->middleware('auth')->name('salir');

// Sistema
Route::middleware('auth')->group(function () {
    Route::get('/', Livewire\Inicio::class)->name('inicio');
    Route::get('/vender', Livewire\Vender::class)->middleware('modulo:vender')->name('vender');
    Route::get('/ventas', Livewire\Ventas::class)->middleware('modulo:ventas')->name('ventas');
    Route::get('/caja', Livewire\Caja::class)->middleware('modulo:caja')->name('caja');
    Route::get('/clientes', Livewire\Clientes::class)->middleware('modulo:clientes')->name('clientes');
    Route::get('/clientes/{uid}/estado-de-cuenta.pdf', [DocumentosController::class, 'estadoCuenta'])->middleware('modulo:clientes')->name('clientes.estado');
    Route::get('/pedidos', Livewire\Pedidos::class)->middleware('modulo:encargos')->name('pedidos');
    Route::get('/pedidos/{uid}/proforma.pdf', [DocumentosController::class, 'proforma'])->middleware('modulo:encargos')->name('pedidos.proforma');
    Route::get('/pedidos/{uid}/orden.pdf', [DocumentosController::class, 'ordenTrabajo'])->middleware('modulo:encargos')->name('pedidos.orden');
    Route::get('/facturacion', Livewire\Facturacion::class)->middleware('modulo:comprobantes')->name('facturacion');
    Route::get('/facturacion/registro/{mes}.csv', [DocumentosController::class, 'registroVentas'])->middleware('modulo:comprobantes')->name('facturacion.csv');
    Route::get('/inventario', Livewire\Inventario::class)->middleware('modulo:inventario')->name('inventario');
    Route::get('/inventario/toma', Livewire\InventarioToma::class)->middleware('modulo:inventario')->name('inventario.toma');
    Route::get('/inventario/valorizado.pdf', [DocumentosController::class, 'inventarioValorizado'])->middleware('modulo:inventario')->name('inventario.valorizado');
    Route::get('/compras', Livewire\Compras::class)->middleware('modulo:compras')->name('compras');
    Route::get('/compras/registro/{mes}.csv', [DocumentosController::class, 'registroCompras'])->middleware('modulo:compras')->name('compras.csv');
    Route::get('/reportes', Livewire\Reportes::class)->middleware('modulo:reportes')->name('reportes');
    Route::get('/reportes/reporte.pdf', [DocumentosController::class, 'reporte'])->middleware('modulo:reportes')->name('reportes.pdf');
    Route::get('/reportes/ventas.csv', [DocumentosController::class, 'ventasPeriodo'])->middleware('modulo:reportes')->name('reportes.ventas');
    Route::get('/redaccion', Livewire\Redaccion::class)->middleware('modulo:documentos')->name('redaccion');
    Route::get('/redaccion/nuevo/{modelo}', Livewire\RedaccionDocumento::class)->middleware('modulo:documentos')->name('redaccion.nuevo');
    Route::get('/redaccion/{uid}/documento.pdf', [DocumentosController::class, 'redaccionPdf'])->middleware('modulo:documentos')->name('redaccion.pdf');
    Route::get('/redaccion/{uid}/documento.doc', [DocumentosController::class, 'redaccionWord'])->middleware('modulo:documentos')->name('redaccion.word');
    Route::get('/redaccion/{uid}', Livewire\RedaccionDocumento::class)->middleware('modulo:documentos')->name('redaccion.doc');
    Route::get('/usuarios', Livewire\Usuarios::class)->middleware('modulo:usuarios')->name('usuarios');
    Route::get('/ajustes', Livewire\Ajustes::class)->middleware('modulo:ajustes')->name('ajustes');

    Route::get('/ventas/{uid}/ticket.pdf', [TicketController::class, 'pdf'])->name('ticket.pdf');
    Route::get('/ventas/{uid}/ticket.json', [TicketController::class, 'filas'])->name('ticket.filas');
    Route::get('/ventas/exportar/{fecha}', [TicketController::class, 'csv'])->middleware('modulo:ventas')->name('ventas.csv');
});
