<?php

namespace App\Livewire;

use App\Models\Cliente;
use App\Models\Documento;
use App\Models\ModeloRedaccion;
use App\Models\Venta;
use App\Redaccion\Catalogo;
use App\Redaccion\Curriculum;
use App\Redaccion\Formato;
use App\Redaccion\Motor;
use App\Services\ErrorNegocio;
use App\Services\Redaccion as Srv;
use App\Support\Texto;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

/** Un documento: el formulario con sus datos y cláusulas, y la hoja redactada (renderDocForm y renderDocView). */
#[Title('Redacción')]
class RedaccionDocumento extends Component
{
    #[Locked]
    public string $modelo = '';

    #[Locked]
    public ?string $uid = null;

    #[Locked]
    public string $vista = 'form';

    public array $d = [];

    /** búsqueda de clientes guardados por persona */
    public array $busca = [];

    /** faltan datos o hay observaciones: pedir confirmación antes de generar */
    #[Locked]
    public array $avisos = [];

    /** ventana de cláusula propia: nuevo o el id */
    #[Locked]
    public ?string $clEdit = null;

    public string $clH = '';

    public string $clT = '';

    /** ventana de cobro abierta y los ejemplares que se imprimen */
    #[Locked]
    public bool $cobrando = false;

    public int|string $ej = 1;

    public function mount(?string $modelo = null, ?string $uid = null): void
    {
        Catalogo::alDia();
        if ($uid) {
            $doc = Documento::where('uid', $uid)->firstOrFail();
            $this->uid = $doc->uid;
            $this->modelo = (string) $doc->plantilla;
            $this->d = $doc->campos();
            $this->vista = 'doc';
            if ($this->m()) {
                $this->d += $this->motor()->iniciales();
            }

            return;
        }
        $this->modelo = (string) $modelo;
        abort_unless($this->m(), 404);
        $this->d = $this->motor()->iniciales();
        if ($base = request()->query('base')) {
            $b = Documento::where('uid', $base)->first();
            if ($b && $b->plantilla === $this->modelo) {
                $this->d = array_merge($this->d, $b->campos(), ['fecha' => today()->toDateString()]);
                session()->now('toast', 'Datos copiados: cambia lo necesario');
            }
        }
    }

    private ?ModeloRedaccion $mCache = null;

    private function m(): ?ModeloRedaccion
    {
        return $this->mCache ??= ModeloRedaccion::where('uid', $this->modelo)->first();
    }

    private function motor(): Motor
    {
        return new Motor($this->m());
    }

    private function doc(): ?Documento
    {
        return $this->uid ? Documento::where('uid', $this->uid)->first() : null;
    }

    // ------------------------------------------------------------ formulario

    public function nivel(int $n): void
    {
        if (isset(Motor::NIVELES[$n])) {
            $this->d['nivel'] = $n;
            $this->d['_cl']['on'] = [];
            $this->dispatch('toast', texto: 'Nivel '.mb_strtolower(Motor::NIVELES[$n][0]));
        }
    }

    public function clausula(string $id, bool $on): void
    {
        if ($id !== 'juris') {
            $this->d['_cl']['on'][$id] = $on;
        }
    }

    public function mover(string $id, int $dir): void
    {
        $ids = array_column($this->motor()->clausulas($this->d), 'id');
        $i = array_search($id, $ids, true);
        $j = $i === false ? -1 : $i + $dir;
        if ($j >= 0 && $j < count($ids)) {
            [$ids[$i], $ids[$j]] = [$ids[$j], $ids[$i]];
            $this->d['_cl']['orden'] = $ids;
        }
    }

    public function abrirClausula(?string $id = null): void
    {
        $c = collect($this->d['_cl']['custom'] ?? [])->firstWhere('id', $id);
        $this->clEdit = $c ? $id : 'nuevo';
        $this->clH = (string) ($c['h'] ?? '');
        $this->clT = (string) ($c['t'] ?? '');
    }

