<?php

namespace App\Models;

use App\Models\Concerns\PerteneceANegocio;
use App\Support\Texto;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;

class Usuario extends Authenticatable
{
    use PerteneceANegocio;

    protected $table = 'usuarios';

    protected $guarded = ['id'];

    protected $hidden = ['pin', 'pin_legado', 'pin_sal', 'remember_token'];

    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
            'bloqueado_hasta' => 'datetime',
            'ultimo_ingreso_at' => 'datetime',
        ];
    }

    public function getAuthPassword(): string
    {
        return (string) $this->pin;
    }

    public function rol(): BelongsTo
    {
        return $this->belongsTo(Rol::class, 'rol_id');
    }

    public function esAdmin(): bool
    {
        return (bool) $this->rol?->es_admin;
    }

    public function puede(string $permiso): bool
    {
        return (bool) $this->rol?->puede($permiso);
    }

    public function primerNombre(): string
    {
        return Texto::primerNombre($this->nombre);
    }

    public function inicial(): string
    {
        return Texto::inicial($this->nombre);
    }

    /** color del círculo con la inicial (c0…c4), igual que antes */
    public function colorAvatar(): string
    {
        $s = 0;
        foreach (str_split((string) $this->uid) as $ch) {
            $s += ord($ch);
        }

        return 'c'.($s % 5);
    }

    /** prefijo de sus notas de venta: inicial + 2 últimas letras de su id (A1, J2…) */
    public function codigoTicket(): string
    {
        return $this->inicial().strtoupper(substr((string) $this->uid, -2));
    }
}
