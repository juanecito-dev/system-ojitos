<?php

namespace App\Models;

use App\Casts\Fecha;
use App\Models\Concerns\PerteneceANegocio;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Compra extends Model
{
    use PerteneceANegocio;

    protected $table = 'compras';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'fecha' => Fecha::class,
            'vence' => Fecha::class,
            'extra' => 'array',
        ];
    }

    public function proveedor(): BelongsTo
    {
        return $this->belongsTo(Proveedor::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(CompraItem::class)->orderBy('orden');
    }

    public function pagado(): int
    {
        return (int) ($this->relationLoaded('pagos') ? $this->pagos->sum('monto') : $this->pagos()->sum('monto'));
    }

    public function saldo(): int
    {
        return max(0, $this->total - $this->pagado());
    }

    public function vencida(): bool
    {
        return $this->saldo() > 0 && $this->vence && $this->vence->lt(today());
    }

    /** a crédito, con saldo y vence entre hoy y dentro de N días */
    public function venceEn(int $dias): bool
    {
        return $this->saldo() > 0 && $this->vence && $this->vence->gte(today()) && $this->vence->lte(today()->addDays($dias));
    }

    public function pagos(): HasMany
    {
        return $this->hasMany(CompraPago::class);
    }
}