    public function guardarClausula(): void
    {
        $h = mb_substr(trim($this->clH), 0, 80);
        $t = trim($this->clT);
        if ($h === '' || $t === '') {
            $this->dispatch('toast', texto: 'Pon el título y el texto');

            return;
        }
        $custom = $this->d['_cl']['custom'] ?? [];
        if ($this->clEdit !== 'nuevo') {
            $custom = array_map(fn ($c) => $c['id'] === $this->clEdit ? ['id' => $c['id'], 'h' => $h, 't' => $t] : $c, $custom);
        } else {
            $id = 'c'.substr(Texto::nuevoUid(), -6);
            $custom[] = ['id' => $id, 'h' => $h, 't' => $t];
            if (! empty($this->d['_cl']['orden'])) {
                $ord = $this->d['_cl']['orden'];
                array_splice($ord, ($j = array_search('juris', $ord, true)) === false ? count($ord) : $j, 0, [$id]);
                $this->d['_cl']['orden'] = $ord;
            }
        }
        $this->d['_cl']['custom'] = array_values($custom);
        $this->clEdit = null;
        $this->dispatch('toast', texto: 'Cláusula guardada');
    }

    public function quitarClausula(string $id): void
    {
        $this->d['_cl']['custom'] = array_values(array_filter($this->d['_cl']['custom'] ?? [], fn ($c) => $c['id'] !== $id));
    }

    public function cerrarVentana(): void
    {
        $this->clEdit = null;
        $this->avisos = [];
    }

    /** toca una frase lista: en áreas de texto se agrega, en campos cortos reemplaza */
    public function frase(string $campo, int $i): void
    {
        $c = collect($this->motor()->definicionCampos())->firstWhere('id', $campo);
        if ($c && ($f = Motor::frasesDe($c, $this->d)[$i] ?? null)) {
            $this->d[$campo] = self::sumarFrase($c, (string) ($this->d[$campo] ?? ''), $f);
        }
    }

    /** en áreas de texto la frase se agrega; en campos cortos reemplaza */
    private static function sumarFrase(array $c, string $cur, string $f): string
    {
        $cur = trim($cur);
        if (($c['type'] ?? '') !== 'area' || $cur === '') {
            return $f;
        }

        return $cur.match ($c['modo'] ?? '') {
            'parrafos' => "\n\n", 'lista' => "\n", default => preg_match('/[.,;]$/', $cur) ? ' ' : '; '
        }.$f;
    }

    // ------------------------------------------------------------ listas del currículum (estudios, experiencia, cursos)

    private function lista(string $id): ?array
    {
        $c = collect($this->motor()->definicionCampos())->first(fn ($x) => ($x['t'] ?? null) === 'lista' && $x['id'] === $id);

        return $c ?: null;
    }

    public function agregarItem(string $lista): void
    {
        if ($this->lista($lista)) {
            $this->d[$lista] = [...array_values(array_filter((array) ($this->d[$lista] ?? []), 'is_array')), []];
        }
    }

    public function quitarItem(string $lista, int $i): void
    {
        if ($this->lista($lista) && isset($this->d[$lista][$i])) {
            $L = (array) $this->d[$lista];
            unset($L[$i]);
            $this->d[$lista] = array_values($L) ?: [[]];
        }
    }

    public function fraseItem(string $lista, int $i, string $campo, int $fi): void
    {
        $c = collect($this->lista($lista)['campos'] ?? [])->firstWhere('id', $campo);
        if ($c && isset($this->d[$lista][$i]) && ($f = Motor::frasesDe($c, $this->d)[$fi] ?? null)) {
            $this->d[$lista][$i][$campo] = self::sumarFrase($c, (string) ($this->d[$lista][$i][$campo] ?? ''), $f);
        }
    }

