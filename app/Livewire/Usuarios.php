<?php

namespace App\Livewire;

use App\Models\Actividad;
use App\Models\Rol;
use App\Models\Usuario;
use App\Models\VentaAnulada;
use App\Services\AccesoPin;
use App\Services\Bitacora;
use App\Support\Catalogos;
use App\Support\Dinero;
use App\Support\Texto;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Usuarios y roles')]
class Usuarios extends Component
{
    /** formulario de usuario: null cerrado, 0 nuevo, id editar */
    #[Locked]
    public ?int $editUsuario = null;

    public array $u = ['nombre' => '', 'usuario' => '', 'rol' => null, 'pin' => '', 'pin2' => '', 'desbloquear' => true];

    #[Locked]
    public ?int $editRol = null;

    public array $r = ['nombre' => '', 'permisos' => [], 'descMax' => '', 'descPct' => ''];

    #[Locked]
    public ?int $desactivando = null;

    public string $error = '';

    public int $actDias = 1;

    public string $actUsuario = '';

    public string $actTipo = '';

    public function mount(): void
    {
        abort_unless(Auth::user()->esAdmin(), 403);
    }

    private function admins(): int
    {
        return Usuario::where('activo', true)->whereHas('rol', fn ($q) => $q->where('es_admin', true))->count();
    }

    // ------------------------------------------------------------ usuarios

    public function nuevoUsuario(): void
    {
        $this->error = '';
        $this->editUsuario = 0;
        $this->u = ['nombre' => '', 'usuario' => '', 'rol' => Rol::where('uid', 'vendedor')->value('id') ?? Rol::where('es_admin', false)->value('id'), 'pin' => '', 'pin2' => '', 'desbloquear' => true];
    }

    public function editarUsuario(int $id): void
    {
        $x = Usuario::find($id);
        if (! $x) {
            return;
        }
        $this->error = '';
        $this->editUsuario = $id;
        $this->u = ['nombre' => $x->nombre, 'usuario' => $x->usuario, 'rol' => $x->rol_id, 'pin' => '', 'pin2' => '', 'desbloquear' => true];
    }

    public function guardarUsuario(): void
    {
        $x = $this->editUsuario ? Usuario::with('rol')->find($this->editUsuario) : null;
        if ($this->editUsuario && ! $x) {
            return;
        }
        $n = trim($this->u['nombre']);
        $us = Texto::usuario($this->u['usuario'] ?: Texto::primerNombre($n));
        $pin = trim($this->u['pin']);
        $ultimoAdmin = $x && $x->esAdmin() && $this->admins() <= 1;
        $rol = Rol::find($this->u['rol']);
        $this->error = match (true) {
            $n === '' => 'Escribe el nombre.',
            Usuario::where('id', '!=', $x?->id)->get()->contains(fn ($o) => Texto::norm($o->nombre) === Texto::norm($n)) => 'Ya existe un usuario con ese nombre.',
            strlen($us) < 3 => 'El usuario para entrar debe tener al menos 3 letras o números, sin espacios.',
            Usuario::where('id', '!=', $x?->id)->where('usuario', $us)->exists() => 'Ese usuario para entrar ya lo tiene otra persona. Elige otro (por ejemplo, '.$us.'2).',
            ! $rol => 'Elige el rol.',
            (! $x || $pin !== '') && ($p = AccesoPin::problemaPinNuevo($pin, trim($this->u['pin2']))) !== '' => $p,
            default => '',
        };
        if ($this->error) {
            return;
        }
        $antes = $x ? ['n' => $x->nombre, 'r' => $x->rol->nombre] : null;
        $datos = ['nombre' => $n, 'usuario' => $us];
        if (! $ultimoAdmin) {
            $datos['rol_id'] = $rol->id;
        }
        if ($pin !== '') {
            $datos += ['pin' => Hash::make($pin), 'pin_legado' => null, 'pin_sal' => null, 'pin_largo' => strlen($pin)];
        }
        if ($x) {
            $x->forceFill($datos)->save();
            if ($this->u['desbloquear'] && app(AccesoPin::class)->bloqueo($x)) {
                app(AccesoPin::class)->desbloquear($x);
            }
            $x->load('rol');
            Bitacora::registrar('usuario', 'Editó a '.$n.($antes['r'] !== $x->rol->nombre ? ' (rol: '.$antes['r'].' → '.$x->rol->nombre.')' : '')
                .($antes['n'] !== $n ? ' (antes '.$antes['n'].')' : '').($pin !== '' ? ' y cambió su PIN' : ''));
            $this->dispatch('toast', texto: 'Cambios guardados');
        } else {
            $x = Usuario::create($datos + ['uid' => Texto::nuevoUid(), 'orden' => (int) Usuario::max('orden') + 1]);
            Bitacora::registrar('usuario', 'Creó el usuario '.$n.' como '.$rol->nombre);
            $this->dispatch('toast', texto: $n.' ya puede entrar: usuario «'.$us.'», rol '.$rol->nombre.' y su PIN');
        }
        $this->editUsuario = null;
    }

