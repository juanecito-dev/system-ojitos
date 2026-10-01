<?php

namespace App\Livewire\Concerns;

use App\Models\Usuario;
use App\Services\AccesoPin;
use App\Services\Bitacora;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Locked;

/**
 * Si el usuario no tiene un permiso, alguien que sí lo tiene lo autoriza con su PIN
 * en este mismo equipo (igual que autorizar() del sistema anterior).
 *
 * Uso en una acción:  if (! $this->requiere('anular', 'Anular la venta…', 'anular', [$id])) return;
 */
trait ConAutorizacion
{
    /** ventana abierta: permiso, que, accion, params, soloAdmin, forzar, incluirme, motivo, titulo */
    #[Locked]
    public ?array $autz = null;

    public string $autzPin = '';

    public ?int $autzUsuario = null;

    public string $autzError = '';

    /** quién autorizó la acción que se está ejecutando (solo dura esta petición) */
    protected ?Usuario $autorizo = null;

    protected ?string $autorizoPermiso = null;

    protected function requiere(string $permiso, string $que, string $accion, array $params = [], array $opt = []): bool
    {
        if ($this->autorizo && $this->autorizoPermiso === $permiso) {
            return true;
        }
        $yo = Auth::user();
        if (empty($opt['forzar']) && $yo->puede($permiso)) {
            return true;
        }
        $ap = $this->autorizadores($permiso, $opt);
        if ($ap->isEmpty()) {
            $this->dispatch('toast', texto: 'Esto necesita la autorización de un administrador');

            return false;
        }
        $this->autz = [
            'permiso' => $permiso, 'que' => $que, 'accion' => $accion, 'params' => $params,
            'huella' => $this->huellaAutorizacion(),
            'soloAdmin' => ! empty($opt['soloAdmin']), 'incluirme' => ! empty($opt['incluirme']),
            'titulo' => $opt['titulo'] ?? 'Necesita autorización',
            'motivo' => $opt['motivo'] ?? (empty($opt['incluirme'])
                ? 'Tu usuario no tiene este permiso: pide a '.($ap->count() === 1 ? $ap->first()->primerNombre() : 'un encargado').' que ponga su PIN.' : ''),
        ];
        $this->autzUsuario = $ap->contains('id', $yo->id) ? $yo->id : $ap->first()->id;
        $this->autzPin = '';
        $this->autzError = '';

        return false;
    }

    /**
     * Lo que se está autorizando (montos, pedido, cliente…): todas las propiedades públicas del componente,
     * menos la ventana del PIN. Si cambian mientras la ventana está abierta, el PIN ya no vale:
     * el administrador autorizó «un gasto de S/ 5», no lo que el navegador mande después.
     */
    protected function huellaAutorizacion(): string
    {
        $fuera = ['autz', 'autzPin', 'autzUsuario', 'autzError', 'error', 'concedidos'];

        return hash('sha256', (string) json_encode($this->except($fuera)));
    }

    protected function autorizadores(string $permiso, array $opt)
    {
        $yo = Auth::user();

        return Usuario::with('rol.permisos')->where('activo', true)->orderBy('orden')->get()
            ->filter(fn ($u) => (! empty($opt['incluirme']) || $u->id !== $yo->id)
                && ($u->esAdmin() || (empty($opt['soloAdmin']) && $u->puede($permiso))))
            ->values();
    }

    public function listaAutorizadores()
    {
        return $this->autz ? $this->autorizadores($this->autz['permiso'], ['soloAdmin' => $this->autz['soloAdmin'], 'incluirme' => $this->autz['incluirme']]) : collect();
    }

    public function confirmarAutorizacion(AccesoPin $acceso): void
    {
        if (! $this->autz) {
            return;
        }
        $u = $this->listaAutorizadores()->firstWhere('id', $this->autzUsuario);
        $pin = trim($this->autzPin);
        if (! $u || $pin === '') {
            return;
        }
        if (! hash_equals($this->autz['huella'] ?? '', $this->huellaAutorizacion())) {
            $this->autz = null;
            $this->autzPin = '';
            $this->dispatch('toast', texto: 'Cambió lo que se iba a autorizar. Revisa y vuelve a intentarlo.');

            return;
        }
        if ($b = $acceso->bloqueo($u)) {
            $this->autzError = $b;

            return;
        }
        if (! $acceso->verificar($u, $pin)) {
            $acceso->fallo($u);
            $this->autzError = $acceso->bloqueo($u->fresh()) ?: 'PIN incorrecto';
            $this->autzPin = '';

            return;
        }
        $acceso->exito($u);
        $a = $this->autz;
        if ($u->id !== Auth::id()) {
            Bitacora::registrar('autoriza', $a['que'].' — autorizó '.$u->nombre);
        }
        $this->autz = null;
        $this->autzPin = '';
        $this->autorizo = $u;
        $this->autorizoPermiso = $a['permiso'];
        $this->{$a['accion']}(...$a['params']);
        $this->autorizo = null;
        $this->autorizoPermiso = null;
    }

    public function cancelarAutorizacion(): void
    {
        $this->autz = null;
        $this->autzPin = '';
    }
}
