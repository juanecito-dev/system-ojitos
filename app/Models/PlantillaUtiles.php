<?php

namespace App\Models;

use App\Models\Concerns\PerteneceANegocio;
use Illuminate\Database\Eloquent\Model;

class PlantillaUtiles extends Model
{
    use PerteneceANegocio;

    protected $table = 'plantillas_utiles';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'items' => 'array',
        ];
    }
}
