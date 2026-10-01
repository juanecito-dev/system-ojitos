<?php

namespace App\Models;

use App\Models\Concerns\PerteneceANegocio;
use Illuminate\Database\Eloquent\Model;

class Maquina extends Model
{
    use PerteneceANegocio;

    protected $table = 'maquinas';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'contadores' => 'array',
        ];
    }
}
