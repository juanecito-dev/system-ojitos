<?php

namespace App\Models;

use App\Models\Concerns\PerteneceANegocio;
use App\Support\Catalogos;
use App\Support\Dinero;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Producto extends Model
{
    use PerteneceANegocio, SoftDeletes;

    protected $table = 'productos';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'rapido' => 'boolean', 'monto_libre' => 'boolean', 'pide_detalle' => 'boolean', 'es_tasa' => 'boolean',
            'favorito' => 'boolean', 'oculto' => 'boolean', 'stock_minimo' => 'float', 'costo' => 'float', 'extra' => 'array',
        ];
    }

    public function opciones(): HasMany
    {
        return $this->hasMany(ProductoOpcion::class)->orderBy('orden');
    }

    public function insumos(): HasMany
    {
        return $this->hasMany(ProductoInsumo::class);
    }

    public function stockBase(): HasOne
    {
        return $this->hasOne(StockBase::class);
    }

    /** "S/ 0.15" o "S/ 0.30 a 0.60" o "Monto libre" */
    public function precioTexto(): string
    {
        $ps = $this->opciones->pluck('precio');
        if ($ps->isEmpty()) {
            return 'Monto libre';
        }
        $mn = $ps->min();
        $mx = $ps->max();

        return $mn === $mx ? Dinero::s($mn) : Dinero::s($mn).' a '.Dinero::n($mx);
    }

    /** en servicios con insumos manda el costo real de los insumos (en céntimos, puede tener fracción) */
    public function costoReal(): float
    {
        if ($this->relationLoaded('insumos') ? $this->insumos->isNotEmpty() : $this->insumos()->exists()) {
            return round($this->costoInsumos(), 4);
        }

        return (float) ($this->costo ?? 0);
    }

    public function costoInsumos(): float
    {
        $I = $this->relationLoaded('insumos') ? $this->insumos->loadMissing('insumo') : $this->insumos()->with('insumo')->get();

        return (float) $I->sum(fn ($x) => ($x->insumo?->costo ?? 0) * $x->cantidad);
    }

    /** cómo se compra: ['n' => 'Millar', 'f' => 1000]; si no se indicó, por unidad */
    public function unidadCompra(): array
    {
        $uc = $this->extra['uc'] ?? null;

        return is_array($uc) && ($uc['f'] ?? 0) > 1 ? ['n' => (string) ($uc['n'] ?? 'Unidad'), 'f' => (float) $uc['f']] : ['n' => 'Unidad', 'f' => 1.0];
    }

    public function fijarExtra(string $k, mixed $v): void
    {
        $e = $this->extra ?? [];
        if ($v === null || $v === '' || $v === []) {
            unset($e[$k]);
        } else {
            $e[$k] = $v;
        }
        $this->extra = $e ?: null;
    }

    /** % de ganancia sobre el precio (solo con un precio único y costo) */
    public function margen(): ?int
    {
        if (! $this->costo || $this->opciones->count() !== 1) {
            return null;
        }
        $p = $this->opciones->first()->precio;

        return $p ? (int) round(($p - $this->costo) / $p * 100) : null;
    }

    public function codigos(): array
    {
        return array_values(array_filter(preg_split('/[\s,;]+/', (string) $this->codigos_barra)));
    }

    public function minimo(): float
    {
        return $this->stock_minimo ?? Catalogos::STOCK_BAJO;
    }
}
