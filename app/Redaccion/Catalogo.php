<?php

namespace App\Redaccion;

use App\Models\ModeloRedaccion;
use Illuminate\Support\Facades\Cache;

/**
 * Los modelos del sistema viven como datos en database/modelos/{paquete}/{id}.php y se copian a la tabla
 * modelos_redaccion («php artisan ojitos:modelos», o solos cuando cambian los archivos).
 * _campos.php tiene grupos de campos que se repiten (precio, ubicación, cuotas…) y _comunes.php
 * las cláusulas que llevan todos los contratos.
 */
class Catalogo
{
    public const SECCIONES = ['contratos' => 'Contratos', 'cartas' => 'Solicitudes, cartas y poderes', 'declaraciones' => 'Declaraciones y constancias',
        'laboral' => 'Laboral', 'cv' => 'Currículum vitae', 'mios' => 'Mis modelos'];

    private static ?array $comunes = null;

    public static function carpeta(): string
    {
        return database_path('modelos');
    }

    /** huella de los archivos: si cambia, hay que sincronizar */
    public static function firma(): string
    {
        $f = glob(self::carpeta().'/{*,*/*}.{php,inc}', GLOB_BRACE) ?: [];
        sort($f);

        return sha1(implode('|', array_map(fn ($x) => $x.':'.filemtime($x).':'.filesize($x), $f)));
    }

    /** copia los archivos a la base de datos: [nuevos, actualizados, apagados] */
    public static function sincronizar(): array
    {
        $grupos = require self::carpeta().'/_campos.php';
        $expandir = function (array $campos) use ($grupos) {
            $out = [];
            foreach ($campos as $c) {
                if (isset($c['grupo'])) {
                    array_push($out, ...($grupos[$c['grupo']] ?? []));
                } else {
                    $out[] = $c;
                }
            }

            return $out;
        };
        $r = ['nuevos' => 0, 'actualizados' => 0, 'apagados' => 0];
        $vistos = ['_comunes'];
        $comunes = require self::carpeta().'/_comunes.php';
        self::guardar(['uid' => '_comunes', 'paquete' => '_', 'seccion' => '_', 'nombre' => 'Cláusulas comunes', 'activo' => false, 'orden' => 0,
            'definicion' => ['clausulas' => $comunes]], $r);
        foreach (glob(self::carpeta().'/*/*.php') ?: [] as $i => $archivo) {
            $m = require $archivo;
            $paquete = basename(dirname($archivo));
            $def = array_diff_key($m, array_flip(['id', 'nombre', 'icono', 'desc', 'nota', 'cobro', 'seccion', 'niveles', 'orden']));
            $def['campos'] = $expandir($m['campos'] ?? []);
            self::guardar(['uid' => $m['id'], 'paquete' => $paquete, 'seccion' => $m['seccion'] ?? 'cartas', 'nombre' => $m['nombre'], 'icono' => $m['icono'] ?? '📄',
                'descripcion' => $m['desc'] ?? null, 'nota' => $m['nota'] ?? null, 'cobro' => $m['cobro'] ?? 'doc', 'niveles' => ! empty($m['niveles']),
                'orden' => $m['orden'] ?? $i + 1, 'activo' => true, 'definicion' => $def], $r);
            $vistos[] = $m['id'];
        }
        $r['apagados'] = ModeloRedaccion::whereNotIn('uid', $vistos)->where('activo', true)->update(['activo' => false]);
        Cache::forever('modelos_redaccion_firma', self::firma());
        self::$comunes = null;

        return $r;
    }

    private static function guardar(array $x, array &$r): void
    {
        $x['version'] = sha1(json_encode($x));
        $m = ModeloRedaccion::firstWhere('uid', $x['uid']);
        if (! $m) {
            ModeloRedaccion::create($x);
            $r['nuevos']++;
        } elseif ($m->version !== $x['version']) {
            $m->update($x);
            $r['actualizados']++;
        }
    }

    /** sincroniza solo si los archivos cambiaron desde la última vez */
    public static function alDia(): void
    {
        if (Cache::get('modelos_redaccion_firma') !== self::firma() || ! ModeloRedaccion::exists()) {
            self::sincronizar();
        }
    }

    /** cláusulas que llevan todos los contratos */
    public static function comunes(): array
    {
        return self::$comunes ??= (array) (ModeloRedaccion::firstWhere('uid', '_comunes')?->def('clausulas') ?? []);
    }

    public static function modelo(string $uid): ?ModeloRedaccion
    {
        return ModeloRedaccion::where('uid', $uid)->where('activo', true)->first();
    }
}
