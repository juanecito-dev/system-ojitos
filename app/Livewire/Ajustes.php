<?php

namespace App\Livewire;

use App\Livewire\Concerns\ConAutorizacion;
use App\Models\ModuloNegocio;
use App\Models\Producto;
use App\Models\ProductoOpcion;
use App\Models\Venta;
use App\Models\VentaItem;
use App\Services\Bitacora;
use App\Services\Comprobantes;
use App\Services\ErrorNegocio;
use App\Services\ImportadorCopia;
use App\Services\Numeracion;
use App\Services\Stock;
use App\Support\AjustesNegocio;
use App\Support\Catalogos;
use App\Support\Dinero;
use App\Support\NegocioActual;
use App\Support\Texto;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Title('Configuración')]
class Ajustes extends Component
{
    use ConAutorizacion, WithFileUploads;

    public const PESTANAS = [
        'negocio' => ['Negocio', 'negocio'], 'ticket' => ['Ticket', 'negocio'], 'cobros' => ['Cobros', 'negocio'],
        'fact' => ['Facturación', 'negocio'], 'maq' => ['Máquinas', 'negocio'], 'productos' => ['Productos y precios', 'precios'],
        'seguridad' => ['Seguridad', 'negocio'], 'datos' => ['Datos y copias', 'datos'],
    ];

    public const NEG_LABEL = ['nombre' => 'Nombre del local', 'giro' => 'Rubro', 'titular' => 'Titular', 'ruc' => 'RUC', 'direccion' => 'Dirección',
        'celular' => 'Celular', 'ciudad' => 'Ciudad', 'meta' => 'Meta de venta diaria', 'serieB' => 'Serie de boletas', 'serieF' => 'Serie de facturas',
        'regimen' => 'Régimen', 'igv' => 'IGV', 'autoLock' => 'Bloqueo por inactividad', 'yapeCel' => 'Número de Yape/Plin', 'yapeNom' => 'Nombre de Yape/Plin',
        'tkPie' => 'Mensaje del ticket', 'tkAncho' => 'Ancho del ticket', 'rubro' => 'Tipo de negocio'];

    #[Url(as: 'p')]
    public string $pestana = '';

    public string $buscar = '';

    public $imagen;

    #[Locked]
    public string $imagenDe = '';

    public $copia;

    public array $log = [];

    public string $error = '';

    // formularios de productos
    #[Locked]
    public ?string $ventana = null;   // nuevo | masivo | grupo

    public array $nuevo = ['nombre' => '', 'grupo' => '', 'grupoNuevo' => '', 'precio' => '', 'stock' => '', 'costo' => '', 'cb' => '', 'rapido' => true];

    public array $masivo = ['grupo' => '', 'modo' => 'pct', 'valor' => '', 'redondeo' => 1];

    public array $renombrar = ['grupo' => '', 'nombre' => ''];

    public string $equipo = '';

    public string $equipoAncho = '';

    public function mount(): void
    {
        $tabs = $this->pestanas();
        abort_if(! $tabs, 403, 'Tu usuario no puede cambiar la configuración.');
        if (! isset($tabs[$this->pestana])) {
            $this->pestana = array_key_first($tabs);
        }
        $this->equipo = (string) request()->cookie('equipo');
        $this->equipoAncho = (string) request()->cookie('ticket_ancho');
    }

    public function updatedPestana(): void
    {
        $tabs = $this->pestanas();
        if (! isset($tabs[$this->pestana])) {
            $this->pestana = array_key_first($tabs);
        }
    }

    public function pestanas(): array
    {
        $yo = Auth::user();

        return array_filter(self::PESTANAS, fn ($t) => $yo->puede($t[1]));
    }

    private function exigir(string $permiso): void
    {
        abort_unless(Auth::user()->puede($permiso), 403);
    }

    private function neg()
    {
        return app(NegocioActual::class)->obligatorio();
    }

    // ------------------------------------------------------------ negocio, ticket, cobros, facturación, seguridad

