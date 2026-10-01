<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ModuloNegocio extends Model
{
    protected $table = 'modulos_negocio';

    public $timestamps = false;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['activo' => 'boolean'];
    }
}
