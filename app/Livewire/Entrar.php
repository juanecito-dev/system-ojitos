<?php

namespace App\Livewire;

use App\Models\Negocio;
use App\Models\Rol;
use App\Models\Usuario;
use App\Services\AccesoPin;
use App\Services\Bitacora;
use App\Support\NegocioActual;
use App\Support\Texto;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.login')]
#[Title('Iniciar sesión')]
class Entrar extends Component
{
    #[Locked]
    public ?int $negocioId = null;

    public string $codigo = '';

    public string $rol = '';

    public string $usuario = '';

    public string $pin = '';

    public bool $recordar = false;

    public string $error = '';

    public function mount()
    {
        if (Negocio::count() === 0) {
            return $this->redirectRoute('primer-uso');
        }
        $this->negocioId = app(NegocioActual::class)->id();
        $rec = json_decode((string) request()->cookie('lgUser'), true) ?: [];
        $this->usuario = $rec['u'] ?? '';
        $this->rol = $rec['r'] ?? '';
        $this->recordar = ! empty($rec['u']);
    }

    public function elegirNegocio(): void
    {
        $neg = Negocio::where('slug', Texto::usuario($this->codigo))->where('activo', true)->first();
        if (! $neg) {
            $this->error = 'No encontré ese negocio. Revisa el código que te dieron.';

            return;
        }
        Cookie::queue(Cookie::forever('negocio', $neg->slug));
        $this->negocioId = $neg->id;
        $this->error = '';
    }

    public function roles()
    {
        if (! $this->negocioId) {
            return collect();
        }
        $roles = Rol::withoutGlobalScopes()->where('negocio_id', $this->negocioId)->orderBy('orden')->get();
        $conUsuarios = $roles->filter(fn ($r) => Usuario::withoutGlobalScopes()->where('rol_id', $r->id)->where('activo', true)->exists());

        return $conUsuarios->isNotEmpty() ? $conUsuarios->values() : $roles;
    }

    public function ingresar(AccesoPin $acceso)
    {
        $this->error = '';
        if (! $this->negocioId) {
            return;
        }
        $clave = 'entrar:'.request()->ip();
        if (RateLimiter::tooManyAttempts($clave, 30)) {
            $this->error = 'Demasiados intentos desde este equipo. Espera un minuto.';

            return;
        }
        RateLimiter::hit($clave, 60);

        $uq = trim(preg_replace('/\s+/', ' ', Texto::norm($this->usuario)));
        $pin = trim($this->pin);
        if ($uq === '') {
            $this->error = 'Escribe tu usuario.';

            return;
        }
        if (! preg_match('/^\d{4,6}$/', $pin)) {
            $this->error = 'Escribe tu PIN (de 4 a 6 números).';

            return;
        }
        $rol = $this->roles()->firstWhere('uid', $this->rol) ?? $this->roles()->first();
        $cand = Usuario::withoutGlobalScopes()->with('rol')->where('negocio_id', $this->negocioId)->where('activo', true)->get()
            ->filter(fn ($u) => $u->usuario === Texto::usuario($uq) || trim(Texto::norm($u->nombre)) === $uq);
        $u = $cand->firstWhere('rol_id', $rol?->id) ?? $cand->first();
        $this->pin = '';
        if (! $u) {
            $this->error = 'Usuario, rol o PIN incorrectos.';

            return;
        }
        app(NegocioActual::class)->set(Negocio::find($this->negocioId));
        if ($b = $acceso->bloqueo($u)) {
            $this->error = $b;

            return;
        }
        if ($u->rol_id !== $rol?->id || ! $acceso->verificar($u, $pin)) {
            $acceso->fallo($u);
            $b = $acceso->bloqueo($u->fresh());
            $this->error = 'Usuario, rol o PIN incorrectos.'.($b ? ' '.$b : '');

            return;
        }
        $acceso->exito($u);
        RateLimiter::clear($clave);
        Cookie::queue($this->recordar ? Cookie::forever('lgUser', json_encode(['u' => $u->usuario, 'r' => $u->rol->uid])) : Cookie::forget('lgUser'));
        $u->forceFill(['ultimo_ingreso_at' => now()])->save();
        Auth::login($u);
        session()->regenerate();
        $eq = request()->cookie('equipo');
        Bitacora::registrar('entrada', $u->nombre.' entró como '.$u->rol->nombre.($eq ? ' en '.$eq : ''), $u);
        session()->flash('toast', 'Hola, '.$u->primerNombre());

        return $this->redirectRoute('inicio');
    }

    public function render()
    {
        $h = (int) now()->format('G');

        return view('livewire.entrar', [
            'saludo' => $h < 12 ? 'Buenos días' : ($h < 19 ? 'Buenas tardes' : 'Buenas noches'),
            'listaRoles' => $this->roles(),
        ]);
    }
}
