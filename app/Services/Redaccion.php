<?php

namespace App\Services;

use App\Models\Cliente;
use App\Models\Documento;
use App\Models\ModeloRedaccion;
use App\Models\Producto;
use App\Models\Usuario;
use App\Redaccion\Curriculum;
use App\Redaccion\Formato;
use App\Redaccion\Motor;
use App\Support\Texto;
use App\Support\Valida;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as DocPdf;
use Illuminate\Support\Str;

/**
 * Documentos redactados: generarlos con su modelo, guardarlos con sus partes y registrar a las personas
 * como clientes (persistDoc() y guardarPersonas() del sistema anterior).
 */
class Redaccion
{
    /** el producto con el que se cobra: por página (contratos), por documento o currículum */
    public static function producto(string $cobro): ?Producto
    {
        return Producto::with('opciones')->where('uid', ['pagina' => 's_contrato', 'cv' => 's_cv'][$cobro] ?? 's_solicitud')->first();
    }

    /** precio de la redacción (por página en los contratos); $opcion = diseño del CV */
    public static function precio(string $cobro, int $opcion = 0): int
    {
        $p = self::producto($cobro);

        return (int) ($p?->opciones->get($opcion)?->precio ?? $p?->opciones->first()?->precio ?? ['pagina' => 500, 'cv' => 500][$cobro] ?? 300);
    }

    /** arma el texto con el modelo y guarda el documento (nuevo o el mismo, si se corrigen los datos) */
    public function generar(?Documento $doc, ModeloRedaccion $m, array $d, Usuario $u): Documento
    {
        $motor = new Motor($m);
        $texto = $motor->texto($d);
        $doc ??= new Documento(['uid' => Texto::nuevoUid(), 'estado' => 'borrador', 'usuario_id' => $u->id, 'vendedor' => $u->nombre]);
        $clientes = $this->guardarPersonas($motor, $d);
        $doc->fijarCampos($d);
        $x = $doc->datos;
        $x['clientes'] = array_values(array_unique([...($x['clientes'] ?? []), ...$clientes]));
        $x['editado'] = false;
        $partes = $motor->partes($d);
        $doc->fill([
            'plantilla' => $m->uid, 'nivel' => $m->niveles ? Motor::nivel($d) : null, 'texto' => $texto, 'paginas' => null, 'titulo' => mb_substr($motor->titulo($d, $texto), 0, 250),
            'partes' => mb_substr($partes, 0, 250) ?: null, 'datos' => $x, 'cliente_id' => $doc->cliente_id ?? ($clientes[0] ?? null),
            'busqueda' => mb_substr(Texto::norm(implode(' ', [$m->nombre, $partes, ...$this->dnis($motor, $d)])), 0, 400),
        ]);
        $doc->save();

        return $doc;
    }

    private function dnis(Motor $m, array $d): array
    {
        return collect($m->definicionCampos())->filter(fn ($c) => ($c['t'] ?? null) === 'persona')->map(fn ($c) => (string) ($d[$c['id'].'_dni'] ?? ''))->push((string) ($d['dni'] ?? ''))->filter()->all();
    }

