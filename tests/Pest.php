<?php

use App\Models\Negocio;
use App\Models\Producto;
use App\Models\Usuario;
use App\Services\AltaNegocio;
use App\Support\NegocioActual;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(RefreshDatabase::class)->in('Feature');

/** Negocio de prueba con admin «alex» (PIN 2580) y vendedor «jeremy» (PIN 1470) */
function negocioDePrueba(string $slug = 'ojitos', array $ajustes = []): Negocio
{
    $alta = app(AltaNegocio::class);
    $neg = $alta->crear(['slug' => $slug, 'nombre' => ucfirst($slug), 'giro' => 'Copias e impresiones', 'titular' => 'Alex Simon Santiago',
        'ruc' => '10745784548', 'direccion' => 'Jr. San Alejandro 382', 'celular' => '942219622', 'ciudad' => 'Tingo María',
        'ajustes' => $ajustes], ['nombre' => 'Alex Simon Santiago', 'usuario' => 'alex', 'pin' => '2580']);
    $alta->crearUsuario($neg, 'Jeremy', 'jeremy', '1470', 'vendedor');
    app(NegocioActual::class)->set($neg);

    return $neg;
}

function usuario(string $usuario): Usuario
{
    return Usuario::with('rol')->where('usuario', $usuario)->firstOrFail();
}

function entrarComo(string $usuario): Usuario
{
    $u = usuario($usuario);
    test()->actingAs($u);

    return $u;
}

function producto(string $uid): Producto
{
    return Producto::with('opciones')->where('uid', $uid)->firstOrFail();
}
