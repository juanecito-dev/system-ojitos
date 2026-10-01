<?php

namespace App\Models;

use App\Models\Concerns\PerteneceANegocio;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Proveedor extends Model
{
    use PerteneceANegocio;

    protected $table = 'proveedores';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'extra' => 'array',
        ];
    }

    public function compras(): HasMany
    {
        return $this->hasMany(Compra::class);
    }
}
