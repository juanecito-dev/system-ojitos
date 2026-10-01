<?php

namespace App\Models;

use App\Casts\Fecha;
use App\Models\Concerns\PerteneceANegocio;
use Illuminate\Database\Eloquent\Model;

class Dia extends Model
{
    use PerteneceANegocio;

    protected $table = 'dias';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'fecha' => Fecha::class,
            'cierre' => 'boolean',
            'caja_arqueo' => 'array',
        ];
    }
}
