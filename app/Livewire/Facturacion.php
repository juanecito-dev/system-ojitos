<?php

namespace App\Livewire;

use App\Livewire\Concerns\ConAutorizacion;
use App\Models\Comprobante;
use App\Services\Comprobantes;
use App\Services\ErrorNegocio;
use App\Support\Catalogos;
use App\Support\Dinero;
use App\Support\NegocioActual;
use App\Support\Valida;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Title('Facturación')]
class Facturacion extends Component
{
    use ConAutorizacion;

    #[Url(as: 't')]
    public string $tab = 'pend';   // pend | emit | reg

    #[Url(as: 'mes')]
    public string $mes = '';

    // ---- registrar un pendiente
    #[Locked]
    public ?string $emitiendo = null;

    #[Locked]
    public bool $guia = false;

    #[Locked]
    public array $saltados = [];

    public array $doc = ['td' => '1', 'nd' => '', 'nom' => '', 'dir' => ''];

    public bool $conDoc = false;

    public string $serie = '';

    public string $numero = '';

    public string $error = '';

    // ---- sobre un comprobante emitido
    #[Locked]
    public ?string $accion = null;     // anular | nc | numero

    #[Locked]
    public ?string $sobre = null;      // uid del comprobante

    public string $motivo = 'Error en los datos del cliente';

    public string $monto = '';

    public string $serieNumero = '';

    public function mount(): void
    {
        if (! Valida::mes($this->mes)) {
            $this->mes = today()->format('Y-m');
        }
    }

    private function srv(): Comprobantes
    {
        return app(Comprobantes::class);
    }

    public static function clave(array $x): string
    {
        return $x['kind'] === 'cierre' ? 'c:'.$x['fecha'] : 'v:'.$x['ids'][0];
    }

    private function pendiente(?string $clave): ?array
    {
        return $clave ? collect($this->srv()->pendientes())->first(fn ($x) => self::clave($x) === $clave) : null;
    }

    // ================================================================ por emitir

    public function abrirEmitir(string $clave): void
    {
        $x = $this->pendiente($clave);
        if (! $x) {
            $this->emitiendo = null;

            return;
        }
        $f = $x['tipo'] === '01';
        $this->emitiendo = $clave;
        $this->doc = ['td' => $f ? '6' : '1', 'nd' => '', 'nom' => '', 'dir' => ''] + [];
        $this->doc = array_merge($this->doc, $x['doc'] ?? []);
        $this->conDoc = $f || $x['total'] > Catalogos::MAX_SIN_DOC || ($x['doc']['nd'] ?? '') !== '';
        $this->serie = $this->srv()->serieDe($x['tipo']);
        $this->numero = (string) ($this->srv()->ultimoNumero($this->serie) + 1);
        $this->error = '';
    }

    public function empezarGuia(): void
    {
        $this->guia = true;
        $this->saltados = [];
        $this->siguienteGuia();
    }

    private function siguienteGuia(): void
    {
        $x = collect($this->srv()->pendientes())->first(fn ($x) => ! in_array(self::clave($x), $this->saltados, true));
        if ($x) {
            $this->abrirEmitir(self::clave($x));
        } else {
            $this->emitiendo = null;
            if ($this->guia) {
                $this->dispatch('toast', texto: '¡Listo! Registraste todos los comprobantes pendientes.');
            }
            $this->guia = false;
        }
    }

    public function saltar(): void
    {
        if ($this->emitiendo) {
            $this->saltados[] = $this->emitiendo;
        }
        $this->siguienteGuia();
    }

    public function registrar(): void
    {
        $x = $this->pendiente($this->emitiendo);
        if (! $x) {
            $this->error = 'Ese comprobante ya no está pendiente. La lista se actualizó.';

            return;
        }
        try {
            $c = $this->srv()->emitirPendiente($x, $this->serie, $this->numero, $this->conDoc ? $this->doc : null);
        } catch (ErrorNegocio $e) {
            $this->error = $e->getMessage();

            return;
        }
        $this->dispatch('toast', texto: Catalogos::TIPOS_COMPROBANTE[$c->tipo].' '.$c->etiqueta().' registrada');
        if ($this->guia) {
            $this->siguienteGuia();
        } else {
            $this->emitiendo = null;
        }
    }

    public function cerrar(): void
    {
        $this->emitiendo = null;
        $this->guia = false;
        $this->accion = null;
        $this->sobre = null;
        $this->error = '';
    }

    // ================================================================ emitidos

    private function comprobante(?string $uid): ?Comprobante
    {
        return $uid ? Comprobante::where('uid', $uid)->first() : null;
    }