    public function pedirDesactivar(int $id): void
    {
        $x = Usuario::with('rol')->find($id);
        if (! $x || $x->id === Auth::id()) {
            return;
        }
        if ($x->esAdmin() && $this->admins() <= 1) {
            $this->dispatch('toast', texto: 'Debe quedar al menos un administrador activo');

            return;
        }
        $this->desactivando = $id;
    }

    public function desactivar(): void
    {
        $x = $this->desactivando ? Usuario::with('rol')->find($this->desactivando) : null;
        $this->desactivando = null;
        if (! $x || $x->id === Auth::id() || ($x->esAdmin() && $this->admins() <= 1)) {
            return;
        }
        $x->update(['activo' => false]);
        Bitacora::registrar('usuario', 'Desactivó a '.$x->nombre);
        $this->dispatch('toast', texto: $x->primerNombre().' ya no puede entrar');
    }

    public function activar(int $id): void
    {
        $x = Usuario::find($id);
        if ($x) {
            $x->update(['activo' => true]);
            Bitacora::registrar('usuario', 'Activó a '.$x->nombre);
            $this->dispatch('toast', texto: $x->primerNombre().' puede entrar otra vez con su PIN');
        }
    }

    // ------------------------------------------------------------ roles

    public function nuevoRol(): void
    {
        $this->error = '';
        $this->editRol = 0;
        $this->r = ['nombre' => '', 'permisos' => Catalogos::PERMISOS_VENDEDOR, 'descMax' => '', 'descPct' => ''];
    }

    public function editarRol(int $id): void
    {
        $x = Rol::with('permisos')->find($id);
        if (! $x || $x->es_admin) {
            return;
        }
        $this->error = '';
        $this->editRol = $id;
        $this->r = ['nombre' => $x->nombre, 'permisos' => $x->claves(), 'descMax' => $x->desc_max ? Dinero::n($x->desc_max) : '', 'descPct' => (string) ($x->desc_pct ?: '')];
    }

