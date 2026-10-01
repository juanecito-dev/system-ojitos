<?php

namespace App\Models;

use App\Casts\Fecha;
use App\Models\Concerns\PerteneceANegocio;
use App\Models\Venta as VentaModelo;
use App\Support\Texto;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Cotización → en proceso → listo → entregado (o «no aceptó»). Reglas de pedTotal, pedEtapa, etc. */
class Pedido extends Model
{
    use PerteneceANegocio;

    protected $table = 'pedidos';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'cliente' => 'array', 'datos' => 'array', 'fecha' => Fecha::class, 'fecha_entrega' => Fecha::class,
            'entregado_at' => 'datetime', 'creado_at' => 'datetime', 'aceptado_at' => 'datetime', 'listo_at' => 'datetime', 'cerrado_at' => 'datetime',
            'cotizada' => 'boolean', 'directa' => 'boolean',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(PedidoItem::class)->orderBy('orden');
    }

    public function pagos(): HasMany
    {
        return $this->hasMany(PedidoPago::class)->orderBy('pagado_at');
    }

    public function historial(): HasMany
    {
        return $this->hasMany(PedidoHistorial::class)->orderByDesc('ocurrido_at')->orderByDesc('id');
    }

    public function clienteGuardado(): BelongsTo
    {
        return $this->belongsTo(Cliente::class, 'cliente_id');
    }

    public function numeroTxt(): string
    {
        return 'N° '.str_pad((string) ($this->numero ?? 0), 6, '0', STR_PAD_LEFT);
    }

    public function dato(string $k): string
    {
        return (string) (($this->cliente ?? [])[$k] ?? '');
    }

    public function subtotal(): int
    {
        return (int) $this->items->sum('subtotal');
    }

    public function pagado(): int
    {
        return (int) $this->pagos->sum('monto');
    }

    public function saldo(): int
    {
        return max(0, $this->total - $this->pagado());
    }

    public function vence(): Carbon
    {
        return $this->fecha->copy()->addDays($this->validez ?: 7);
    }

    /** la etapa que se muestra: una cotización pasada de su validez sale como «vencida» */
    public function etapaVista(): string
    {
        return $this->etapa === 'cotizado' && $this->vence()->lt(today()) ? 'vencido' : $this->etapa;
    }

    public function activo(): bool
    {
        return in_array($this->etapa, ['proceso', 'listo'], true);
    }

    public function atrasado(): bool
    {
        return $this->activo() && $this->fecha_entrega && $this->fecha_entrega->lt(today());
    }

    /** listo hace más de 7 días y no lo recogen */
    public function olvidado(): bool
    {
        return $this->etapa === 'listo' && $this->listo_at && $this->listo_at->lt(now()->subDays(7));
    }

    public function descripcion(): string
    {
        return $this->items->isNotEmpty()
            ? $this->items->map(fn ($l) => VentaModelo::cant($l->cantidad).' '.$l->nombre.($l->detalle ? ' ('.$l->detalle.')' : ''))->join(', ')
            : (string) $this->detalle;
    }

    public function celular(): string
    {
        $d = Texto::cel9($this->dato('cel'));

        return strlen($d) === 9 ? $d : '';
    }
}
