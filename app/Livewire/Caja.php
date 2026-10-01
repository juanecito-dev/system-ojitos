<?php

namespace App\Livewire;

use App\Livewire\Concerns\ConAutorizacion;
use App\Livewire\Concerns\ConDia;
use App\Models\CajaMovimiento;
use App\Models\ClienteMovimiento;
use App\Models\CompraPago;
use App\Models\TurnoCaja;
use App\Services\Bitacora;
use App\Services\CajaDia;
use App\Services\CuadreCaja;
use App\Services\Reportes;
use App\Support\Catalogos;
use App\Support\Dinero;
use App\Support\NegocioActual;
use App\Support\Texto;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Caja y gastos')]
class Caja extends Component
{
    use ConAutorizacion, ConDia;

    public string $inicial = '';

    public array $contado = [];

    /** ventana de movimiento: gasto | retiro | ingreso */
    #[Locked]
    public ?string $mov = null;

    public string $concepto = '';

    public string $monto = '';

    public string $nota = '';

    public string $metodo = 'efectivo';

    /** cierre por confirmar */
    #[Locked]
    public ?int $cerrando = null;

    /** corrección del sencillo inicial */
    #[Locked]
    public ?int $corrigiendo = null;

    public string $sencillo = '';

    public ?int $borrandoFiado = null;

    public function abrirCaja(): void
    {
        if (! $this->esHoy()) {
            return;
        }
        $v = Dinero::aCentimos($this->inicial === '' ? '0' : $this->inicial);
        if ($v === null || $v < 0) {
            $this->dispatch('toast', texto: 'Escribe un monto, por ejemplo 50');

            return;
        }
        $yo = Auth::user();
        // un turno a la vez: si quedó abierto uno de otro día (pasó la medianoche), primero se cierra ese
        if ($antes = TurnoCaja::where('usuario_id', $yo->id)->whereNull('cierra_at')->first()) {
            $this->dispatch('toast', texto: $antes->fecha->toDateString() === $this->dia ? 'Tu caja ya está abierta'
                : 'Primero cierra tu caja abierta el '.$antes->abre_at->format('d/m').' a las '.$antes->abre_at->format('H:i'));

            return;
        }
        TurnoCaja::create(['uid' => Texto::nuevoUid(), 'fecha' => $this->dia, 'usuario_id' => $yo->id, 'vendedor' => $yo->nombre, 'abre_at' => now(), 'inicial' => $v]);
        $this->inicial = '';
        $this->dispatch('toast', texto: 'Tu caja está abierta con '.Dinero::s($v));
    }

    private function turnoEditable(int $id): ?TurnoCaja
    {
        $t = TurnoCaja::find($id);
        $yo = Auth::user();
        // una caja abierta se puede cerrar aunque se haya abierto otro día (turno que pasó la medianoche): queda con la fecha en que se abrió
        if (! $t || $t->cierra_at || ($t->usuario_id !== $yo->id && ! $yo->esAdmin())) {
            return null;
        }

        return $t;
    }

    public function pedirCierre(int $id): void
    {
        $t = $this->turnoEditable($id);
        $v = Dinero::aCentimos($this->contado[$id] ?? '');
        if (! $t) {
            return;
        }
        if ($v === null || $v < 0) {
            $this->dispatch('toast', texto: 'Escribe cuánto efectivo contaste');

            return;
        }
        $this->cerrando = $id;
    }

