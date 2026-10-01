<?php

use App\Livewire\Clientes;
use App\Livewire\Redaccion;
use App\Livewire\RedaccionDocumento;
use App\Livewire\Vender;
use App\Models\Cliente;
use App\Models\Documento;
use App\Models\Venta;
use App\Redaccion\Catalogo;
use App\Redaccion\Formato;
use App\Services\Ventas;
use Livewire\Livewire;

beforeEach(function () {
    negocioDePrueba();
    Catalogo::sincronizar();
});

it('la pantalla muestra los modelos por sección con su precio', function () {
    entrarComo('alex');
    Livewire::test(Redaccion::class)->assertSee('Contratos')->assertSee('Compraventa de terreno')->assertSee('por página')->assertSee('3 niveles')
        ->assertSee('Carta poder')->set('buscarModelo', 'poder')->assertSee('Carta poder')->assertDontSee('Compraventa de terreno');
});

it('llena una carta poder con una frase lista, la genera y guarda a las personas como clientes', function () {
    entrarComo('alex');
    $w = Livewire::test(RedaccionDocumento::class, ['modelo' => 'carta_poder'])
        ->set('d.pod_nombre', 'Rosa Díaz')->set('d.pod_trato', 'Doña')->set('d.pod_dni', '22334455')->set('d.pod_dom', 'Jr. Huallaga 450')
        ->set('d.apo_nombre', 'Luis Vega')->set('d.apo_dni', '66778899')->set('d.apo_dom', 'Av. Raimondi 120')
        ->call('frase', 'facultad', 0)->call('generar');
    $doc = Documento::first();
    expect($w->get('vista'))->toBe('doc')->and($doc->titulo)->toBe('Carta poder')->and($doc->partes)->toBe('Rosa Díaz y Luis Vega')
        ->and($doc->texto)->toContain('identificada con DNI N° 22334455')->toContain('pueda recoger en mi nombre documentos y/o certificados')
        ->and(Cliente::where('documento', '22334455')->first()->extra['trato'])->toBe('Doña');
    $w->assertSee('CARTA PODER');
});

it('avisa de lo que falta y de un DNI mal escrito antes de generar', function () {
    entrarComo('alex');
    $w = Livewire::test(RedaccionDocumento::class, ['modelo' => 'autorizacion'])->set('d.aut_dni', '1234')->call('generar');
    expect($w->get('avisos')['faltan'])->not->toBeEmpty()->and($w->get('avisos')['obs'][0])->toContain('8 números')
        ->and(Documento::count())->toBe(0);
    $w->call('generar', true);
    expect(Documento::count())->toBe(1)->and(Formato::faltan(Documento::first()->texto))->toBeGreaterThan(0);
});

it('en un contrato se prenden cláusulas, se agrega una propia y se mueve', function () {
    entrarComo('alex');
    $w = Livewire::test(RedaccionDocumento::class, ['modelo' => 'compromiso'])->call('nivel', 1)
        ->call('clausula', 'fiador', true)->set('d.fia_nombre', 'Mario Paz')->set('d.fia_dni', '11112222')
        ->call('abrirClausula')->set('clH', 'Uso del dinero')->set('clT', 'el deudor usará el dinero solo para su negocio')->call('guardarClausula')
        ->call('generar', true);
    $t = Documento::first()->texto;
    expect($t)->toContain('FIADOR SOLIDARIO')->toContain('USO DEL DINERO:** El deudor usará el dinero solo para su negocio.')
        ->toContain('[firma] MARIO PAZ | 11112222 | EL FIADOR');
});

it('trae los datos de un cliente guardado y sirve como base para otro documento', function () {
    entrarComo('alex');
    Cliente::create(['uid' => 'c1', 'nombre' => 'Pedro Ramos', 'documento' => '44556677', 'direccion' => 'Jr. Lima 1', 'extra' => ['trato' => 'Don', 'ec' => 'soltero']]);
    $w = Livewire::test(RedaccionDocumento::class, ['modelo' => 'dj'])->set('busca.dec', '4455')->assertSee('Pedro Ramos')
        ->call('cargarCliente', 'dec', Cliente::first()->id);
    expect($w->get('d.dec_dni'))->toBe('44556677')->and($w->get('d.dec_ec'))->toBe('soltero');
    $w->call('generar', true);

    $this->get(route('redaccion.nuevo', ['modelo' => 'dj', 'base' => Documento::first()->uid]))->assertOk()->assertSee('Datos copiados');
});

