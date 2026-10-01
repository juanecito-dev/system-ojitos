<?php

namespace App\Livewire;

use App\Livewire\Concerns\ConAutorizacion;
use App\Livewire\Concerns\ConDia;
use App\Models\Comprobante;
use App\Models\Venta;
use App\Services\Bitacora;
use App\Services\CajaDia;
use App\Services\Ventas as ServicioVentas;
use App\Support\Dinero;
use App\Support\NegocioActual;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Ventas')]
class Ventas extends Component
{
    use ConAutorizacion, ConDia;

    /** venta que se está anulando: pide el motivo */
    #[Locked]
    public ?string $anulando = null;

    public string $motivo = '';

    public ?string $ticketUid = null;

    public function pedirAnular(string $uid): void
    {
        $v = Venta::where('uid', $uid)->first();
        if (! $v || ! $this->visible($v)) {
            return;
        }
        if (! $this->requiere('anular', 'Anular la venta de '.Dinero::s($v->total).' de las '.$v->vendida_at->format('H:i'), 'pedirAnular', [$uid])) {
            return;
        }
        $this->anulando = $uid;
        $this->motivo = '';
        // quien autorizó queda anotado; la anulación ya no vuelve a pedir PIN
        session()->put('anular_ok', $uid);
    }

    public function anular(ServicioVentas $ventas): void
    {
        $uid = $this->anulando;
        if (! $uid || session()->pull('anular_ok') !== $uid) {
            return;
        }
        $v = Venta::with('items')->where('uid', $uid)->first();
        if (! $v) {
            $this->anulando = null;

            return;
        }
        $aviso = $ventas->anular($v, Auth::user(), trim($this->motivo));
        Bitacora::registrar('anula', 'Anuló la venta '.($v->numero ?? '').' de '.Dinero::s($v->total).($this->motivo ? ' — '.trim($this->motivo) : ''));
        $this->anulando = null;
        $this->dispatch('toast', texto: 'Venta anulada'.($aviso ? '. '.$aviso : ''));
    }

    public function cancelarAnular(): void
    {
        $this->anulando = null;
        session()->forget('anular_ok');
    }

    private function visible(Venta $v): bool
    {
        return Auth::user()->puede('verTodo') || $v->usuario_id === Auth::id();
    }

    public function render()
    {
        $yo = Auth::user();
        $neg = app(NegocioActual::class)->obligatorio();
        $caja = new CajaDia($this->dia, $yo->puede('verTodo') ? null : $yo->id);
        $r = $caja->resumen();
        $V = $caja->ventas;
        $por = [];
        foreach ($V as $v) {
            foreach ($v->items->where('tercero', false) as $l) {
                $por[$l->nombre] ??= ['cant' => 0, 'sub' => 0];
                $por[$l->nombre]['cant'] += $l->cantidad;
                $por[$l->nombre]['sub'] += $l->subtotal;
            }
        }
        uasort($por, fn ($a, $b) => $b['sub'] <=> $a['sub']);
        $vend = [];
        foreach ($V as $v) {
            $n = $v->vendedor ?: 'Sin usuario';
            $vend[$n] ??= ['n' => 0, 'tot' => 0, 'efe' => 0];
            $vend[$n]['n']++;
            $vend[$n]['tot'] += $v->propio();
            if (($v->metodo ?: 'efectivo') === 'efectivo') {
                $vend[$n]['efe'] += $v->total;
            }
        }
        uasort($vend, fn ($a, $b) => $b['tot'] <=> $a['tot']);
        $metodos = collect($neg->metodosActivos() + ['fiado' => $neg->nombreMetodo('fiado')])
            ->filter(fn ($n, $k) => ($r['metodos'][$k] ?? 0) || $k !== 'fiado')->map(fn ($n, $k) => [$n, $r['metodos'][$k] ?? 0]);
        foreach ($r['metodos'] as $k => $monto) {
            if (! $metodos->has($k)) {
                $metodos[$k] = [$neg->nombreMetodo($k), $monto];
            }
        }
        $boletasPend = $r['pendientes']->count() + ($r['menores']->isNotEmpty() && ! $caja->dia?->cierre ? 1 : 0);

        return view('livewire.ventas', [
            'r' => $r, 'V' => $V->sortByDesc('vendida_at'), 'por' => $por, 'maxRow' => max(1, collect($por)->max('sub') ?? 1),
            'vend' => count($vend) > 1 || (count($vend) === 1 && ! isset($vend['Sin usuario'])) ? $vend : [],
            'metodos' => $metodos, 'boletasPend' => $boletasPend, 'neg' => $neg,
            'ventaAnular' => $this->anulando ? Venta::with('items')->where('uid', $this->anulando)->first() : null,
            'cpeAnular' => $this->anulando ? $this->comprobanteDe($this->anulando) : null,
            'ticket' => $this->ticketUid ? Venta::with(['items', 'cliente'])->where('uid', $this->ticketUid)->get()->first(fn ($v) => $this->visible($v)) : null,
        ]);
    }

    private function comprobanteDe(string $uid): ?Comprobante
    {
        $v = Venta::where('uid', $uid)->first();

        return $v?->comprobante_uid ? Comprobante::where('uid', $v->comprobante_uid)->where('estado', '!=', 'anulado')->first() : null;
    }
}