    /** las personas con DNI o celular quedan en Clientes, con su trato, estado civil y cónyuge */
    public function guardarPersonas(Motor $m, array $d): array
    {
        $ids = [];
        if ($m->esCv()) {
            // en el currículum, la persona es quien lo pide
            $d = ['cv_nombre' => $d['nombre'] ?? '', 'cv_dni' => $d['dni'] ?? '', 'cv_cel' => $d['cel'] ?? '', 'cv_dom' => $d['dirCv'] ?? ''];
            $personas = [['t' => 'persona', 'id' => 'cv']];
        }
        foreach ($personas ?? $m->definicionCampos() as $c) {
            if (($c['t'] ?? null) !== 'persona' || (! isset($personas) && ! $m->visible($c, $d))) {
                continue;
            }
            $p = $c['id'];
            $nombre = trim((string) ($d[$p.'_nombre'] ?? ''));
            $dni = preg_replace('/\D/', '', (string) ($d[$p.'_dni'] ?? ''));
            $dni = Valida::documento($dni) ? $dni : '';     // un DNI incompleto no se guarda en la ficha del cliente
            $cel = Texto::cel9($d[$p.'_cel'] ?? '');
            $cel = strlen($cel) === 9 ? $cel : '';
            if ($nombre === '' || ($dni === '' && $cel === '')) {
                continue;
            }
            $cl = Cliente::where(fn ($q) => $q->when($dni !== '', fn ($x) => $x->where('documento', $dni))->when($cel !== '', fn ($x) => $x->orWhere('celular', $cel)))->first();
            $cl ??= new Cliente(['uid' => Texto::nuevoUid(), 'nombre' => $nombre]);
            if (! $cl->nombre || preg_match('/^Cliente \d{4}$/', $cl->nombre)) {
                $cl->nombre = $nombre;
            }
            $cl->documento = $dni ?: $cl->documento;
            $cl->celular = $cl->celular ?: ($cel ?: null);
            $cl->direccion = trim((string) ($d[$p.'_dom'] ?? '')) ?: $cl->direccion;
            $extra = $cl->extra ?? [];
            foreach (['trato' => '_trato', 'ec' => '_ec', 'cony' => '_cony', 'conyDni' => '_conyDni'] as $k => $suf) {
                if (trim((string) ($d[$p.$suf] ?? '')) !== '') {
                    $extra[$k] = trim((string) $d[$p.$suf]);
                }
            }
            $cl->extra = $extra ?: null;
            $cl->save();
            $ids[] = $cl->id;
        }

        return $ids;
    }

    /** el texto del documento; los del sistema anterior se leen de su HTML la primera vez */
    public function texto(Documento $doc): string
    {
        if (Curriculum::es($doc->plantilla)) {
            return '';
        }
        if ($doc->texto === null && $doc->html) {
            $doc->texto = Formato::desdeHtml($doc->html);
            $doc->save();
        }

        return (string) $doc->texto;
    }

    /** guarda el texto corregido a mano */
    public function corregir(Documento $doc, string $texto): void
    {
        $x = $doc->datos ?? [];
        $x['editado'] = true;
        $doc->update(['texto' => mb_substr($texto, 0, 200000), 'datos' => $x, 'paginas' => null]);
    }

    // ------------------------------------------------------------ PDF, Word y cobro

    /** el PDF en A4 (docPDF); de paso anota cuántas páginas salen */
    public function pdf(Documento $doc): DocPdf
    {
        $pdf = Curriculum::es($doc->plantilla)
            ? Pdf::loadView('pdf.cv', ['d' => $doc->campos(), 'diseno' => $doc->plantilla])->setPaper('a4')
            : Pdf::loadView('pdf.redaccion', ['titulo' => $doc->titulo ?: 'Documento', 'bloques' => Formato::bloques($this->texto($doc))])->setPaper('a4');
        $pdf->render();
        $n = max(1, (int) $pdf->getDomPDF()->getCanvas()->get_page_count());
        if ($doc->paginas !== $n) {
            $doc->paginas = $n;
            $doc->saveQuietly();
        }

        return $pdf;
    }

    /** cuántas páginas A4 tiene (se calcula una vez y se guarda hasta que cambie el texto) */
    public function paginas(Documento $doc): int
    {
        if ($doc->paginas) {
            return $doc->paginas;
        }
        if ($this->texto($doc) === '' && ! Curriculum::es($doc->plantilla)) {
            return 1;
        }
        $this->pdf($doc);

        return (int) $doc->paginas;
    }

    /** el documento para Word (HTML que Word abre como documento .doc) */
    public function word(Documento $doc): string
    {
        if (Curriculum::es($doc->plantilla)) {
            return "\u{FEFF}".view('redaccion.cv-word', ['d' => $doc->campos(), 'diseno' => $doc->plantilla])->render();
        }

        return "\u{FEFF}".view('redaccion.word', ['titulo' => $doc->titulo ?: 'Documento', 'bloques' => Formato::bloques($this->texto($doc))])->render();
    }