    /** guarda un dato del negocio (columna o ajuste) y lo anota en la actividad */
    public function fijar(string $k, mixed $v): void
    {
        $this->exigir('negocio');
        $neg = $this->neg();
        $columnas = ['nombre', 'giro', 'titular', 'ruc', 'direccion', 'celular', 'ciudad'];
        $permitidos = array_merge($columnas, ['meta', 'tkPie', 'tkAncho', 'tkQr', 'yapeCel', 'yapeNom', 'autoLock', 'regimen', 'igv', 'serieB', 'serieF']);
        $esUlt = (bool) preg_match('/^ult_[A-Z0-9]{4}$/', $k);
        if (! in_array($k, $permitidos, true) && ! $esUlt) {
            return;
        }
        $v = is_string($v) ? trim($v) : $v;
        if (in_array($k, ['serieB', 'serieF'], true)) {
            $v = strtoupper(substr($v, 0, 4));
        }
        if ($esUlt) {
            $v = preg_replace('/\D/', '', (string) $v);
        }
        // se quitan espacios, guiones y el +51; si trae letras no se toca y la revisión lo rechaza
        if (in_array($k, ['celular', 'yapeCel', 'ruc'], true) && $v !== '' && preg_match('/^[\d\s+().-]+$/', (string) $v)) {
            $v = $k === 'ruc' ? preg_replace('/\D/', '', (string) $v) : Texto::cel9((string) $v);
        }
        if ($k === 'nombre' && $v === '') {
            return;
        }
        if (($error = AjustesNegocio::problema($k, $v)) !== '') {
            $this->dispatch('toast', texto: $error);

            return;
        }
        if ($k === 'meta' && $v !== '') {
            $v = Dinero::n(Dinero::aCentimos($v));
        }
        $antes = match (true) {
            $esUlt => app(Numeracion::class)->valor('ult:'.substr($k, 4)) ?: '',
            in_array($k, $columnas, true) => $neg->{$k},
            default => $neg->ajuste($k),
        };
        if ((string) $antes === (string) $v) {
            return;
        }
        if ($esUlt) {
            app(Numeracion::class)->fijar('ult:'.substr($k, 4), (int) $v);
        } elseif (in_array($k, $columnas, true)) {
            $neg->{$k} = $v === '' ? null : $v;
            $neg->save();
        } else {
            $neg->guardarAjuste($k, $v === false ? null : $v);
        }
        if (! $esUlt) {
            Bitacora::registrar('config', (self::NEG_LABEL[$k] ?? $k).': «'.mb_substr((string) $antes, 0, 60).'» → «'.mb_substr(is_bool($v) ? ($v ? 'sí' : 'no') : (string) $v, 0, 60).'»');
        }
        $this->dispatch('toast', texto: 'Guardado');
    }

    public function cambiarRubro(string $rubro, bool $aplicarModulos): void
    {
        $this->exigir('negocio');
        if (! isset(Catalogos::RUBROS[$rubro])) {
            return;
        }
        $neg = $this->neg();
        if ($aplicarModulos) {
            foreach (Catalogos::MODULOS_OPCIONALES as $m) {
                ModuloNegocio::updateOrCreate(['negocio_id' => $neg->id, 'modulo' => $m], ['activo' => ! in_array($m, Catalogos::RUBROS[$rubro][1], true)]);
            }
        }
        $neg->update(['rubro' => $rubro]);
        Bitacora::registrar('config', 'Tipo de negocio: '.Catalogos::RUBROS[$rubro][0]);
        $this->dispatch('toast', texto: 'Guardado');
    }

    public function modulo(string $m, bool $on): void
    {
        $this->exigir('negocio');
        if (! in_array($m, Catalogos::MODULOS_OPCIONALES, true)) {
            return;
        }
        ModuloNegocio::updateOrCreate(['negocio_id' => $this->neg()->id, 'modulo' => $m], ['activo' => $on]);
        Bitacora::registrar('config', ($on ? 'Encendió' : 'Apagó').' el módulo '.Catalogos::MODULOS[$m][0]);
    }

    public function metodoActivo(string $m, bool $on): void
    {
        $this->exigir('negocio');
        if ($m === 'efectivo' || ! isset(Catalogos::METODOS[$m])) {
            return;
        }
        $neg = $this->neg();
        $off = array_values(array_diff($neg->metodosApagados(), [$m]));
        if (! $on) {
            $off[] = $m;
        }
        $neg->guardarAjuste('metOff', $off);
        Bitacora::registrar('config', ($on ? 'Activó' : 'Apagó').' el método de pago '.$neg->nombreMetodo($m));
        $this->dispatch('toast', texto: 'Guardado');
    }