    public function guardarRol(): void
    {
        $x = $this->editRol ? Rol::find($this->editRol) : null;
        if (($this->editRol && ! $x) || $x?->es_admin) {
            return;
        }
        $n = trim($this->r['nombre']);
        if ($n === '') {
            $this->error = 'Escribe el nombre del rol.';

            return;
        }
        if (Rol::where('id', '!=', $x?->id)->get()->contains(fn ($o) => Texto::norm($o->nombre) === Texto::norm($n))) {
            $this->error = 'Ya existe un rol con ese nombre.';

            return;
        }
        $dm = Dinero::aCentimos($this->r['descMax']);
        $dp = (int) $this->r['descPct'];
        if (trim((string) $this->r['descPct']) !== '' && (! preg_match('/^\d{1,3}$/', trim((string) $this->r['descPct'])) || $dp > 100)) {
            $this->error = 'El tope de descuento en % es un número de 1 a 100 (o déjalo vacío para no poner tope).';

            return;
        }
        if (trim((string) $this->r['descMax']) !== '' && ! ($dm > 0)) {
            $this->error = 'El tope de descuento en soles debe ser un monto, por ejemplo 2.00 (o déjalo vacío).';

            return;
        }
        $datos = ['nombre' => $n, 'desc_max' => $dm > 0 ? $dm : null, 'desc_pct' => $dp > 0 && $dp <= 100 ? $dp : null];
        $x ??= new Rol(['uid' => 'r_'.Texto::nuevoUid(), 'orden' => (int) Rol::max('orden') + 1]);
        $x->fill($datos)->save();
        $x->sincronizarPermisos(array_values(array_intersect($this->r['permisos'], array_keys(Catalogos::permisos()))));
        Bitacora::registrar('rol', ($this->editRol ? 'Cambió los permisos del rol ' : 'Creó el rol ').$n);
        $this->editRol = null;
        $this->dispatch('toast', texto: 'Rol guardado');
    }

    public function eliminarRol(int $id): void
    {
        $x = Rol::find($id);
        if (! $x || $x->es_admin || Usuario::where('rol_id', $id)->exists()) {
            return;
        }
        Bitacora::registrar('rol', 'Eliminó el rol '.$x->nombre);
        $x->delete();
    }

    public function cerrar(): void
    {
        $this->editUsuario = null;
        $this->editRol = null;
        $this->desactivando = null;
    }

    public function render(AccesoPin $acceso)
    {
        $roles = Rol::with('permisos')->withCount(['usuarios' => fn ($q) => $q->where('activo', true)])->orderBy('orden')->get();
        $usuarios = Usuario::with('rol')->orderByDesc('activo')->orderBy('orden')->get();
        $desde = today()->subDays(max(1, $this->actDias) - 1)->toDateString();
        $act = Actividad::where('fecha', '>=', $desde)->when($this->actUsuario, fn ($q) => $q->where('usuario_id', $this->actUsuario))
            ->when($this->actTipo && $this->actTipo !== 'anula', fn ($q) => $q->where('tipo', $this->actTipo))
            ->when($this->actTipo === 'anula', fn ($q) => $q->whereRaw('1 = 0'))
            ->orderByDesc('ocurrido_at')->limit(200)->get()
            ->map(fn ($a) => ['t' => $a->ocurrido_at, 'tipo' => $a->tipo, 'det' => $a->detalle, 'vend' => $a->vendedor, 'eq' => $a->equipo]);
        if (! $this->actTipo || $this->actTipo === 'anula') {
            $an = VentaAnulada::where('fecha', '>=', $desde)->when($this->actUsuario, fn ($q) => $q->where('usuario_id', $this->actUsuario))->get()
                ->map(fn ($x) => ['t' => $x->anulada_at, 'tipo' => 'anula', 'det' => ($x->tipo === 'deshecha' ? 'Deshizo' : 'Anuló').' la venta '.($x->numero ?? '').' de '.Dinero::s($x->total).($x->motivo ? ' — '.$x->motivo : ''), 'vend' => $x->por, 'eq' => null]);
            $act = $act->concat($an)->sortByDesc('t')->take(200)->values();
        }

        return view('livewire.usuarios', [
            'usuarios' => $usuarios, 'roles' => $roles, 'act' => $act, 'acceso' => $acceso,
            'ultimoAdmin' => $this->editUsuario && ($e = $usuarios->firstWhere('id', $this->editUsuario)) && $e->esAdmin() && $this->admins() <= 1,
            'editado' => $this->editUsuario ? $usuarios->firstWhere('id', $this->editUsuario) : null,
            'porDesactivar' => $this->desactivando ? $usuarios->firstWhere('id', $this->desactivando) : null,
        ]);
    }
}
