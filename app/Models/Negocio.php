<?php

namespace App\Models;

use App\Support\Catalogos;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Negocio extends Model
{
    protected $table = 'negocios';

    protected $guarded = ['id'];

    protected $hidden = ['logo', 'qr_yape', 'qr_plin'];

    protected function casts(): array
    {
        return [
            'ajustes' => 'array',
            'activo' => 'boolean',
            'ultima_copia_at' => 'datetime',
        ];
    }

    public function modulos(): HasMany
    {
        return $this->hasMany(ModuloNegocio::class);
    }

    public function roles(): HasMany
    {
        return $this->hasMany(Rol::class)->withoutGlobalScopes()->orderBy('orden');
    }

    public function usuarios(): HasMany
    {
        return $this->hasMany(Usuario::class)->withoutGlobalScopes();
    }

    /** Un ajuste guardado (meta, tkPie, tkAncho, metOff…) */
    public function ajuste(string $clave, mixed $def = null): mixed
    {
        return data_get($this->ajustes ?? [], $clave, $def);
    }

    public function fijarAjuste(string $clave, mixed $valor): void
    {
        $a = $this->ajustes ?? [];
        if ($valor === null || $valor === '') {
            unset($a[$clave]);
        } else {
            $a[$clave] = $valor;
        }
        $this->ajustes = $a;
    }

    public function moduloActivo(string $modulo): bool
    {
        if (! in_array($modulo, Catalogos::MODULOS_OPCIONALES, true)) {
            return true;
        }
        $m = $this->relationLoaded('modulos') ? $this->modulos : $this->modulos()->get();
        $fila = $m->firstWhere('modulo', $modulo);

        return $fila ? (bool) $fila->activo : true;
    }

    public function metodosApagados(): array
    {
        $off = $this->ajuste('metOff');

        return is_array($off) ? $off : Catalogos::METODOS_APAGADOS_INICIO;
    }

    public function nombreMetodo(?string $m): string
    {
        $m = $m ?: 'efectivo';

        return $this->ajuste('metNom.'.$m) ?: (Catalogos::METODOS[$m] ?? ($m === 'fiado' ? 'Fiado' : ucfirst($m)));
    }

    /** Métodos de pago encendidos: [clave => nombre]. Efectivo siempre está. */
    public function metodosActivos(): array
    {
        $off = $this->metodosApagados();
        $out = [];
        foreach (Catalogos::METODOS as $k => $n) {
            if ($k === 'efectivo' || ! in_array($k, $off, true)) {
                $out[$k] = $this->nombreMetodo($k);
            }
        }

        return $out;
    }
}
