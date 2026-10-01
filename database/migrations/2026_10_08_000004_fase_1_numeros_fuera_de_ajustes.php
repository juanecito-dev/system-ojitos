<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Fase 1, bloque 4: los números que cambian con cada venta salen de la configuración del negocio.
 * - «última boleta/factura emitida en SUNAT» (ajustes.ult_SERIE) → secuencias, clave ult:SERIE.
 * - la última lectura de cada contador del sistema anterior (ajustes.maquinas.ult) → el contador en su máquina.
 * Así guardar la configuración ya no puede pisar un número que otro equipo acaba de subir.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (DB::table('negocios')->whereNotNull('ajustes')->get(['id', 'ajustes']) as $n) {
            $a = json_decode((string) $n->ajustes, true);
            if (! is_array($a)) {
                continue;
            }
            $cambio = false;
            foreach ($a as $k => $v) {
                if (! preg_match('/^ult_([A-Z0-9]{4})$/', (string) $k, $x)) {
                    continue;
                }
                $valor = (int) preg_replace('/\D/', '', (string) $v);
                $fila = DB::table('secuencias')->where('negocio_id', $n->id)->where('clave', 'ult:'.$x[1])->first();
                if ($fila) {
                    DB::table('secuencias')->where('id', $fila->id)->update(['valor' => max((int) $fila->valor, $valor), 'updated_at' => now()]);
                } else {
                    DB::table('secuencias')->insert(['negocio_id' => $n->id, 'clave' => 'ult:'.$x[1], 'valor' => $valor, 'created_at' => now(), 'updated_at' => now()]);
                }
                unset($a[$k]);
                $cambio = true;
            }
            $ult = $a['maquinas']['ult'] ?? null;
            if (is_array($ult)) {
                foreach (DB::table('maquinas')->where('negocio_id', $n->id)->get(['id', 'uid', 'contadores']) as $m) {
                    $cs = json_decode((string) $m->contadores, true);
                    if (! is_array($cs)) {
                        continue;
                    }
                    $nuevos = array_map(fn ($c) => is_array($c) && is_array($u = $ult[$m->uid.':'.($c['id'] ?? '')] ?? null) && isset($u['v'], $u['d'])
                        ? [...$c, 'ult' => ['v' => (int) $u['v'], 'd' => (string) $u['d']]] : $c, $cs);
                    if ($nuevos !== $cs) {
                        DB::table('maquinas')->where('id', $m->id)->update(['contadores' => json_encode($nuevos, JSON_UNESCAPED_UNICODE)]);
                    }
                }
                unset($a['maquinas']['ult']);
                if (! array_filter($a['maquinas'])) {
                    unset($a['maquinas']);
                }
                $cambio = true;
            }
            if ($cambio) {
                DB::table('negocios')->where('id', $n->id)->update(['ajustes' => $a ? json_encode($a, JSON_UNESCAPED_UNICODE) : null]);
            }
        }
    }

    public function down(): void
    {
        // los datos quedan donde están; el código anterior los vuelve a calcular desde los comprobantes y las lecturas
    }
};
