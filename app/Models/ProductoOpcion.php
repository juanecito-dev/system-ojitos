<?php

namespace App\Models;

use App\Models\Concerns\HeredaNegocio;
use Illuminate\Database\Eloquent\Model;

class ProductoOpcion extends Model
{
    use HeredaNegocio;

    public const PADRE = ['productos', 'producto_id'];

    protected $table = 'producto_opciones';

    public $timestamps = false;

    protected $guarded = ['id'];
}
