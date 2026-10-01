<?php

namespace App\Livewire;

use App\Models\LecturaContador;
use App\Models\Maquina;
use App\Services\Contadores;
use App\Services\ErrorNegocio;
use App\Support\Valida;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Locked;
use Livewire\Component;

/** Contadores de las máquinas dentro de Caja: lectura al cerrar, copias de prueba y cuadre (maqHTML del sistema anterior). */
class CajaContadores extends Component
{
    #[Locked]
    public string $dia = '';

    /** lo escrito en cada contador: "maquina:contador" => número */
    public array $lectura = [];

    /** lectura grande por confirmar: [key, valor, texto] */
    #[Locked]
    public ?array $confirmar = null;

    #[Locked]
    public ?string $mermaDe = null;

    public string $mermaN = '';

    #[Locked]
    public ?int $historialDe = null;

    public function mount(string $dia): void
    {
        $this->dia = Valida::fecha($dia) && $dia <= today()->toDateString() ? $dia : today()->toDateString();
    }

    public function esHoy(): bool
    {
        return $this->dia === today()->toDateString();
    }

    public function guardar(string $key, bool $confirmado = false): void
    {
        if (! $this->esHoy()) {
            return;
        }
        $raw = preg_replace('/\D/', '', (string) ($confirmado ? ($this->confirmar['valor'] ?? '') : ($this->lectura[$key] ?? '')));
        $this->confirmar = null;
        if ($raw === '') {
            $this->dispatch('toast', texto: 'Escribe el número del contador');

            return;
        }
        [$maq, $cont] = array_pad(explode(':', $key, 2), 2, '');
        try {
            $r = app(Contadores::class)->anotar($this->dia, $maq, $cont, (int) $raw, $confirmado);
        } catch (ErrorNegocio $e) {
            $this->dispatch('toast', texto: $e->getMessage());

            return;
        }
        if (isset($r['confirmar'])) {
            $this->confirmar = ['key' => $key, 'valor' => $raw, 'texto' => $r['confirmar']];

            return;
        }
        unset($this->lectura[$key]);
        $this->dispatch('toast', texto: $r['ok']);
    }

    public function abrirMerma(string $grupo): void
    {
        if ($this->esHoy() && isset(Contadores::GRUPOS[$grupo])) {
            $this->mermaDe = $grupo;
            $this->mermaN = '';
        }
    }

    public function anotarMerma(): void
    {
        $g = $this->mermaDe;
        $n = (int) preg_replace('/\D/', '', $this->mermaN);
        if (! $g || ! $this->esHoy()) {
            return;
        }
        try {
            app(Contadores::class)->merma($this->dia, $g, $n);
        } catch (ErrorNegocio $e) {
            $this->dispatch('toast', texto: $e->getMessage());

            return;
        }
        $this->mermaDe = null;
        $this->dispatch('toast', texto: 'Anotado: '.Contadores::numero($n).' copias de prueba');
    }

    public function verHistorial(int $id): void
    {
        $this->historialDe = Auth::user()->esAdmin() ? $id : null;
    }

    public function cerrarVentana(): void
    {
        $this->confirmar = null;
        $this->mermaDe = null;
        $this->historialDe = null;
    }

    public function render(Contadores $cont)
    {
        $yo = Auth::user();
        $maquinas = $cont->maquinas();
        $hist = $this->historialDe ? $maquinas->firstWhere('id', $this->historialDe) : null;

        return view('livewire.caja-contadores', [
            'cont' => $cont, 'maquinas' => $maquinas, 'admin' => $yo->esAdmin(), 'config' => $yo->puede('negocio'),
            'cuadre' => $maquinas->isNotEmpty() ? $cont->cuadre($this->dia) : [],
            'lecturas' => LecturaContador::where('fecha', $this->dia)->orderBy('ocurrido_at')->get(),
            'hist' => $hist, 'filas' => $hist instanceof Maquina ? $cont->historial($hist) : [],
        ]);
    }
}
