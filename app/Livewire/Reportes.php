<?php

namespace App\Livewire;

use App\Services\Reportes as Srv;
use App\Support\Dinero;
use App\Support\NegocioActual;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/** Reportes: resultado, dinero que entró y salió, gráficos, grupos, productos, horas, vendedores, clientes, gastos y pagos (renderRep). */
#[Title('Reportes')]
class Reportes extends Component
{
    #[Url(as: 'r')]
    public string $rango = 'mes';

    #[Url(as: 'desde')]
    public string $desde = '';

    #[Url(as: 'hasta')]
    public string $hasta = '';

    /** con qué se compara: anterior (el periodo de antes) o anio (las mismas fechas del año pasado) */
    #[Url(as: 'cmp')]
    public string $comparar = 'anterior';

    public string $orden = 'g';

    public function updatedRango(): void
    {
        if (! isset(Srv::RANGOS[$this->rango])) {
            $this->rango = 'mes';
        }
        if ($this->rango === 'custom' && $this->desde === '') {
            [$this->desde, $this->hasta] = Srv::rango('7');
        }
    }

    public function render(Srv $srv)
    {
        $this->updatedRango();
        $this->comparar = $this->comparar === 'anio' ? 'anio' : 'anterior';
        $costos = Auth::user()->puede('costos');
        if (! $costos) {
            $this->orden = 'v';
        }
        [$a, $b] = Srv::rango($this->rango, $this->desde, $this->hasta);
        [$pa, $pb] = Srv::previo($a, $b, $this->rango, $this->comparar);
        $R = $srv->calcular($a, $b);
        $P = $srv->calcular($pa, $pb);
        $largo = $this->rango === 'anio' || Srv::dias($a, $b) > 92;
        $neg = app(NegocioActual::class)->obligatorio();

        $proy = null;
        if ($this->rango === 'mes') {
            $hoyN = today()->day;
            $meta = Dinero::aCentimos($neg->ajuste('meta')) ?? 0;
            $proy = ['porDia' => (int) round($R['ventas'] / $hoyN), 'cierre' => (int) round($R['ventas'] / $hoyN * today()->daysInMonth),
                'meta' => $meta, 'cumplidos' => collect($R['porDia'])->filter(fn ($v) => $meta > 0 && $v >= $meta)->count(), 'hoyN' => $hoyN];
        }
        $diasGrafico = [];
        if (! $largo) {
            for ($d = Carbon::parse($a); $d->toDateString() <= $b; $d->addDay()) {
                $diasGrafico[] = ['k' => $d->toDateString(), 'v' => $R['porDia'][$d->toDateString()] ?? 0, 'hoy' => $d->isToday()];
            }
        }

        return view('livewire.reportes', Srv::tablas($R, $this->orden) + [
            'R' => $R, 'P' => $P, 'a' => $a, 'b' => $b, 'pa' => $pa, 'pb' => $pb, 'largo' => $largo, 'costos' => $costos, 'proy' => $proy,
            'diasGrafico' => $diasGrafico, 'neg' => $neg, 'cp' => $srv->clientesYPedidos($R),
        ]);
    }
}