it('abre los documentos del sistema anterior desde su HTML', function () {
    entrarComo('alex');
    Documento::create(['uid' => 'viejo1', 'plantilla' => 'carta_poder', 'titulo' => 'Carta poder', 'estado' => 'borrador', 'datos' => ['data' => ['pod_nombre' => 'Ana']],
        'html' => '<h1 data-k="title" contenteditable="true">CARTA PODER</h1><p data-k="p">Yo, <b>ANA</b>, otorgo poder.</p><div data-k="sign" data-f="[{&quot;n&quot;:&quot;ANA&quot;,&quot;d&quot;:&quot;123&quot;,&quot;r&quot;:&quot;Poderdante&quot;}]"></div>']);
    Livewire::test(RedaccionDocumento::class, ['uid' => 'viejo1'])->assertSee('otorgo poder')->assertSee('Poderdante');
    expect(Documento::first()->texto)->toBe("[title] CARTA PODER\n[p] Yo, **ANA**, otorgo poder.\n[firma] ANA | 123 | Poderdante");
});

function contratoListo(): Documento
{
    Livewire::test(RedaccionDocumento::class, ['modelo' => 'prestamo'])->call('nivel', 2)
        ->set('d.mte_nombre', 'Ana Ruiz')->set('d.mte_dni', '11223344')->set('d.mta_nombre', 'Juan Soto')->set('d.mta_dni', '55667788')
        ->call('generar', true);

    return Documento::first();
}

it('sale en PDF A4 y en Word, y cuenta sus páginas', function () {
    entrarComo('alex');
    $doc = contratoListo();
    $this->get(route('redaccion.pdf', $doc->uid))->assertOk()->assertHeader('Content-Type', 'application/pdf');
    expect($doc->fresh()->paginas)->toBeGreaterThanOrEqual(1);
    $w = $this->get(route('redaccion.word', $doc->uid))->assertOk()->assertHeader('Content-Type', 'application/msword; charset=UTF-8');
    expect($w->headers->get('Content-Disposition'))->toContain('prestamo-de-dinero-ana-ruiz.doc')
        ->and($w->getContent())->toContain('PRÉSTAMO')->toContain('DNI N° 11223344')->toContain('WordSection1');
});

it('se corrige tocando el texto y guarda la corrección', function () {
    entrarComo('alex');
    $doc = contratoListo();
    $t = str_replace('[title] ', '[title] MI ', $doc->texto)."\n[p] Texto **agregado** a mano.";
    Livewire::test(RedaccionDocumento::class, ['uid' => $doc->uid])->call('corregir', $t);
    $doc->refresh();
    expect($doc->texto)->toContain('[title] MI ')->toContain('**agregado**')->and($doc->datos['editado'])->toBeTrue()->and($doc->paginas)->toBeNull();

    // volver a generar con los datos avisa que se pierden las correcciones
    $w = Livewire::test(RedaccionDocumento::class, ['uid' => $doc->uid])->call('editarDatos')->call('generar');
    expect($w->get('avisos'))->toHaveKey('editado');
});

