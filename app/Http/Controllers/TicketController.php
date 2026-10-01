<?php

namespace App\Http\Controllers;

use App\Models\Venta;
use App\Services\Ticket;
use App\Support\Catalogos;
use App\Support\Dinero;
use App\Support\NegocioActual;
use App\Support\Valida;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Auth;

class TicketController extends Controller
{
    private function venta(string $uid): Venta
    {
        $v = Venta::with(['items', 'cliente'])->where('uid', $uid)->firstOrFail();
        $yo = Auth::user();
        abort_unless($yo->puede('verTodo') || $v->usuario_id === $yo->id, 403, 'No puedes ver las ventas de otros usuarios.');

        return $v;
    }

    /** Nota de venta en PDF, del ancho del papel de la ticketera (80 o 58 mm) */
    public function pdf(string $uid, Ticket $ticket)
    {
        $v = $this->venta($uid);
        $neg = app(NegocioActual::class)->obligatorio();
        $filas = $ticket->filas($v, $neg);
        $ancho = Ticket::ancho($neg);
        $pdf = $ticket->pdf($filas, $ancho, 'Nota de venta '.$v->numeroTicket());

        return $pdf->stream('nota-de-venta-'.$v->numeroTicket().'.pdf');
    }

    /** Filas del ticket para dibujar la imagen en el navegador */
    public function filas(string $uid, Ticket $ticket)
    {
        return response()->json($ticket->filas($this->venta($uid), app(NegocioActual::class)->obligatorio()));
    }

    /** Ventas del día para Excel (mismas columnas que el sistema anterior) */
    public function csv(string $fecha)
    {
        abort_unless(Valida::fecha($fecha), 404);
        $neg = app(NegocioActual::class)->obligatorio();
        $yo = Auth::user();
        $V = Venta::with('items')->where('fecha', $fecha)->when(! $yo->puede('verTodo'), fn ($q) => $q->where('usuario_id', $yo->id))->orderBy('vendida_at')->get();
        $q = fn ($s) => '"'.str_replace('"', '""', preg_replace('/^([=+\-@])/', "'$1", (string) $s)).'"';
        $out = [implode(';', ['Fecha', 'Hora', 'Servicio', 'Detalle', 'Cantidad', 'Precio unit.', 'Subtotal', 'Total venta', 'Pago', 'Comprobante', 'Vendedor'])];
        foreach ($V as $v) {
            foreach ($v->items as $l) {
                $out[] = implode(';', [$v->fecha->format('d/m/Y'), $v->vendida_at->format('H:i'), $q($l->nombre), $q($l->detalle ?? ''), Venta::cant($l->cantidad),
                    Dinero::n($l->precio), Dinero::n($l->subtotal), Dinero::n($v->total), $neg->nombreMetodo($v->metodo),
                    $l->tercero ? 'Tasa de terceros' : ($v->boleta ? 'Boleta' : ($v->propio() > Catalogos::LIMITE_CIERRE ? 'Pendiente' : 'Cierre del dia')), $q($v->vendedor ?? '')]);
            }
        }

        return response("\u{FEFF}".implode("\r\n", $out), 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="ventas-'.$fecha.'.csv"',
        ]);
    }
}