    public function metodoNombre(string $m, string $nombre): void
    {
        $this->exigir('negocio');
        if (! isset(Catalogos::METODOS[$m])) {
            return;
        }
        $neg = $this->neg();
        $noms = $neg->ajuste('metNom', []);
        $nombre = mb_substr(trim($nombre), 0, 24);
        if ($nombre === '' || $nombre === Catalogos::METODOS[$m]) {
            unset($noms[$m]);
        } else {
            $noms[$m] = $nombre;
        }
        $neg->guardarAjuste('metNom', $noms ?: null);
        Bitacora::registrar('config', 'Método de pago '.Catalogos::METODOS[$m].' ahora se llama «'.($nombre ?: Catalogos::METODOS[$m]).'»');
        $this->dispatch('toast', texto: 'Nombre guardado');
    }

    /** logo o QR: se achica y se guarda como imagen dentro del negocio */
    public function subirImagen(string $cual): void
    {
        $this->exigir('negocio');
        $this->imagenDe = in_array($cual, ['logo', 'qr_yape', 'qr_plin'], true) ? $cual : '';
    }

    public function updatedImagen(): void
    {
        $this->exigir('negocio');
        $cual = $this->imagenDe;
        if (! $cual || ! $this->imagen) {
            return;
        }
        $this->validate(['imagen' => 'image|max:8192']);
        $url = self::achicar($this->imagen->getRealPath(), $cual === 'logo' ? 360 : 480);
        if (! $url) {
            $this->dispatch('toast', texto: 'No se pudo leer la imagen');

            return;
        }
        $neg = $this->neg();
        $neg->{$cual} = $url;
        $neg->save();
        $this->imagen = null;
        Bitacora::registrar('config', $cual === 'logo' ? 'Cambió el logo del ticket' : 'Cambió el QR de '.($cual === 'qr_yape' ? 'Yape' : 'Plin'));
        $this->dispatch('toast', texto: $cual === 'logo' ? 'Logo guardado' : 'QR guardado');
    }

    public function quitarImagen(string $cual): void
    {
        $this->exigir('negocio');
        if (in_array($cual, ['logo', 'qr_yape', 'qr_plin'], true)) {
            $neg = $this->neg();
            $neg->{$cual} = null;
            $neg->save();
        }
    }

    public static function achicar(string $ruta, int $max): ?string
    {
        $data = @file_get_contents($ruta);
        $img = $data ? @imagecreatefromstring($data) : false;
        if (! $img) {
            return null;
        }
        $w = imagesx($img);
        $h = imagesy($img);
        $k = min(1, $max / max($w, $h));
        $nw = max(1, (int) round($w * $k));
        $nh = max(1, (int) round($h * $k));
        $out = imagecreatetruecolor($nw, $nh);
        imagefill($out, 0, 0, imagecolorallocate($out, 255, 255, 255));
        imagecopyresampled($out, $img, 0, 0, 0, 0, $nw, $nh, $w, $h);
        ob_start();
        imagepng($out, null, 9);
        $png = ob_get_clean();
        if (strlen($png) > 110000) {
            ob_start();
            imagejpeg($out, null, 85);

            return 'data:image/jpeg;base64,'.base64_encode(ob_get_clean());
        }

        return 'data:image/png;base64,'.base64_encode($png);
    }

    // ------------------------------------------------------------ productos

