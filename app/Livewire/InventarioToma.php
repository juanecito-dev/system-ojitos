<?php

namespace App\Livewire;

use App\Models\Producto;
use App\Models\TomaConteo;
use App\Services\Inventario as Inv;
use App\Services\Stock;
use App\Support\Texto;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Toma de inventario: contar todo seguido (con el lector cada lectura suma 1) y aplicar las diferencias juntas.
 * Lo contado se guarda en el servidor, así que se puede seguir desde otro equipo.
 */
#[Title('Toma de inventario')]
class InventarioToma extends Component
{
    public string $buscar = '';

    public string $grupo = '';

    #[Locked]
    public ?int $ultimo = null;

    #[Locked]
    public ?string $confirmar = null;   // aplicar | borrar

    private function conStock()
    {
        $st = app(Inv::class)->stock();

        return Producto::orderBy('orden')->get()->filter(fn ($p) => array_key_exists($p->id, $st));
    }

    /** lo escrito en un producto: vacío lo quita de lo contado */
    public function fijar(int $id, string $valor): void
    {
        $v = str_replace(',', '.', trim($valor));
        if ($v !== '' && (! is_numeric($v) || (float) $v < 0)) {
            $this->dispatch('toast', texto: 'Escribe un número, por ejemplo 24');

            return;
        }
        app(Inv::class)->tomaFijar($id, $v === '' ? null : (float) $v, Auth::user());
        $this->ultimo = $id;
    }

    /** Enter en el buscador: si es un código de barras suma 1; si no, va al primero que coincide */
    public function escanear(): void
    {
        $code = trim($this->buscar);
        if ($code === '') {
            return;
        }
        $P = $this->conStock();
        $p = $P->first(fn ($x) => in_array($code, $x->codigos(), true));
        if ($p) {
            $n = app(Inv::class)->tomaSumar($p->id, Auth::user());
            $this->buscar = '';
            $this->ultimo = $p->id;
            $this->dispatch('toast', texto: $p->nombre.': '.Stock::formato($n));

            return;
        }
        $todos = Producto::get();
        if ($otro = $todos->first(fn ($x) => in_array($code, $x->codigos(), true))) {
            $this->buscar = '';
            $this->dispatch('toast', texto: $otro->nombre.' no tiene control de stock');

            return;
        }
        $q = Texto::norm($code);
        $h = $P->first(fn ($x) => str_contains(Texto::norm($x->nombre.' '.$x->codigos_barra), $q));
        $this->ultimo = $h?->id;
        if ($h) {
            $this->dispatch('enfocar-conteo', id: $h->id);
        }
    }

    /** el lector de códigos pasó sin que el cursor esté en el buscador */
    public function escanearCodigo(string $codigo): void
    {
        $this->buscar = mb_substr($codigo, 0, 60);
        $this->escanear();
    }

    public function pedir(string $que): void
    {
        $this->confirmar = in_array($que, ['aplicar', 'borrar'], true) && TomaConteo::exists() ? $que : null;
    }

    public function aplicar(): void
    {
        $this->confirmar = null;
        $r = app(Inv::class)->tomaAplicar(Auth::user());
        session()->flash('toast', 'Conteo aplicado a '.$r['n'].' productos');
        $this->redirectRoute('inventario');
    }

    public function borrar(): void
    {
        $this->confirmar = null;
        TomaConteo::query()->delete();
        $this->ultimo = null;
        $this->dispatch('toast', texto: 'Se borró lo contado');
    }

    public function cancelar(): void
    {
        $this->confirmar = null;
    }

    public function render(Inv $inv)
    {
        $st = $inv->stock();
        $all = $this->conStock();
        $grupos = $all->pluck('grupo')->unique()->values();
        $q = Texto::norm($this->buscar);
        $lista = $all->filter(fn ($p) => ($this->grupo === '' || $p->grupo === $this->grupo) && ($q === '' || str_contains(Texto::norm($p->nombre.' '.$p->codigos_barra), $q)));
        $T = TomaConteo::get()->keyBy('producto_id')->filter(fn ($t) => array_key_exists($t->producto_id, $st));
        $dif = fn ($id) => round($T[$id]->cantidad - $st[$id], 3);
        $perd = $T->keys()->sum(function ($id) use ($dif, $all) {
            $d = $dif($id);

            return $d < 0 ? -$d * ($all->firstWhere('id', $id)?->costo ?? 0) : 0;
        });

        return view('livewire.inventario-toma', [
            'st' => $st, 'lista' => $lista, 'total' => $all->count(), 'grupos' => $grupos, 'T' => $T, 'dif' => $dif,
            'nDif' => $T->keys()->filter(fn ($id) => $dif($id))->count(), 'perd' => Auth::user()->puede('costos') ? $perd : 0,
            'quien' => $T->pluck('vendedor')->filter()->unique()->map(fn ($n) => Texto::primerNombre($n))->join(', '),
        ])->title('Toma de inventario');
    }
}