it('se cobra por página con los ejemplares extra como impresión, y al vender queda cobrado', function () {
    entrarComo('alex');
    $doc = contratoListo();
    $n = app(App\Services\Redaccion::class)->paginas($doc);
    $w = Livewire::test(RedaccionDocumento::class, ['uid' => $doc->uid])->call('abrirCobro');
    expect($w->get('ej'))->toBe(2);
    $w->assertSee('Redacción · '.$n)->assertSee('los otros 1 se cobran como impresión B/N')
        ->call('agregarAlCobro')->assertRedirect(route('vender', ['documento' => $doc->uid, 'ej' => 2]));

    $this->withSession([])->get(route('vender', ['documento' => $doc->uid, 'ej' => 2]))->assertOk();
    $v = Livewire::withQueryParams(['documento' => $doc->uid, 'ej' => 2])->test(Vender::class);
    $lineas = $v->get('docLineas');
    expect($lineas)->toHaveCount(2)->and($lineas[0]['cant'])->toBe($n)->and($lineas[0]['precio'])->toBe(500)
        ->and($lineas[1]['cant'])->toBe($n)->and($lineas[1]['precio'])->toBe(15)->and($v->get('pay.cliente'))->toBe($doc->cliente_id);

    $v->set('orden', $lineas)->call('abrirCobro')->call('cobrar', false);
    $venta = Venta::first();
    $doc->refresh();
    expect($venta->total)->toBe($n * 500 + $n * 15)->and($doc->estado)->toBe('cobrado')->and($doc->venta_uid)->toBe($venta->uid)
        ->and($venta->items->first()->origen_tipo)->toBe('documento')->and($venta->items->first()->origen_uid)->toBe($doc->uid);

    Livewire::test(RedaccionDocumento::class, ['uid' => $doc->uid])->assertSee('Cobrado el')->assertSee('Marcar entregado')->call('entregar');
    expect($doc->fresh()->estado)->toBe('entregado');

    // si se anula la venta, el documento vuelve a «sin cobrar»
    app(Ventas::class)->anular($venta, usuario('alex'), 'Error');
    expect($doc->fresh()->estado)->toBe('borrador')->and($doc->fresh()->venta_uid)->toBeNull();
});

it('una carta se cobra por documento y aparece en la ficha del cliente', function () {
    entrarComo('alex');
    Livewire::test(RedaccionDocumento::class, ['modelo' => 'carta_poder'])
        ->set('d.pod_nombre', 'Rosa Díaz')->set('d.pod_dni', '22334455')->set('d.apo_nombre', 'Luis Vega')->set('d.apo_dni', '66778899')->call('generar', true);
    $doc = Documento::first();
    $c = app(App\Services\Redaccion::class)->cobro($doc, 1);
    expect($c['porPag'])->toBeFalse()->and($c['total'])->toBe(300)->and($c['lineas'])->toHaveCount(1);

    $cli = Cliente::where('documento', '66778899')->first();
    Livewire::test(Clientes::class)->set('ver', $cli->uid)->assertSee('Documentos')->assertSee('Carta poder');
});

function fotoPrueba(): string
{
    $im = imagecreatetruecolor(30, 38);
    imagefill($im, 0, 0, imagecolorallocate($im, 200, 120, 60));
    ob_start();
    imagejpeg($im);

    return 'data:image/jpeg;base64,'.base64_encode((string) ob_get_clean());
}

it('arma un currículum con estudios, experiencia y frases por tipo de trabajo, en PDF y Word', function () {
    entrarComo('alex');
    $w = Livewire::test(RedaccionDocumento::class, ['modelo' => 'cv_simple'])
        ->set('d.nombre', 'Lucía Paredes Ríos')->set('d.cel', '987654321')->set('d.dni', '45678912')->set('d.rubro', 'ventas')
        ->call('frase', 'perfil', 0)
        ->set('d.edu.0.inst', 'Instituto Tingo María')->set('d.edu.0.grado', 'Computación e Informática')->set('d.edu.0.desde', '2019')->set('d.edu.0.hasta', '2022')
        ->call('agregarItem', 'edu')->set('d.edu.1.inst', 'Colegio Nacional Leoncio Prado')
        ->set('d.exp.0.cargo', 'Vendedora')->set('d.exp.0.emp', 'Bodega Don Pepe')->call('fraseItem', 'exp', 0, 'fun', 2)
        ->set('d.hab', "Ofimática: Word y Excel\nTrabajo en equipo");
    expect($w->get('d.perfil'))->toContain('orientado al logro de metas')->and($w->get('d.exp.0.fun'))->toStartWith('Caja: cobro');
    $w->call('generar')->assertSee('Lucía Paredes Ríos')->assertSee('Formación académica')->assertSee('Colegio Nacional Leoncio Prado')->assertSee('+51 987 654 321');

    $doc = Documento::first();
    expect($doc->plantilla)->toBe('cv_simple')->and($doc->titulo)->toBe('Currículum: Lucía Paredes Ríos')->and($doc->partes)->toBe('Lucía Paredes Ríos')
        ->and(Cliente::where('documento', '45678912')->exists())->toBeTrue();
    $this->get(route('redaccion.pdf', $doc->uid))->assertOk()->assertHeader('Content-Type', 'application/pdf');
    expect($this->get(route('redaccion.word', $doc->uid))->getContent())->toContain('<b>Lucía Paredes Ríos</b>')->toContain('FORMACIÓN ACADÉMICA')
        ->toContain('<b>Caja: </b>cobro')->not->toContain('<i></i>');
});