    public function cerrarCaja(): void
    {
        $t = $this->cerrando ? $this->turnoEditable($this->cerrando) : null;
        $v = Dinero::aCentimos($this->contado[$this->cerrando] ?? '');
        $this->cerrando = null;
        if (! $t || $v === null || $v < 0) {
            return;
        }
        $c = (new CajaDia($t->fecha->toDateString()))->turno($t);
        $t->update(['cierra_at' => now(), 'contado' => $v, 'esperado' => $c['esperado'], 'cerro_por' => Auth::user()->nombre]);
        $d = $v - $c['esperado'];
        Bitacora::registrar('caja', 'Cerró la caja de '.$t->vendedor.': debía haber '.Dinero::s($c['esperado']).', contó '.Dinero::s($v));
        $this->dispatch('toast', texto: $d === 0 ? 'Caja cerrada: cuadró exacto' : ($d > 0 ? 'Caja cerrada: sobraron '.Dinero::s($d) : 'Caja cerrada: faltaron '.Dinero::s(-$d)));
    }

    public function pedirCorreccion(int $id): void
    {
        $t = $this->turnoEditable($id);
        if (! $t) {
            return;
        }
        if (! Auth::user()->esAdmin() && $t->abre_at->lt(now()->subMinutes(15))) {
            $this->dispatch('toast', texto: 'Pasaron más de 15 minutos desde que abriste: pide al administrador que corrija el sencillo');

            return;
        }
        $this->corrigiendo = $id;
        $this->sencillo = Dinero::n($t->inicial);
    }

    public function corregirSencillo(): void
    {
        $t = $this->corrigiendo ? $this->turnoEditable($this->corrigiendo) : null;
        $this->corrigiendo = null;
        $v = Dinero::aCentimos($this->sencillo);
        if (! $t || $v === null || $v < 0 || $v === $t->inicial) {
            return;
        }
        $log = $t->correcciones ?? [];
        $log[] = ['antes' => $t->inicial, 'despues' => $v, 't' => now()->getTimestampMs(), 'por' => Auth::user()->nombre];
        $t->update(['inicial' => $v, 'correcciones' => $log]);
        Bitacora::registrar('caja', 'Corrigió el sencillo de '.$t->vendedor.': '.Dinero::s($log[count($log) - 1]['antes']).' → '.Dinero::s($v));
    }

    public function abrirMovimiento(string $tipo): void
    {
        if (! isset(Catalogos::CONCEPTOS[$tipo])) {
            return;
        }
        $this->mov = $tipo;
        $this->concepto = Catalogos::CONCEPTOS[$tipo][0];
        $this->monto = '';
        $this->nota = '';
        $this->metodo = 'efectivo';
    }

    public function guardarMovimiento(): void
    {
        $tipo = $this->mov;
        $v = Dinero::aCentimos($this->monto);
        if (! $tipo) {
            return;
        }
        if (! ($v > 0)) {
            $this->dispatch('toast', texto: 'Escribe el monto, por ejemplo 25.00');

            return;
        }
        $que = ['gasto' => 'un gasto', 'retiro' => 'un retiro', 'ingreso' => 'un ingreso'][$tipo];
        if (! $this->requiere('gastos', 'Registrar '.$que.' de '.Dinero::s($v), 'guardarMovimiento')) {
            return;
        }
        $yo = Auth::user();
        $metodo = $tipo === 'retiro' ? 'efectivo' : $this->metodo;
        if (! array_key_exists($metodo, app(NegocioActual::class)->obligatorio()->metodosActivos())) {
            $metodo = 'efectivo';
        }
        CajaMovimiento::create([
            'uid' => Texto::nuevoUid(), 'fecha' => $this->dia, 'tipo' => $tipo,
            'concepto' => in_array($this->concepto, Catalogos::CONCEPTOS[$tipo], true) ? $this->concepto : 'Otro',
            'nota' => trim($this->nota) ?: null, 'monto' => $v, 'metodo' => $metodo,
            'usuario_id' => $yo->id, 'vendedor' => $yo->nombre, 'ocurrido_at' => $this->esHoy() ? now() : Carbon::parse($this->dia.' 23:59'),
        ]);
        $this->mov = null;
        $this->dispatch('toast', texto: ucfirst(['gasto' => 'gasto', 'retiro' => 'retiro de caja', 'ingreso' => 'otro ingreso'][$tipo]).' guardado: '.Dinero::s($v));
    }

