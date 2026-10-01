<?php

namespace Database\Seeders;

use App\Models\Negocio;
use App\Services\AltaNegocio;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Deja lista la lista de permisos. Con OJITOS_DEMO=1 en el .env crea además
     * el negocio Ojitos con usuarios de prueba (alex / 2580 y jeremy / 1470)
     * para probar el sistema sin importar la copia.
     */
    public function run(): void
    {
        AltaNegocio::asegurarPermisos();

        if (config('ojitos.demo') && ! Negocio::where('slug', 'ojitos')->exists()) {
            $alta = app(AltaNegocio::class);
            $neg = $alta->crear([
                'slug' => 'ojitos', 'nombre' => 'Ojitos', 'giro' => 'Copias e impresiones', 'titular' => 'Alex Simon Santiago',
                'ruc' => '10745784548', 'direccion' => 'Jr. San Alejandro 382', 'celular' => '942219622', 'ciudad' => 'Tingo María',
                'rubro' => 'imprenta', 'ajustes' => ['regimen' => 'rer', 'igv' => 'exonerado', 'serieB' => 'EB01', 'serieF' => 'E001'],
            ], ['nombre' => 'Alex Simon Santiago', 'usuario' => 'alex', 'pin' => '2580']);
            $alta->crearUsuario($neg, 'Jeremy', 'jeremy', '1470', 'vendedor');
        }
    }
}