    public function productoCampo(int $id, string $campo, string $valor): void
    {
        $this->exigir('precios');
        $p = Producto::with('opciones')->find($id);
        if (! $p) {
            return;
        }
        $valor = trim($valor);
        switch ($campo) {
            case 'nombre':
                if ($valor === '' || $valor === $p->nombre) {
                    return;
                }
                Bitacora::registrar('producto', 'Renombró «'.$p->nombre.'» a «'.$valor.'»');
                $p->update(['nombre' => $valor]);
                break;
            case 'costo':
                if (! Auth::user()->puede('costos')) {
                    return;
                }
                $c = $valor === '' ? null : Dinero::aCosto($valor);
                if ($valor !== '' && ($c === null || $c < 0)) {
                    $this->dispatch('toast', texto: 'Escribe el costo, por ejemplo 0.42');

                    return;
                }
                Bitacora::registrar('precio', 'Costo de '.$p->nombre.': '.($p->costo ? Dinero::sCosto($p->costo) : '—').' → '.($c ? Dinero::sCosto($c) : '—'));
                $p->update(['costo' => $c]);
                break;
            case 'stock':
                if ($valor === '') {
                    app(Stock::class)->quitarControl($p->id);
                } elseif (is_numeric($valor) && $valor >= 0) {
                    app(Stock::class)->fijar($p->id, (float) $valor);
                } else {
                    $this->dispatch('toast', texto: 'Escribe un número, por ejemplo 24');

                    return;
                }
                break;
            case 'codigos':
                $codes = array_values(array_filter(preg_split('/[\s,;]+/', $valor)));
                foreach ($codes as $c) {
                    $dup = Producto::where('id', '!=', $p->id)->get()->first(fn ($x) => in_array($c, $x->codigos(), true));
                    if ($dup) {
                        $this->dispatch('toast', texto: 'Ese código ya es de '.$dup->nombre);

                        return;
                    }
                }
                $p->update(['codigos_barra' => $codes ? implode(' ', $codes) : null]);
                break;
            case 'favorito':
                $p->update(['favorito' => ! $p->favorito]);
                break;
            default:
                if (preg_match('/^op(\d+)$/', $campo, $m)) {
                    $o = $p->opciones[(int) $m[1]] ?? null;
                    $c = Dinero::aCentimos($valor);
                    if (! $o || ! ($c > 0)) {
                        $this->dispatch('toast', texto: 'Escribe un precio válido, por ejemplo 0.50');

                        return;
                    }
                    if ($o->precio === $c) {
                        return;
                    }
                    Bitacora::registrar('precio', $p->nombre.($p->opciones->count() > 1 ? ' ('.($o->etiqueta ?: 'opción '.((int) $m[1] + 1)).')' : '').': '.Dinero::s($o->precio).' → '.Dinero::s($c));
                    $o->update(['precio' => $c]);
                } else {
                    return;
                }
        }
        $this->dispatch('toast', texto: 'Guardado');
    }

    public function quitarProducto(int $id): void
    {
        $this->exigir('precios');
        $p = Producto::find($id);
        if ($p) {
            $p->delete();
            Bitacora::registrar('producto', 'Quitó el producto '.$p->nombre);
            $this->dispatch('toast', texto: 'Quitado: '.$p->nombre, accion: ['label' => 'Deshacer', 'evento' => 'restaurarProducto', 'params' => ['id' => $id]]);
        }
    }

    #[On('restaurarProducto')]
    public function restaurarProducto(int $id): void
    {
        $this->exigir('precios');
        $p = Producto::withTrashed()->find($id);
        if ($p && $p->trashed()) {
            $p->restore();
            Bitacora::registrar('producto', 'Volvió a poner '.$p->nombre);
        }
    }

    public function abrir(string $v): void
    {
        $this->exigir('precios');
        $this->error = '';
        $this->ventana = in_array($v, ['nuevo', 'masivo', 'grupo'], true) ? $v : null;
        $g = Producto::orderBy('orden')->pluck('grupo')->unique()->values();
        $this->nuevo = ['nombre' => '', 'grupo' => $g->contains('Arte y manualidades') ? 'Arte y manualidades' : ($g->first() ?? ''), 'grupoNuevo' => '', 'precio' => '', 'stock' => '', 'costo' => '', 'cb' => '', 'rapido' => true];
        $this->masivo = ['grupo' => $g->first() ?? '', 'modo' => 'pct', 'valor' => '', 'redondeo' => 1];
        $this->renombrar = ['grupo' => $g->first() ?? '', 'nombre' => ''];
    }

    public function cerrar(): void
    {
        $this->ventana = null;
    }

