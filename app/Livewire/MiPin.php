<?php

namespace App\Livewire;

use App\Services\AccesoPin;
use App\Services\Bitacora;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Livewire\Attributes\On;
use Livewire\Component;

class MiPin extends Component
{
    public bool $abierto = false;

    public string $actual = '';

    public string $nuevo = '';

    public string $repite = '';

    public string $error = '';

    #[On('abrirMiPin')]
    public function abrir(): void
    {
        $this->reset();
        $this->abierto = true;
    }

    public function cerrar(): void
    {
        $this->reset();
    }

    public function guardar(AccesoPin $acceso): void
    {
        $yo = Auth::user();
        if ($b = $acceso->bloqueo($yo)) {
            $this->error = $b;

            return;
        }
        if (! $acceso->verificar($yo, trim($this->actual))) {
            $acceso->fallo($yo);
            $this->error = $acceso->bloqueo($yo->fresh()) ?: 'El PIN actual no es correcto.';

            return;
        }
        $this->error = AccesoPin::problemaPinNuevo(trim($this->nuevo), trim($this->repite), trim($this->actual));
        if ($this->error) {
            return;
        }
        $yo->forceFill(['pin' => Hash::make(trim($this->nuevo)), 'pin_legado' => null, 'pin_sal' => null, 'pin_largo' => strlen(trim($this->nuevo))])->save();
        $acceso->exito($yo);
        Bitacora::registrar('usuario', $yo->nombre.' cambió su PIN');
        $this->reset();
        $this->dispatch('toast', texto: 'PIN cambiado. Desde ahora entra con el nuevo');
    }

    public function render()
    {
        return view('livewire.mi-pin');
    }
}