    /** nombre del archivo: el modelo y la primera persona (nombreArch del sistema anterior) */
    public function archivo(Documento $doc): string
    {
        $m = ModeloRedaccion::where('uid', $doc->plantilla)->value('nombre') ?? $doc->titulo ?? 'documento';
        $quien = trim(explode(' y ', (string) $doc->partes)[0]);

        return Str::limit(Str::slug(Texto::sunat($m.' '.$quien)), 60, '') ?: 'documento';
    }

    /** cómo se cobra: por página (contratos), currículum o por documento */
    public function cobroDe(Documento $doc): string
    {
        $c = ModeloRedaccion::where('uid', $doc->plantilla)->value('cobro');

        return in_array($c, ['pagina', 'cv'], true) ? $c : 'doc';
    }

    /** ejemplares que se imprimen: los que dice el contrato (2 si no lo dice), o 1 */
    public function ejemplares(Documento $doc): int
    {
        $m = ModeloRedaccion::where('uid', $doc->plantilla)->first();
        if (Curriculum::es($doc->plantilla)) {
            return 1;
        }

        return max(1, (int) ($doc->campos()['ejemplares'] ?? 0) ?: ($m?->niveles ? 2 : 1));
    }

    /**
     * El cobro del documento (cobrarDoc): la redacción (por página o por documento) y los ejemplares
     * adicionales como impresión B/N. Devuelve el detalle para mostrar y las líneas para la caja.
     */
    public function cobro(Documento $doc, int $ejemplares): array
    {
        $cobro = $this->cobroDe($doc);
        $prod = self::producto($cobro);
        if (! $prod || $prod->oculto) {
            $falta = ['pagina' => 'Contrato (por página)', 'cv' => 'Currículum vitae'][$cobro] ?? 'Solicitud o documento';
            throw new ErrorNegocio('Falta el producto «'.$falta.'» en tu catálogo. Agrégalo en Ajustes › Productos.');
        }
        $n = $this->paginas($doc);
        $porPag = $cobro === 'pagina';
        $op = Curriculum::DISENOS[$doc->plantilla][1] ?? 0;
        $unit = self::precio($cobro, $op);
        $redac = $porPag ? $unit * $n : $unit;
        $bn = Producto::with('opciones')->where('uid', 'bn_a4')->first();
        $bnP = $bn && ! $bn->oculto ? (int) ($bn->opciones->first()?->precio ?? 0) : 0;
        $ej = $bnP ? max(1, min(50, $ejemplares)) : 1;
        $extra = ($ej - 1) * $n;
        $t = (string) ($doc->titulo ?: $prod->nombre);
        $det = $cobro === 'cv'
            ? mb_substr(($prod->opciones->get($op)?->etiqueta ?? 'Currículum').' – '.($doc->partes ?? ''), 0, 60)
            : mb_substr(mb_strtoupper(mb_substr($t, 0, 1)).mb_strtolower(mb_substr($t, 1)), 0, 60);
        $lineas = [['pid' => $prod->id, 'nombre' => $prod->nombre, 'det' => $det, 'cant' => $porPag ? $n : 1, 'precio' => $unit, 'doc' => $doc->uid]];
        if ($extra > 0) {
            $lineas[] = ['pid' => $bn->id, 'nombre' => $bn->nombre, 'det' => 'Ejemplares de documento', 'cant' => $extra, 'precio' => $bnP, 'doc' => $doc->uid];
        }

        return ['porPag' => $porPag, 'paginas' => $n, 'unit' => $unit, 'redac' => $redac, 'bnP' => $bnP, 'ej' => $ej, 'extra' => $extra,
            'total' => $redac + $extra * $bnP, 'lineas' => $lineas];
    }

    /** las líneas que se agregan a la caja */
    public function lineasCobro(Documento $doc, int $ejemplares): array
    {
        try {
            return $this->cobro($doc, $ejemplares)['lineas'];
        } catch (ErrorNegocio) {
            return [];
        }
    }

    public function entregar(Documento $doc): void
    {
        $doc->update(['estado' => 'entregado', 'entregado_at' => now()]);
        Bitacora::registrar('documento', 'Entregado: '.($doc->titulo ?: 'documento').($doc->partes ? ' ('.$doc->partes.')' : ''));
    }
}