    public function agregarProducto(): void
    {
        $this->exigir('precios');
        $n = $this->nuevo;
        $g = $n['grupo'] === '__nuevo' ? trim($n['grupoNuevo']) : $n['grupo'];
        $precio = Dinero::aCentimos($n['precio']);
        $this->error = match (true) {
            trim($n['nombre']) === '' => 'Escribe el nombre.',
            $g === '' => 'Escribe el nombre del grupo.',
            ! ($precio > 0) => 'Escribe el precio, por ejemplo 1.50',
            default => '',
        };
        $cb = trim($n['cb']);
        if (! $this->error && $cb !== '' && ($d = Producto::get()->first(fn ($x) => in_array($cb, $x->codigos(), true)))) {
            $this->error = 'Ese código ya es de '.$d->nombre.'.';
        }
        if ($this->error) {
            return;
        }
        $mismo = Producto::where('grupo', $g)->orderByDesc('orden')->first();
        $orden = $mismo ? $mismo->orden + 1 : (int) Producto::max('orden') + 1;
        Producto::where('orden', '>=', $orden)->increment('orden');
        $costo = Dinero::aCosto($n['costo']);
        $p = Producto::create(['uid' => 'c_'.Texto::nuevoUid(), 'grupo' => $g, 'nombre' => trim($n['nombre']), 'color' => $mismo->color ?? 't-otro',
            'rapido' => (bool) $n['rapido'], 'costo' => $costo > 0 ? $costo : null, 'codigos_barra' => $cb ?: null, 'orden' => $orden]);
        $p->opciones()->create(['etiqueta' => '', 'precio' => $precio, 'orden' => 0]);
        if (is_numeric($n['stock']) && $n['stock'] >= 0) {
            app(Stock::class)->fijar($p->id, (float) $n['stock']);
        }
        Bitacora::registrar('producto', 'Agregó el producto '.$p->nombre.' a '.Dinero::s($precio));
        $this->ventana = null;
        $this->dispatch('toast', texto: 'Agregado: '.$p->nombre);
    }

    /** cambios de precio por grupo: [[opcion_id, producto, etiqueta, antes, ahora]] */
    public function vistaMasivo(): array
    {
        $m = $this->masivo;
        $v = (float) str_replace(',', '.', (string) $m['valor']);
        $r = max(1, (int) $m['redondeo']);
        if (! $v) {
            return [];
        }
        $out = [];
        foreach (Producto::with('opciones')->where('grupo', $m['grupo'])->orderBy('orden')->get() as $p) {
            foreach ($p->opciones as $o) {
                $n = $m['modo'] === 'pct' ? $o->precio * (1 + $v / 100) : $o->precio + round($v * 100);
                $n = (int) max($r, round($n / $r) * $r);
                if ($n !== $o->precio) {
                    $out[] = [$o->id, $p->nombre, $p->opciones->count() > 1 ? $o->etiqueta : '', $o->precio, $n];
                }
            }
        }

        return $out;
    }

    public function aplicarMasivo(): void
    {
        $this->exigir('precios');
        $L = $this->vistaMasivo();
        if (! $L) {
            $this->error = 'No hay cambios que aplicar.';

            return;
        }
        foreach ($L as [$id, , , , $n]) {
            ProductoOpcion::where('id', $id)->update(['precio' => $n]);
        }
        Bitacora::registrar('precio', 'Cambió '.count($L).' precios de «'.$this->masivo['grupo'].'» ('.($this->masivo['modo'] === 'pct' ? $this->masivo['valor'].'%' : 'S/ '.$this->masivo['valor']).'): '
            .collect($L)->take(6)->map(fn ($x) => $x[1].' '.Dinero::n($x[3]).'→'.Dinero::n($x[4]))->join(', ').(count($L) > 6 ? '…' : ''));
        $this->ventana = null;
        $this->dispatch('toast', texto: count($L).' precios actualizados');
    }

    public function renombrarGrupo(): void
    {
        $this->exigir('precios');
        $g = $this->renombrar['grupo'];
        $n = trim($this->renombrar['nombre']);
        if ($n === '') {
            $this->error = 'Escribe el nombre nuevo.';

            return;
        }
        if ($n !== $g && Producto::where('grupo', $n)->exists()) {
            $this->error = 'Ya existe un grupo con ese nombre.';

            return;
        }
        Producto::where('grupo', $g)->update(['grupo' => $n]);
        Bitacora::registrar('producto', 'Renombró el grupo «'.$g.'» a «'.$n.'»');
        $this->ventana = null;
        $this->dispatch('toast', texto: 'Grupo renombrado');
    }

