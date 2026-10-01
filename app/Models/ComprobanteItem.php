<?php

namespace App\Models;

use App\Models\Concerns\HeredaNegocio;
use Illuminate\Database\Eloquent\Model;

class ComprobanteItem extends Model
{
    use HeredaNegocio;

    public const PADRE = ['comprobantes', 'comprobante_id'];

    protected $table = 'comprobante_items';

    public $timestamps = false;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['cantidad' => 'float'];
    }
}
