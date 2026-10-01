<?php

namespace App\Livewire;

use App\Models\ClienteMovimiento;
use App\Models\Pedido;
use App\Models\TurnoCaja;
use App\Services\CajaDia;
use App\Services\Contadores;
use App\Support\Acceso;
use App\Support\Avisos;
use App\Support\Catalogos;
use App\Support\Dinero;
use App\Support\NegocioActual;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Inicio')]
class Inicio extends Component
{
    public function render(Avisos $avisos)
    {
        $neg = app(NegocioActual::class)->obligatorio();
        $yo = Auth::user();
        $hoy = today()->toDateString();
        $caja = new CajaDia($hoy);
        $r = $caja->resumen();
        $h = (int) now()->format('G');
        $saldos = ClienteMovimiento::selectRaw("cliente_id, SUM(CASE WHEN tipo = 'fiado' THEN monto ELSE -monto END) AS s")->groupBy('cliente_id')->pluck('s');
        $cierrePend = $r['menores']->isNotEmpty() && ! $caja->dia?->cierre;

        return view('livewire.inicio', [
            'saludo' => $h < 12 ? 'Buenos días' : ($h < 19 ? 'Buenas tardes' : 'Buenas noches'),
            'nombre' => $yo->primerNombre(),
            'fechaTxt' => ucfirst(now()->translatedFormat('D j \d\e F')),
            'cajaAbierta' => TurnoCaja::where('fecha', $hoy)->where('usuario_id', $yo->id)->whereNull('cierra_at')->exists() || $caja->dia?->caja_inicial !== null,
            'r' => $r,
            'meta' => Dinero::aCentimos($neg->ajuste('meta')) ?? 0,
            'porEmitir' => $avisos->comprobantesPorEmitir(),
            'cierrePend' => $cierrePend ? (int) $r['menores']->sum(fn ($v) => $v->propio()) : 0,
            'deben' => (int) $saldos->filter(fn ($s) => $s > 0)->sum(),
            'nDeben' => $saldos->filter(fn ($s) => $s > 0)->count(),
            'bajo' => $avisos->stockBajo(),
            'pedAct' => Pedido::whereIn('etapa', ['proceso', 'listo'])->count(),
            'pedUrg' => $avisos->pedidosUrgentes()->count(),
            'cotEsperan' => Pedido::where('etapa', 'cotizado')->get()->filter(fn ($p) => $p->etapaVista() === 'cotizado')->count(),
            'ultimas' => $caja->ventas->sortByDesc('vendida_at')->take(5),
            'modulos' => collect(Acceso::menu())->except('inicio'),
            'vencen' => Acceso::modulo('compras') ? $avisos->comprasPorVencer() : collect(),
            'sinCobrar' => $yo->esAdmin() ? app(Contadores::class)->sinCobrar(today()->subDays(6)->toDateString(), $hoy) : null,
            'faltan' => collect(Catalogos::MODULOS)->filter(fn ($m, $k) => ! $m[3] && Acceso::modulo($k))->map(fn ($m) => $m[0])->values(),
        ]);
    }
}
