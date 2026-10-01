<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CompraPago extends Model
{
    protected $table = 'compra_pagos';

    public $timestamps = false;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'pagado_at' => 'datetime',
        ];
    }
}
