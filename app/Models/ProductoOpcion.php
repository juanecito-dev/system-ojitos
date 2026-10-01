<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductoOpcion extends Model
{
    protected $table = 'producto_opciones';

    public $timestamps = false;

    protected $guarded = ['id'];
}
