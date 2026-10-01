<?php

namespace App\Redaccion;

/**
 * Condiciones cortas de los modelos para mostrar un campo o una parte del texto:
 * «pago=partes», «forma!=unica», «tipo=casa,cuarto», «sigue», «!sigue», «cl:fiador», «nivel>=2»,
 * unidas con «&&» (y) o «||» (o).
 */
class Condicion
{
    /** @param callable(string):bool|null $clOn si la cláusula está incluida */
    public static function cumple(?string $expr, array $d, ?callable $clOn = null, int $nivel = 2): bool
    {
        if ($expr === null || trim($expr) === '') {
            return true;
        }
        foreach (explode('||', $expr) as $o) {
            $ok = true;
            foreach (explode('&&', $o) as $t) {
                if (! self::termino(trim($t), $d, $clOn, $nivel)) {
                    $ok = false;
                    break;
                }
            }
            if ($ok) {
                return true;
            }
        }

        return false;
    }

    private static function termino(string $t, array $d, ?callable $clOn, int $nivel): bool
    {
        if (str_starts_with($t, '!')) {
            return ! self::termino(substr($t, 1), $d, $clOn, $nivel);
        }
        if (str_starts_with($t, 'cl:')) {
            return $clOn ? $clOn(substr($t, 3)) : false;
        }
        if (preg_match('/^nivel\s*(>=|<=|=)\s*(\d)$/', $t, $m)) {
            return match ($m[1]) {
                '>=' => $nivel >= (int) $m[2], '<=' => $nivel <= (int) $m[2], default => $nivel === (int) $m[2]
            };
        }
        if (preg_match('/^([\w]+)\s*(!=|=)\s*(.*)$/u', $t, $m)) {
            $v = (string) ($d[$m[1]] ?? '');
            $en = in_array($v, array_map('trim', explode(',', $m[3])), true);

            return $m[2] === '=' ? $en : ! $en;
        }
        $v = $d[$t] ?? null;

        return $v !== null && $v !== false && $v !== 'false' && $v !== '' && $v !== '0' && $v !== 0;
    }
}
