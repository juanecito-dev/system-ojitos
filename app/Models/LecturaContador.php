<?php

namespace App\Models;

use App\Casts\Fecha;
use App\Models\Concerns\PerteneceANegocio;
use Illuminate\Database\Eloquent\Model;

class LecturaContador extends Model
{
    use PerteneceANegocio;

    protected $table = 'lecturas_contador';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'fecha' => Fecha::class,
            'ocurrido_at' => 'datetime',
        ];
    }
}
