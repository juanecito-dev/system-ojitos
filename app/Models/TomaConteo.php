<?php

namespace App\Models;

use App\Models\Concerns\PerteneceANegocio;
use Illuminate\Database\Eloquent\Model;

/** Lo contado en la toma de inventario, antes de aplicarlo. */
class TomaConteo extends Model
{
    use PerteneceANegocio;

    protected $table = 'toma_conteos';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'cantidad' => 'float',
        ];
    }
}
