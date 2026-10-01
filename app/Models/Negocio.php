<?php

namespace App\Models;

use App\Support\AjustesNegocio;
use App\Support\Catalogos;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class Negocio extends Model
{
    protected $table = 'negocios';

    protected $guarded = ['id'];

    protected $hidden = ['logo', 'qr_yape', 'qr_plin'];

    public const SUSPENDIDO = 'Este negocio está suspendido. Comunícate con soporte para volver a usarlo.';

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

    /** Un ajuste guardado (meta, tkPie, tkAncho, metOff…); si no hay, el de AjustesNegocio::POR_DEFECTO */
    public function ajuste(string $clave, mixed $def = null): mixed
    {
        return data_get($this->ajustes ?? [], $clave, $def ?? AjustesNegocio::porDefecto($clave));
    }

    /** Guarda un ajuste (null o '' lo quita). */
    public function guardarAjuste(string $clave, mixed $valor): void
    {
        $this->guardarAjustes([$clave => $valor]);
    }

    /**
     * Guarda varios ajustes de una vez sin pisar los que otro equipo cambió al mismo tiempo:
     * se vuelve a leer lo guardado, bloqueado, y solo se cambian estas claves.
     */
    public function guardarAjustes(array $cambios): void
    {
        foreach ($cambios as $k => $v) {
            if (is_scalar($v) && $v !== '' && ($error = AjustesNegocio::problema((string) $k, $v)) !== '') {
                throw new InvalidArgumentException($error);
            }
        }
        $a = DB::transaction(function () use ($cambios) {
            $guardado = static::query()->toBase()->where('id', $this->id)->lockForUpdate()->value('ajustes');
            $a = is_string($guardado) ? (json_decode($guardado, true) ?: []) : [];
            foreach ($cambios as $k => $v) {
                if ($v === null || $v === '') {
                    unset($a[$k]);
                } else {
                    $a[$k] = $v;
                }
            }
            static::query()->whereKey($this->id)->update(['ajustes' => $a ? json_encode($a, JSON_UNESCAPED_UNICODE) : null, 'updated_at' => now()]);

            return $a;
        });
        $this->ajustes = $a ?: null;
        $this->syncOriginalAttribute('ajustes');
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
