<?php

namespace App\Models;

use App\Models\Concerns\PerteneceANegocio;
use Illuminate\Database\Eloquent\Model;

class ModeloDocumento extends Model
{
    use PerteneceANegocio;

    protected $table = 'modelos_documento';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'datos' => 'array',
        ];
    }
}
