<?php

namespace App\Models;

use App\Models\Concerns\PerteneceANegocio;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Cliente extends Model
{
    use PerteneceANegocio;

    protected $table = 'clientes';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'alias' => 'array',
            'extra' => 'array',
            'ultima_visita_at' => 'datetime',
        ];
    }

    public function movimientos(): HasMany
    {
        return $this->hasMany(ClienteMovimiento::class);
    }

    /** lo que debe: fiados − abonos, en céntimos */
    public function saldo(): int
    {
        return (int) $this->movimientos()->selectRaw("COALESCE(SUM(CASE WHEN tipo = 'fiado' THEN monto ELSE -monto END), 0) AS s")->value('s');
    }
}
