<?php

namespace App\Models;

use App\Models\Concerns\PerteneceANegocio;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Documento redactado. «datos» guarda la ficha como en el sistema anterior: los datos del formulario
 * van en datos.data (así se abren igual los importados). «texto» es el documento en el formato por líneas.
 */
class Documento extends Model
{
    use PerteneceANegocio;

    protected $table = 'documentos';

    protected $guarded = ['id'];

    public const ESTADOS = ['borrador' => ['p', 'Sin cobrar'], 'cobrado' => ['b', 'Cobrado'], 'entregado' => ['s', 'Entregado']];

    protected function casts(): array
    {
        return [
            'datos' => 'array',
            'cobrado_at' => 'datetime',
            'entregado_at' => 'datetime',
        ];
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    /** los datos del formulario */
    public function campos(): array
    {
        return (array) ($this->datos['data'] ?? []);
    }

    public function fijarCampos(array $d): void
    {
        $x = $this->datos ?? [];
        $x['data'] = $d;
        $this->datos = $x;
    }

    /** clientes relacionados (sus ids), como «clis» del sistema anterior */
    public function clientes(): array
    {
        return array_values(array_filter((array) ($this->datos['clientes'] ?? [])));
    }
}
