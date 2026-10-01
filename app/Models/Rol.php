<?php

namespace App\Models;

use App\Models\Concerns\PerteneceANegocio;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Rol extends Model
{
    use PerteneceANegocio;

    protected $table = 'roles';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['es_admin' => 'boolean'];
    }

    public function permisos(): BelongsToMany
    {
        return $this->belongsToMany(Permiso::class, 'permiso_rol', 'rol_id', 'permiso_id');
    }

    public function usuarios(): HasMany
    {
        return $this->hasMany(Usuario::class, 'rol_id');
    }

    /** claves de permisos: ['vender', 'caja', …] */
    public function claves(): array
    {
        return $this->permisos->pluck('clave')->all();
    }

    public function puede(string $permiso): bool
    {
        return $this->es_admin || in_array($permiso, $this->claves(), true);
    }

    /** Asigna los permisos por su clave */
    public function sincronizarPermisos(array $claves): void
    {
        $ids = Permiso::whereIn('clave', $claves)->pluck('id');
        $this->permisos()->sync($ids);
        $this->unsetRelation('permisos');
    }
}
