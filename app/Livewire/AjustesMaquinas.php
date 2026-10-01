<?php

namespace App\Livewire;

use App\Models\Maquina;
use App\Models\Producto;
use App\Services\Bitacora;
use App\Services\Contadores;
use App\Support\NegocioActual;
use App\Support\Texto;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Locked;
use Livewire\Component;

/** Configuración › Máquinas: cada fotocopiadora con sus contadores y qué productos gastan contador (maqCfgHTML). */
class AjustesMaquinas extends Component
{
    #[Locked]
    public ?int $quitando = null;

    public function boot(): void
    {
        abort_unless(Auth::user()?->puede('negocio'), 403);
    }

    private function prods(): array
    {
        return app(Contadores::class)->prods();
    }

    private function guardarProds(array $p): void
    {
        $neg = app(NegocioActual::class)->obligatorio();
        $neg->guardarAjuste('maquinas', $p ? ['prods' => $p] : null);
    }

    public function agregar(): void
    {
        if (! $this->prods()) {
            $this->guardarProds(Contadores::PRODS_INICIO);
        }
        $n = Maquina::count() + 1;
        Maquina::create(['uid' => Texto::nuevoUid(), 'nombre' => 'Máquina '.$n, 'contadores' => [['id' => Texto::nuevoUid(), 'n' => 'Contador', 'tipo' => 'bn']], 'orden' => (int) Maquina::max('orden') + 1]);
        Bitacora::registrar('config', 'Agregó la máquina '.$n);
        $this->dispatch('toast', texto: 'Máquina agregada: ponle su nombre');
    }

    public function nombre(int $id, string $v): void
    {
        $m = Maquina::find($id);
        $v = mb_substr(trim($v), 0, 60);
        if ($m && $v !== '' && $v !== $m->nombre) {
            Bitacora::registrar('config', 'Renombró la máquina «'.$m->nombre.'» a «'.$v.'»');
            $m->update(['nombre' => $v]);
            $this->dispatch('toast', texto: 'Guardado');
        }
    }

    public function pedirQuitar(int $id): void
    {
        $this->quitando = Maquina::whereKey($id)->exists() ? $id : null;
    }

    public function quitar(): void
    {
        $m = $this->quitando ? Maquina::find($this->quitando) : null;
        $this->quitando = null;
        if ($m) {
            $m->delete();
            Bitacora::registrar('config', 'Quitó la máquina '.$m->nombre);
            $this->dispatch('toast', texto: 'Quitada: '.$m->nombre);
        }
    }

    /** cambia un contador: n (nombre) o tipo */
    public function contador(int $id, string $cid, string $campo, string $v): void
    {
        $m = Maquina::find($id);
        $v = trim($v);
        if (! $m || ! in_array($campo, ['n', 'tipo'], true) || $v === '' || ($campo === 'tipo' && ! isset(Contadores::TIPOS[$v]))) {
            return;
        }
        $cs = collect($m->contadores ?? [])->map(fn ($c) => $c['id'] === $cid ? [...$c, $campo => mb_substr($v, 0, 30)] : $c)->all();
        $m->update(['contadores' => $cs]);
        if ($campo === 'tipo') {
            $this->ajustarProds();
        }
        $this->dispatch('toast', texto: 'Guardado');
    }

    public function agregarContador(int $id): void
    {
        $m = Maquina::find($id);
        if ($m) {
            $m->update(['contadores' => [...($m->contadores ?? []), ['id' => Texto::nuevoUid(), 'n' => 'Color', 'tipo' => 'color']]]);
            $this->ajustarProds();
        }
    }

    public function quitarContador(int $id, string $cid): void
    {
        $m = Maquina::find($id);
        if ($m && count($m->contadores ?? []) > 1) {
            $m->update(['contadores' => collect($m->contadores)->reject(fn ($c) => $c['id'] === $cid)->values()->all()]);
            $this->ajustarProds();
        }
    }

    /** si ahora se cuadra todo junto (contador total) o por separado, los productos siguen contando en el grupo que corresponde */
    private function ajustarProds(): void
    {
        $gs = app(Contadores::class)->grupos();
        $P = $this->prods();
        foreach ($P as $uid => $p) {
            if ($gs === ['total'] && ($p['g'] ?? '') !== 'total') {
                $P[$uid]['g'] = 'total';
            } elseif ($gs !== ['total'] && ($p['g'] ?? '') === 'total') {
                $P[$uid]['g'] = $this->grupoPorColor($uid);
            }
        }
        $this->guardarProds($P);
    }

    private function grupoPorColor(string $uid): string
    {
        return Producto::where('uid', $uid)->value('color') === 't-bn' ? 'bn' : 'color';
    }

    public function prodGrupo(string $uid, string $g): void
    {
        $P = $this->prods();
        if ($g === '') {
            unset($P[$uid]);
        } elseif (isset(Contadores::GRUPOS[$g]) && Producto::where('uid', $uid)->exists()) {
            $P[$uid] = ['g' => $g, 'f' => $P[$uid]['f'] ?? 1];
        } else {
            return;
        }
        $this->guardarProds($P);
        $this->dispatch('toast', texto: 'Guardado');
    }

    public function prodHojas(string $uid, string $f): void
    {
        $P = $this->prods();
        $f = (int) $f;
        if (isset($P[$uid]) && $f >= 1 && $f <= 10) {
            $P[$uid]['f'] = $f;
            $this->guardarProds($P);
            $this->dispatch('toast', texto: 'Guardado');
        }
    }

    public function render(Contadores $cont)
    {
        return view('livewire.ajustes-maquinas', [
            'maquinas' => $cont->maquinas(),
            'total' => $cont->grupos() === ['total'],
            'prods' => $cont->prods(),
            'candidatos' => Producto::with('opciones')->whereIn('color', ['t-bn', 't-color', 't-foto'])->orderBy('orden')->get()->filter(fn ($p) => $p->opciones->isNotEmpty()),
            'quitar' => $this->quitando ? Maquina::find($this->quitando) : null,
        ]);
    }
}