    public function pedirAccion(string $accion, string $uid): void
    {
        $c = $this->comprobante($uid);
        if (! $c || $c->estado === 'anulado' || ! in_array($accion, ['anular', 'nc', 'numero'], true)) {
            return;
        }
        // poner el número que falta no necesita permiso; corregir uno ya puesto, anular y la nota de crédito sí
        $ok = match (true) {
            $accion === 'numero' && ! $c->numero => true,
            $accion === 'numero' => $this->requiere('anular', 'Corregir el número de '.$c->etiqueta(), 'pedirAccion', [$accion, $uid],
                ['forzar' => true, 'soloAdmin' => true, 'incluirme' => true, 'titulo' => 'Confirma con el PIN de un administrador', 'motivo' => 'El número debe ser el mismo que te dio SUNAT.']),
            $accion === 'anular' => $this->requiere('anular', 'Anular '.$c->etiqueta(), 'pedirAccion', [$accion, $uid]),
            default => $this->requiere('anular', 'Registrar una nota de crédito sobre '.$c->etiqueta(), 'pedirAccion', [$accion, $uid]),
        };
        if (! $ok) {
            return;
        }
        $this->accion = $accion;
        $this->sobre = $uid;
        $this->error = '';
        $this->motivo = $accion === 'nc' ? 'Anulación de la operación' : 'Error en los datos del cliente';
        $this->monto = Dinero::n(max(0, abs($c->total) - $this->srv()->notasPrevias($c)));
        $this->serieNumero = '';
        $this->serie = $c->serie;
        $this->numero = $c->numero ?: (string) ($this->srv()->ultimoNumero($c->serie, $c->tipo === '07') + 1);
        session()->put('fact_ok', $accion.':'.$uid);
    }

    public function confirmarAccion(): void
    {
        $c = $this->comprobante($this->sobre);
        if (! $c || session()->get('fact_ok') !== $this->accion.':'.$this->sobre) {
            return;
        }
        $yo = Auth::user();
        try {
            match ($this->accion) {
                'anular' => $this->srv()->anular($c, $this->motivo, $yo),
                'nc' => $this->srv()->notaCredito($c, (int) Dinero::aCentimos($this->monto), $this->motivo, $this->serieNumero, $yo),
                'numero' => $this->srv()->cambiarNumero($c, $this->serie, $this->numero, $yo),
            };
        } catch (ErrorNegocio $e) {
            $this->error = $e->getMessage();

            return;
        }
        session()->forget('fact_ok');
        $this->dispatch('toast', texto: ['anular' => 'Comprobante anulado en tu registro', 'nc' => 'Nota de crédito registrada', 'numero' => 'Número guardado'][$this->accion]);
        $this->accion = null;
        $this->sobre = null;
    }

    public function render()
    {
        if (! Valida::mes($this->mes)) {
            $this->mes = today()->format('Y-m');
        }
        $neg = app(NegocioActual::class)->obligatorio();
        $cfg = $this->srv()->config();
        $datos = ['neg' => $neg, 'cfg' => $cfg, 'puedeFactura' => $this->srv()->puedeFactura()];
        $P = $this->srv()->pendientes();
        $datos['nPend'] = count($P);
        if ($this->tab === 'pend') {
            $datos['P'] = $P;
            $r = collect($P)->map(fn ($x) => Comprobantes::diasRestan(Carbon::parse($x['fecha'])));
            $datos['vencidos'] = $r->filter(fn ($d) => $d < 0)->count();
            $datos['vencen'] = $r->filter(fn ($d) => $d >= 0 && $d <= 1)->count();
        } else {
            $desde = $this->mes.'-01';
            $hasta = Carbon::parse($desde)->endOfMonth()->toDateString();
            $C = Comprobante::where('fecha', '>=', $desde)->where('fecha', '<=', $hasta)->orderBy('emitido_at')->get();
            $val = fn ($c) => $c->estado === 'anulado' ? 0 : ($c->tipo === '07' ? -1 : 1);
            $datos += ['C' => $C, 'saltos' => $this->srv()->saltos($this->mes),
                'tot' => fn ($f) => (int) $C->sum(fn ($c) => $val($c) * ($c->{$f} ?? 0))];
        }
        $x = $this->pendiente($this->emitiendo);
        if ($this->emitiendo && ! $x) {
            $this->emitiendo = null;
        }
        $datos['x'] = $x;
        $datos['posGuia'] = $x && $this->guia ? collect($P)->search(fn ($y) => self::clave($y) === $this->emitiendo) + 1 : null;
        $datos['c'] = $this->comprobante($this->sobre);
        $datos['previas'] = $datos['c'] ? $this->srv()->notasPrevias($datos['c']) : 0;

        return view('livewire.facturacion', $datos);
    }
}
