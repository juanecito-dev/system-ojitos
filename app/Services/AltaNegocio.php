<?php

namespace App\Services;

use App\Models\ModuloNegocio;
use App\Models\Negocio;
use App\Models\Permiso;
use App\Models\Producto;
use App\Models\Rol;
use App\Models\Usuario;
use App\Support\Catalogos;
use App\Support\NegocioActual;
use App\Support\Texto;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Crea un negocio listo para usar: módulos según su rubro, roles Administrador y Vendedor,
 * catálogo inicial (el de Ojitos) y su usuario administrador.
 */
class AltaNegocio
{
    /** Crea o actualiza la lista de permisos (es la misma para todos los negocios) */
    public static function asegurarPermisos(): void
    {
        $orden = 0;
        foreach (Catalogos::PERMISOS_MODULO as $clave => $nombre) {
            Permiso::updateOrCreate(['clave' => $clave], ['nombre' => $nombre, 'tipo' => 'modulo', 'orden' => ++$orden]);
        }
        foreach (Catalogos::PERMISOS_ACCION as $clave => $nombre) {
            Permiso::updateOrCreate(['clave' => $clave], ['nombre' => $nombre, 'tipo' => 'accion', 'orden' => ++$orden]);
        }
    }

    /**
     * @param  array  $datos  nombre, giro, titular, ruc, direccion, celular, ciudad, rubro
     * @param  array|null  $admin  nombre, usuario, pin (si es null no se crea usuario)
     */
    public function crear(array $datos, ?array $admin = null, bool $conCatalogo = true): Negocio
    {
        self::asegurarPermisos();

        return DB::transaction(function () use ($datos, $admin, $conCatalogo) {
            $rubro = $datos['rubro'] ?? 'imprenta';
            $neg = Negocio::create([
                'slug' => $datos['slug'] ?? $this->slugLibre($datos['nombre']),
                'nombre' => $datos['nombre'],
                'giro' => $datos['giro'] ?? null,
                'titular' => $datos['titular'] ?? null,
                'ruc' => $datos['ruc'] ?? null,
                'direccion' => $datos['direccion'] ?? null,
                'celular' => $datos['celular'] ?? null,
                'ciudad' => $datos['ciudad'] ?? null,
                'rubro' => $rubro,
                'ajustes' => $datos['ajustes'] ?? [],
            ]);
            $prev = app(NegocioActual::class)->get();
            app(NegocioActual::class)->set($neg);

            $this->aplicarRubro($neg, $rubro);
            $this->rolesIniciales($neg);
            if ($conCatalogo) {
                $this->catalogoInicial($neg);
            }
            if ($admin) {
                $this->crearUsuario($neg, $admin['nombre'], $admin['usuario'] ?? null, $admin['pin'], 'admin');
            }

            app(NegocioActual::class)->set($prev ?? $neg);

            return $neg;
        });
    }

    public function aplicarRubro(Negocio $neg, string $rubro): void
    {
        $off = Catalogos::RUBROS[$rubro][1] ?? [];
        foreach (Catalogos::MODULOS_OPCIONALES as $m) {
            ModuloNegocio::updateOrCreate(['negocio_id' => $neg->id, 'modulo' => $m], ['activo' => ! in_array($m, $off, true)]);
        }
    }

    public function rolesIniciales(Negocio $neg): void
    {
        $admin = Rol::withoutGlobalScopes()->firstOrCreate(['negocio_id' => $neg->id, 'uid' => 'admin'], ['nombre' => 'Administrador', 'es_admin' => true, 'orden' => 0]);
        $admin->sincronizarPermisos(array_keys(Catalogos::permisos()));
        $vend = Rol::withoutGlobalScopes()->firstOrCreate(['negocio_id' => $neg->id, 'uid' => 'vendedor'], ['nombre' => 'Vendedor', 'orden' => 1]);
        if ($vend->wasRecentlyCreated) {
            $vend->sincronizarPermisos(Catalogos::PERMISOS_VENDEDOR);
        }
    }

    public function catalogoInicial(Negocio $neg): void
    {
        $items = json_decode(file_get_contents(database_path('data/catalogo-inicial.json')), true);
        foreach ($items as $i => $it) {
            $p = Producto::withoutGlobalScopes()->create([
                'negocio_id' => $neg->id, 'uid' => $it['id'], 'rol' => $it['rol'] ?? null, 'grupo' => $it['g'], 'nombre' => $it['n'], 'color' => $it['c'],
                'rapido' => $it['quick'], 'monto_libre' => $it['free'], 'pide_detalle' => $it['desc'], 'es_tasa' => $it['tasa'],
                'costo' => $it['costo'] ?: null, 'orden' => $i,
            ]);
            foreach ($it['ops'] as $j => $o) {
                $p->opciones()->create(['etiqueta' => $o['l'] ?? '', 'precio' => $o['p'], 'orden' => $j]);
            }
        }
    }

    public function crearUsuario(Negocio $neg, string $nombre, ?string $usuario, string $pin, string $rolUid): Usuario
    {
        $rol = Rol::withoutGlobalScopes()->where('negocio_id', $neg->id)->where('uid', $rolUid)->firstOrFail();

        return Usuario::withoutGlobalScopes()->create([
            'negocio_id' => $neg->id,
            'rol_id' => $rol->id,
            'uid' => Texto::nuevoUid(),
            'nombre' => $nombre,
            'usuario' => Texto::usuario($usuario ?: Texto::primerNombre($nombre)),
            'pin' => Hash::make($pin),
            'pin_largo' => strlen($pin),
        ]);
    }

    public function slugLibre(string $nombre): string
    {
        $base = Str::slug($nombre) ?: 'negocio';
        $slug = $base;
        $n = 1;
        while (Negocio::where('slug', $slug)->exists()) {
            $slug = $base.'-'.(++$n);
        }

        return $slug;
    }
}
