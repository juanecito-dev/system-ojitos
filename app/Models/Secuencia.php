<?php

namespace App\Models;

use App\Models\Concerns\PerteneceANegocio;
use Illuminate\Database\Eloquent\Model;

class Secuencia extends Model
{
    use PerteneceANegocio;

    protected $table = 'secuencias';

    protected $guarded = ['id'];
}