    /** la foto llega ya recortada y comprimida desde el navegador (300 × 378, JPEG), como en el sistema anterior */
    public function ponerFoto(string $foto): void
    {
        if (strlen($foto) > 600000 || ! preg_match('#^data:image/jpeg;base64,[A-Za-z0-9+/=]+$#', $foto)) {
            $this->dispatch('toast', texto: 'No se pudo leer la foto');

            return;
        }
        $this->d['foto'] = $foto;
        $this->dispatch('toast', texto: 'Foto lista');
    }

    public function quitarFoto(): void
    {
        unset($this->d['foto']);
    }

    /** trae los datos de un cliente guardado */
    public function cargarCliente(string $p, int $id): void
    {
        $c = Cliente::find($id);
        if (! $c) {
            return;
        }
        $e = $c->extra ?? [];
        $this->d = array_merge($this->d, array_filter([$p.'_nombre' => $c->nombre, $p.'_dni' => $c->documento, $p.'_dom' => $c->direccion, $p.'_cel' => $c->celular,
            $p.'_trato' => $e['trato'] ?? null, $p.'_ec' => $e['ec'] ?? null, $p.'_cony' => $e['cony'] ?? null, $p.'_conyDni' => $e['conyDni'] ?? null], fn ($v) => $v !== null && $v !== ''));
        $this->busca[$p] = '';
        $this->dispatch('toast', texto: 'Datos de '.Texto::primerNombre($c->nombre).' cargados');
    }

    public function generar(bool $igual = false): void
    {
        $m = $this->motor();
        $faltan = $m->faltan($this->d);
        $obs = $m->observaciones($this->d);
        $doc = $this->doc();
        // las correcciones hechas a mano en la hoja se pierden al volver a generar: se avisa junto con lo que falta
        $editado = $doc && ! empty($doc->datos['editado']);
        if (! $igual && ($faltan || $obs || $editado)) {
            $this->avisos = ['faltan' => $faltan, 'obs' => $obs, 'editado' => $editado];

            return;
        }
        $this->avisos = [];
        $doc = app(Srv::class)->generar($doc, $this->m(), $this->d, Auth::user());
        $this->uid = $doc->uid;
        $this->vista = 'doc';
        $this->dispatch('redaccion-url', url: route('redaccion.doc', $doc->uid));
    }

    public function editarDatos(): void
    {
        $this->vista = $this->m() ? 'form' : 'doc';
    }

    // ------------------------------------------------------------ documento hecho

    /** el texto corregido tocando la hoja (se guarda solo, sin volver a dibujar la hoja) */
    public function corregir(string $texto, Srv $srv): void
    {
        $this->skipRender();
        $doc = $this->doc();
        if ($doc && trim($texto) !== '' && $texto !== $doc->texto) {
            $srv->corregir($doc, $texto);
        }
    }

    public function abrirCobro(Srv $srv): void
    {
        $doc = $this->doc();
        if ($doc) {
            $this->ej = $srv->ejemplares($doc);
            $this->cobrando = true;
        }
    }

    public function cerrarCobro(): void
    {
        $this->cobrando = false;
    }

    /** a la caja: se suma a lo que ya había y se abre el cobro */
    public function agregarAlCobro()
    {
        return $this->uid ? $this->redirectRoute('vender', ['documento' => $this->uid, 'ej' => max(1, (int) $this->ej)]) : null;
    }

    /** cambia el diseño del currículum sin perder los datos */
    public function diseno(string $id): void
    {
        $doc = $this->doc();
        if (! $doc || ! Curriculum::es($doc->plantilla) || ! Curriculum::es($id) || $id === $doc->plantilla) {
            return;
        }
        $doc->update(['plantilla' => $id, 'paginas' => null]);
        $this->modelo = $id;
        $this->mCache = null;
    }

    /** color, letra, foto, columna o tamaño del currículum moderno */
    public function tema(string $k, string $v): void
    {
        $doc = $this->doc();
        if (! $doc || $doc->plantilla !== 'cv_moderno' || ! (isset(Curriculum::TEMA_OPCIONES[$k][1][$v]) || ($k === 'color' && preg_match('/^#[0-9a-f]{6}$/i', $v)))) {
            return;
        }
        $d = $doc->campos();
        $d['tema'] = [...Curriculum::temaDe($d), $k => $v];
        $doc->fijarCampos($d);
        $doc->paginas = null;
        $doc->save();
        $this->d['tema'] = $d['tema'];
    }