    public function borrarMovimiento(int $id, bool $confirmado = false): void
    {
        $m = CajaMovimiento::find($id);
        if (! $m || (! Auth::user()->esAdmin() && $m->usuario_id && $m->usuario_id !== Auth::id())) {
            return;
        }
        if ($m->concepto === 'Pago de fiado' && $m->referencia && ! $confirmado) {
            $this->borrandoFiado = $id;

            return;
        }
        $this->borrandoFiado = null;
        if (! $this->requiere('borrar', 'Borrar un movimiento de caja de '.Dinero::s($m->monto), 'borrarMovimiento', [$id, true])) {
            return;
        }
        // una caja cerrada no se toca: el movimiento de un turno ya cuadrado se corrige desde la caja de hoy
        if (CuadreCaja::cuadrado($m->usuario_id, $m->ocurrido_at, $m->fecha->toDateString())) {
            $this->borrandoFiado = null;
            $this->dispatch('toast', texto: 'Esa caja ya se cerró y cuadró: no se puede borrar. Si fue un error, registra un movimiento de corrección hoy.');

            return;
        }
        if ($m->concepto === 'Pago de fiado' && $m->referencia) {
            ClienteMovimiento::where('tipo', 'abono')->where(fn ($q) => $q->where('uid', $m->referencia)->orWhere('venta_uid', $m->referencia))->get()->each->delete();
        }
        // el pago a un proveedor también sale de la compra: la deuda vuelve a figurar
        if ($m->concepto === 'Pago a proveedor') {
            CompraPago::where('caja_mov_uid', $m->uid)->get()->each->delete();
        }
        Bitacora::registrar('caja', 'Borró '.$m->tipo.' «'.$m->concepto.'» de '.Dinero::s($m->monto));
        $m->delete();   // queda marcado como borrado
        $this->borrandoFiado = null;
        $this->dispatch('toast', texto: 'Movimiento borrado');
    }

    public function cerrarVentana(): void
    {
        $this->mov = null;
        $this->cerrando = null;
        $this->corrigiendo = null;
        $this->borrandoFiado = null;
    }

    public function render()
    {
        $yo = Auth::user();
        $admin = $yo->esAdmin();
        $caja = new CajaDia($this->dia);
        // hoy también se ven las cajas que quedaron abiertas de otro día, para poder cerrarlas
        $antes = $this->esHoy() ? TurnoCaja::where('fecha', '<', $this->dia)->whereNull('cierra_at')->get() : collect();
        $todos = $caja->turnos->merge($antes);
        $mios = $todos->where('usuario_id', $yo->id);
        $vis = $admin ? $todos : $mios;
        $fuera = $caja->sinTurno();

        return view('livewire.caja', [
            'caja' => $caja, 'admin' => $admin, 'mios' => $mios, 'abierto' => $mios->first(fn ($t) => ! $t->cierra_at),
            'turnos' => $vis->sortByDesc('abre_at'), 'fuera' => $fuera, 'verFuera' => $fuera['total'] && ($admin || in_array($yo->nombre, $fuera['quien'], true)),
            'movs' => ($admin ? $caja->movs : $caja->movs->filter(fn ($m) => ! $m->usuario_id || $m->usuario_id === $yo->id))->sortByDesc('ocurrido_at'),
            'legado' => $caja->dia?->caja_inicial !== null || $caja->dia?->caja_arqueo,
            'metodos' => app(NegocioActual::class)->obligatorio()->metodosActivos(),
            'neg' => app(NegocioActual::class)->obligatorio(),
            'fiadoBorrar' => $this->borrandoFiado ? CajaMovimiento::find($this->borrandoFiado) : null,
            'resumen' => $admin && $caja->ventas->isNotEmpty() ? app(Reportes::class)->resumenDia($this->dia) : null,
        ]);
    }
}