it('cambia de diseño sin perder los datos, con colores en el moderno, y cobra según el diseño', function () {
    entrarComo('alex');
    Livewire::test(RedaccionDocumento::class, ['modelo' => 'cv_medio'])->set('d.nombre', 'Mario Quispe')->set('d.titulo', 'Técnico electricista')
        ->call('ponerFoto', fotoPrueba())->call('generar', true);
    $doc = Documento::first();
    expect($doc->campos()['foto'])->toStartWith('data:image/jpeg;base64,');
    $this->get(route('redaccion.pdf', $doc->uid))->assertOk();

    Livewire::test(RedaccionDocumento::class, ['uid' => $doc->uid])->call('diseno', 'cv_moderno')->call('tema', 'color', '#1E5B4F')->call('tema', 'foto', 'cuadrado')
        ->call('tema', 'lado', 'nada')->assertSee('Técnico electricista')->assertSee('Columna');
    $doc->refresh();
    expect($doc->plantilla)->toBe('cv_moderno')->and($doc->campos()['tema'])->toMatchArray(['color' => '#1E5B4F', 'foto' => 'cuadrado', 'lado' => 'izq'])
        ->and($doc->campos()['nombre'])->toBe('Mario Quispe');
    $this->get(route('redaccion.pdf', $doc->uid))->assertOk();
    expect($this->get(route('redaccion.word', $doc->uid))->getContent())->toContain('#1e5b4f')->toContain('Mario<br>Quispe');

    $c = app(App\Services\Redaccion::class)->cobro($doc, app(App\Services\Redaccion::class)->ejemplares($doc));
    expect($c['total'])->toBe(1200)->and($c['lineas'])->toHaveCount(1)->and($c['lineas'][0]['det'])->toBe('Moderno – Mario Quispe');
    Livewire::test(RedaccionDocumento::class, ['uid' => $doc->uid])->call('diseno', 'cv_harvard');
    expect(app(App\Services\Redaccion::class)->cobro($doc->fresh(), 1)['total'])->toBe(1000);
});

it('abre los currículums del sistema anterior con sus datos', function () {
    entrarComo('alex');
    Documento::create(['uid' => 'cvviejo', 'plantilla' => 'cv_moderno', 'titulo' => 'Currículum', 'estado' => 'borrador', 'html' => '<div class="cvm">…</div>',
        'datos' => ['data' => ['nombre' => 'Shakira Marín', 'edu' => [['grado' => 'Educación Inicial', 'inst' => 'Universidad de Huánuco', 'desde' => '2023']],
            'cert' => [[]], 'tema' => ['color' => '#7A1F3D', 'foto' => 'sin', 'fuente' => 'serif', 'lado' => 'izq', 'tam' => 'compacto']]]]);
    Livewire::test(RedaccionDocumento::class, ['uid' => 'cvviejo'])->assertSee('Shakira')->assertSee('Universidad de Huánuco')->assertSee('#7a1f3d', false);
    $this->get(route('redaccion.pdf', 'cvviejo'))->assertOk();
});