    public function entregar(Srv $srv): void
    {
        $doc = $this->doc();
        if ($doc && $doc->estado === 'cobrado') {
            $srv->entregar($doc);
            $this->dispatch('toast', texto: 'Marcado como entregado');
        }
    }

    // ------------------------------------------------------------ vista

    /** las tarjetas del formulario: [titulo, persona, campos] */
    private function tarjetas(Motor $m): array
    {
        $out = [];
        $cur = ['titulo' => 'Datos generales', 'persona' => null, 'campos' => []];
        foreach ($m->definicionCampos() as $c) {
            if (! $m->visible($c, $this->d)) {
                continue;
            }
            $t = $c['t'] ?? null;
            if ($t === 'lista') {
                if ($cur['campos'] || $cur['persona']) {
                    $out[] = $cur;
                }
                $out[] = ['titulo' => $c['label'], 'persona' => null, 'lista' => $c, 'campos' => []];
                $cur = ['titulo' => 'Datos del documento', 'persona' => null, 'campos' => []];

                continue;
            }
            if ($t === 'persona' || $t === 'h') {
                if ($cur['campos'] || $cur['persona']) {
                    $out[] = $cur;
                }
                $cur = $t === 'persona'
                    ? ['titulo' => $c['label'], 'persona' => $c['id'], 'campos' => array_values(array_filter(Motor::camposPersona($c), fn ($f) => $m->visible($f, $this->d)))]
                    : ['titulo' => $c['label'], 'persona' => null, 'campos' => []];
                if ($t === 'persona') {
                    $out[] = $cur;
                    $cur = ['titulo' => 'Datos del documento', 'persona' => null, 'campos' => []];
                }

                continue;
            }
            $cur['campos'][] = $c;
        }
        if ($cur['campos']) {
            $out[] = $cur;
        }

        return $out;
    }

    public function render(Srv $srv)
    {
        $m = $this->m() ? $this->motor() : null;
        $datos = ['mod' => $this->m(), 'motor' => $m, 'nv' => Motor::nivel($this->d)];
        if ($this->vista === 'form' && $m) {
            $datos['tarjetas'] = $this->tarjetas($m);
            $datos['clausulas'] = $m->clausulas($this->d);
            $datos['hallados'] = collect($this->busca)->filter(fn ($q) => trim($q) !== '')->map(function ($q) {
                $dq = preg_replace('/\D/', '', $q);

                return Cliente::where(fn ($x) => $x->where('nombre', 'like', '%'.trim($q).'%')
                    ->when(strlen($dq) >= 3, fn ($y) => $y->orWhere('documento', 'like', '%'.$dq.'%')->orWhere('celular', 'like', '%'.$dq.'%')))->limit(5)->get();
            });
        } else {
            $doc = $this->doc();
            $texto = $doc ? $srv->texto($doc) : '';
            $datos['doc'] = $doc;
            $datos['cv'] = $doc && Curriculum::es($doc->plantilla);
            $datos['bloques'] = Formato::bloques($texto);
            $datos['faltan'] = Formato::faltan($texto);
            $datos['paginas'] = $doc && $texto !== '' ? $srv->paginas($doc) : null;
            $datos['ticket'] = $doc?->venta_uid ? Venta::where('uid', $doc->venta_uid)->value('numero') : null;
            if ($this->cobrando && $doc) {
                try {
                    $datos['cobro'] = $srv->cobro($doc, max(1, (int) $this->ej));
                } catch (ErrorNegocio $e) {
                    $datos['cobroError'] = $e->getMessage();
                }
            }
        }

        return view('livewire.redaccion-documento', $datos)->title($this->m()?->nombre ?? 'Documento');
    }
}
