<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Modelo de documento del sistema (igual para todos los negocios). Viene de database/modelos. */
class ModeloRedaccion extends Model
{
    protected $table = 'modelos_redaccion';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'definicion' => 'array',
            'niveles' => 'boolean',
            'activo' => 'boolean',
        ];
    }

    public function def(string $k, mixed $def = null): mixed
    {
        return $this->definicion[$k] ?? $def;
    }

    /** campos del formulario */
    public function campos(): array
    {
        return (array) $this->def('campos', []);
    }
}
