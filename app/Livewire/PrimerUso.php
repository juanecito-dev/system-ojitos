<?php

namespace App\Livewire;

use App\Models\Negocio;
use App\Services\AccesoPin;
use App\Services\AltaNegocio;
use App\Services\ErrorNegocio;
use App\Services\ImportadorCopia;
use App\Support\Catalogos;
use App\Support\Texto;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Solo se ve cuando todavía no hay ningún negocio: crear el primero desde cero
 * o traer todo desde la copia de seguridad del sistema anterior.
 */
#[Layout('components.layouts.login')]
#[Title('Bienvenido')]
class PrimerUso extends Component
{
    use WithFileUploads;

    public string $modo = 'copia';   // copia | nuevo

    public $archivo;

    public string $negocio = '';

    public string $codigo = '';

    public string $nombre = '';

    public string $usuario = '';

    public string $rubro = 'imprenta';

    public string $pin = '';

    public string $pin2 = '';

    public string $error = '';

    public array $log = [];

    public function mount()
    {
        if (Negocio::exists()) {
            return $this->redirectRoute('login');
        }
    }

    public function importar(ImportadorCopia $imp)
    {
        $this->error = '';
        if (Negocio::exists()) {
            return $this->redirectRoute('login');
        }
        if (! $this->archivo) {
            $this->error = 'Elige el archivo de tu copia (ojitos-copia-….json).';

            return;
        }
        @set_time_limit(600);
        try {
            $d = json_decode(file_get_contents($this->archivo->getRealPath()), true);
            $imp->validar($d);
            $slug = Texto::usuario($this->codigo) ?: null;
            $res = $imp->importar($d, null, function ($t) {
                $this->log[] = $t;
            }, $slug);
        } catch (ErrorNegocio $e) {
            $this->error = $e->getMessage();

            return;
        } catch (\Throwable $e) {
            report($e);
            $this->error = ImportadorCopia::ERROR_INESPERADO;

            return;
        }
        Cookie::queue(Cookie::forever('negocio', $res['negocio']->slug));
        session()->flash('toast', 'Copia importada. Entra con tu usuario y PIN de siempre.');

        return $this->redirectRoute('login');
    }

    public function crear(AltaNegocio $alta)
    {
        $this->error = '';
        if (Negocio::exists()) {
            return $this->redirectRoute('login');
        }
        $u = Texto::usuario($this->usuario ?: Texto::primerNombre($this->nombre));
        $this->error = match (true) {
            trim($this->negocio) === '' => 'Escribe el nombre de tu negocio.',
            trim($this->nombre) === '' => 'Escribe tu nombre.',
            strlen($u) < 3 => 'El usuario para entrar debe tener al menos 3 letras o números, sin espacios.',
            default => AccesoPin::problemaPinNuevo($this->pin, $this->pin2),
        };
        if ($this->error) {
            return;
        }
        $neg = $alta->crear([
            'nombre' => trim($this->negocio), 'slug' => Texto::usuario($this->codigo) ?: null, 'giro' => Catalogos::RUBROS[$this->rubro][0] ?? null,
            'titular' => trim($this->nombre), 'rubro' => $this->rubro,
        ], ['nombre' => trim($this->nombre), 'usuario' => $u, 'pin' => $this->pin], $this->rubro !== 'otro');
        Cookie::queue(Cookie::forever('negocio', $neg->slug));
        $admin = $neg->usuarios()->first();
        Auth::login($admin);
        session()->regenerate();
        session()->flash('toast', 'Listo, '.$admin->primerNombre().'. Crea a tu ayudante en Usuarios y roles.');

        return $this->redirectRoute('inicio');
    }

    public function render()
    {
        return view('livewire.primer-uso');
    }
}
