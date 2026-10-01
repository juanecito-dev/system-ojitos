<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ComprobanteItem extends Model
{
    protected $table = 'comprobante_items';

    public $timestamps = false;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['cantidad' => 'float'];
    }
}
