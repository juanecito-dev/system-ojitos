<?php

namespace App\Livewire;

use App\Models\Documento;
use App\Models\ModeloRedaccion;
use App\Redaccion\Catalogo;
use App\Redaccion\Curriculum;
use App\Services\Redaccion as Srv;
use App\Support\Texto;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/** Redacción: modelos por sección y documentos hechos, con buscador y estado (renderDocs del sistema anterior). */
#[Title('Redacción')]
class Redaccion extends Component
{
    #[Url(as: 'q')]
    public string $buscarModelo = '';

    #[Url(as: 'h')]
    public string $buscarDoc = '';

    #[Url(as: 'e')]
    public string $estado = 'todos';

    public int $ver = 40;

    public function mount(): void
    {
        Catalogo::alDia();
    }

    public function render()
    {
        $q = Texto::norm(trim($this->buscarModelo));
        $modelos = ModeloRedaccion::where('activo', true)->orderBy('orden')->get()
            ->filter(fn ($m) => $q === '' || str_contains(Texto::norm($m->nombre.' '.$m->descripcion), $q));
        $precios = ['pagina' => Srv::precio('pagina'), 'doc' => Srv::precio('doc'), 'cv' => Srv::precio('cv')];
        foreach (Curriculum::DISENOS as $id => [, $op]) {
            $precios[$id] = Srv::precio('cv', $op);
        }

        $hq = Texto::norm(trim($this->buscarDoc));
        $base = Documento::query();
        $docs = (clone $base)->when($this->estado !== 'todos', fn ($x) => $this->estado === 'encargo' ? $x->whereNotNull('encargo') : $x->where('estado', $this->estado))
            ->when($hq !== '', fn ($x) => $x->where(fn ($y) => $y->where('busqueda', 'like', '%'.$hq.'%')->orWhere('titulo', 'like', '%'.$this->buscarDoc.'%')->orWhere('partes', 'like', '%'.$this->buscarDoc.'%')))
            ->orderByDesc('created_at')->limit($this->ver + 1)->get();

        return view('livewire.redaccion', [
            'secciones' => collect(Catalogo::SECCIONES)->map(fn ($l, $k) => ['l' => $l, 'modelos' => $modelos->where('seccion', $k)])->filter(fn ($s) => $s['modelos']->isNotEmpty()),
            'precios' => $precios, 'docs' => $docs->take($this->ver), 'hayMas' => $docs->count() > $this->ver,
            'cuentas' => ['borrador' => (clone $base)->where('estado', 'borrador')->count(), 'encargo' => (clone $base)->whereNotNull('encargo')->count()],
            'nombres' => ModeloRedaccion::pluck('nombre', 'uid'),
        ]);
    }
}
