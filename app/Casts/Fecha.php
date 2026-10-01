<?php

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Día del negocio (sin hora). Se guarda siempre como «2026-09-29» para que las búsquedas
 * por fecha funcionen igual en SQLite y en MySQL, y se lee como fecha de Carbon.
 *
 * @implements CastsAttributes<Carbon|null, \DateTimeInterface|string|null>
 */
class Fecha implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): ?Carbon
    {
        return $value === null || $value === '' ? null : Carbon::parse(substr((string) $value, 0, 10))->startOfDay();
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        return substr(Carbon::parse((string) $value)->toDateString(), 0, 10);
    }
}