    // ------------------------------------------------------------ datos

    public function importar(): void
    {
        $this->exigir('datos');
        $this->error = '';
        if (! $this->copia) {
            $this->error = 'Elige el archivo de la copia.';

            return;
        }
        if (! $this->requiere('datos', 'Importar la copia de seguridad', 'importar', [], ['forzar' => true, 'soloAdmin' => true, 'incluirme' => true,
            'titulo' => 'Confirma con el PIN de un administrador', 'motivo' => 'Se agregan y actualizan ventas, clientes, productos y usuarios con los de la copia. Solo se puede si todavía no se usó el sistema nuevo.'])) {
            return;
        }
        @set_time_limit(600);
        try {
            $d = json_decode(file_get_contents($this->copia->getRealPath()), true);
            $imp = app(ImportadorCopia::class);
            $this->log = [];
            $res = $imp->importar($d, $this->neg(), function ($t) {
                $this->log[] = $t;
            });
        } catch (ErrorNegocio $e) {
            $this->error = $e->getMessage();

            return;
        } catch (\Throwable $e) {
            report($e);
            $this->error = ImportadorCopia::ERROR_INESPERADO;

            return;
        }
        $this->log[] = 'Listo: '.collect($res)->except('negocio')->map(fn ($n, $k) => $n.' '.$k)->join(', ').'.';
        Bitacora::registrar('datos', 'Importó la copia del '.substr($d['creado'] ?? '', 0, 10).' (autorizó '.($this->autorizo?->nombre ?? Auth::user()->nombre).')');
        $this->copia = null;
        $this->dispatch('toast', texto: 'Copia importada');
    }

    public function guardarEquipo(): void
    {
        Cookie::queue(Cookie::forever('equipo', mb_substr(trim($this->equipo), 0, 30)));
        $this->dispatch('toast', texto: 'Nombre del equipo guardado');
    }

    public function updatedEquipoAncho(): void
    {
        if (in_array($this->equipoAncho, ['80', '58'], true)) {
            Cookie::queue(Cookie::forever('ticket_ancho', $this->equipoAncho));
        } else {
            Cookie::queue(Cookie::forget('ticket_ancho'));
        }
        $this->dispatch('toast', texto: 'Guardado para este equipo');
    }

    public function render(Stock $stock)
    {
        $neg = $this->neg()->load('modulos');
        $this->updatedPestana();
        $datos = ['neg' => $neg, 'tabs' => $this->pestanas()];
        if ($this->pestana === 'productos') {
            $q = Texto::norm($this->buscar);
            $datos['productos'] = Producto::with(['opciones', 'stockBase'])->orderBy('orden')->get()
                ->filter(fn ($p) => $q === '' || str_contains(Texto::norm($p->nombre.' '.$p->grupo.' '.$p->codigos_barra), $q))->groupBy('grupo');
            $datos['stock'] = $stock->todos();
            $datos['grupos'] = Producto::orderBy('orden')->pluck('grupo')->unique()->values();
            $datos['vistaMasivo'] = $this->ventana === 'masivo' ? $this->vistaMasivo() : [];
        }
        if ($this->pestana === 'ticket') {
            $demo = new Venta(['numero' => 'A1-001', 'total' => 400, 'metodo' => 'efectivo', 'vendedor' => Auth::user()->nombre, 'pago' => 400, 'descuento' => 0, 'abono' => 0]);
            $demo->fecha = today();
            $demo->vendida_at = now();
            $demo->setRelation('items', collect([new VentaItem(['cantidad' => 10, 'nombre' => 'Copia B/N A4', 'precio' => 15, 'subtotal' => 150]),
                new VentaItem(['cantidad' => 1, 'nombre' => 'Anillado', 'precio' => 250, 'subtotal' => 250])]));
            $demo->setRelation('cliente', null);
            $datos['demo'] = $demo;
        }
        if ($this->pestana === 'fact') {
            $cmp = app(Comprobantes::class);
            $cfg = $cmp->config();
            $datos['cfg'] = $cfg;
            $datos['ultB'] = $cmp->ultimoNumero($cfg['serieB']);
            $datos['ultF'] = $cmp->ultimoNumero($cfg['serieF']);
        }

        return view('livewire.ajustes', $datos);
    }
}
