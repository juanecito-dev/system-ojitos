<?php

namespace App\Models;

use App\Casts\Fecha;
use App\Models\Concerns\PerteneceANegocio;
use App\Support\Catalogos;
use App\Support\Texto;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $uid
 * @property Carbon $fecha
 * @property string|null $numero
 * @property int|null $usuario_id
 * @property int $total
 * @property string|null $metodo
 * @property Carbon $vendida_at
 * @property Carbon|null $anulada_at
 * @property bool $devuelta_en_caja
 */
class Venta extends Model
{
    use PerteneceANegocio;

    /**
     * Una venta anulada no se borra: queda con anulada_at y deja de contar como venta en todas partes.
     * Para verla (historial de anuladas, cuadre del turno en que se cobró) usar conAnuladas().
     */
    protected static function booted(): void
    {
        static::addGlobalScope('vigente', fn ($q) => $q->whereNull($q->getModel()->getTable().'.anulada_at'));
    }

    public static function conAnuladas()
    {
        return static::withoutGlobalScope('vigente');
    }

    protected $table = 'ventas';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'fecha' => Fecha::class, 'comprobante_fecha' => Fecha::class, 'vendida_at' => 'datetime', 'anulada_at' => 'datetime', 'devuelta_en_caja' => 'boolean',
            'boleta' => 'boolean', 'al_cierre' => 'boolean', 'comprobante_pedido' => 'array', 'extra' => 'array',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(VentaItem::class)->orderBy('orden');
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class);
    }

    /** tasas pagadas a terceros (pagalo.pe, Banco de la Nación): no son ingreso propio */
    public function terceros(): int
    {
        return (int) $this->items->where('tercero', true)->sum('subtotal');
    }

    /** lo que es ingreso del negocio */
    public function propio(): int
    {
        return $this->total - $this->terceros();
    }

    /** el cliente pidió su comprobante (o es factura) */
    public function pideComprobante(): bool
    {
        $r = $this->comprobante_pedido;

        return is_array($r) && (($r['tipo'] ?? null) === '01' || ! empty($r['pide']));
    }

    /** va a la boleta de cierre del día: ventas de hasta S/ 5 sin comprobante propio */
    public function vaAlCierre(): bool
    {
        $p = $this->propio();

        return ! $this->boleta && $p > 0 && ! $this->pideComprobante()
            && ($p <= Catalogos::LIMITE_CIERRE || $this->al_cierre);
    }

    /** le falta su boleta: más de S/ 5 o el cliente la pidió */
    public function faltaComprobante(): bool
    {
        $p = $this->propio();

        return ! $this->boleta && $p > 0
            && (($p > Catalogos::LIMITE_CIERRE && ! $this->al_cierre) || $this->pideComprobante());
    }

    /** número de la nota de venta: 20260929-A1-001 */
    public function numeroTicket(): string
    {
        return $this->fecha->format('Ymd').'-'.($this->numero ?: str_pad((string) $this->id, 3, '0', STR_PAD_LEFT));
    }

    /** texto para copiar en el portal de SUNAT */
    public function descripcionSunat(): string
    {
        return Texto::sunat($this->items->where('tercero', false)
            ->map(fn ($l) => rtrim(self::cant($l->cantidad).' '.$l->nombre.' '.($l->detalle ?? '')))->join(', '));
    }

    public function resumen(): string
    {
        return $this->items->map(fn ($l) => self::cant($l->cantidad).' '.$l->nombre.($l->detalle ? ' ('.$l->detalle.')' : ''))->join(', ');
    }

    public static function cant(float|int $n): string
    {
        return rtrim(rtrim(number_format((float) $n, 3, '.', ''), '0'), '.');
    }
}
