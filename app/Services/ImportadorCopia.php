<?php

namespace App\Services;

use App\Models\Actividad;
use App\Models\CajaMovimiento;
use App\Models\Cliente;
use App\Models\ClienteMovimiento;
use App\Models\Compra;
use App\Models\Comprobante;
use App\Models\Dia;
use App\Models\Documento;
use App\Models\LecturaContador;
use App\Models\Maquina;
use App\Models\ModeloDocumento;
use App\Models\ModuloNegocio;
use App\Models\Negocio;
use App\Models\Pedido;
use App\Models\PlantillaUtiles;
use App\Models\Producto;
use App\Models\ProductoInsumo;
use App\Models\Proveedor;
use App\Models\Rol;
use App\Models\StockBase;
use App\Models\StockMovimiento;
use App\Models\TurnoCaja;
use App\Models\Usuario;
use App\Models\Venta;
use App\Models\VentaAnulada;
use App\Support\Catalogos;
use App\Support\NegocioActual;
use App\Support\Texto;
use Carbon\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Lee la copia de seguridad v3 de caja-rapida.html (Configuración › Datos › Descargar copia)
 * y la guarda en las tablas. Se puede correr varias veces: lo que ya está (mismo id) se actualiza,
 * no se duplica. Nada se borra del servidor.
 */
class ImportadorCopia
{
    /** @var array<string, int> */
    private array $productos = [];

    private array $usuarios = [];

    private array $clientes = [];

    private array $proveedores = [];

    private array $cuenta = [];

    public const ERROR_INESPERADO = 'No se pudo importar la copia: el archivo tiene datos que el sistema no entiende. No se guardó nada. '
        .'Descarga una copia nueva del sistema anterior y vuelve a intentarlo; si sigue fallando, avisa a soporte.';

    /** @var callable|null */
    private $log = null;

    public function leerArchivo(string $ruta): array
    {
        $d = json_decode(file_get_contents($ruta), true);
        $this->validar($d);

        return $d;
    }

    public function validar(mixed $d): void
    {
        if (! is_array($d) || ($d['app'] ?? null) !== 'ojitos-caja') {
            throw new ErrorNegocio('Ese archivo no es una copia de Ojitos Caja.');
        }
        if ((int) ($d['v'] ?? 0) < 3) {
            throw new ErrorNegocio('La copia es de una versión antigua (v'.($d['v'] ?? '?').'). Descarga una copia nueva desde el sistema actual.');
        }
    }

    /**
     * @param  Negocio|null  $neg  null = crear un negocio nuevo con los datos de la copia
     * @return array resumen: cuántos registros de cada cosa
     */
    public function importar(array $d, ?Negocio $neg = null, ?callable $log = null, ?string $slug = null, bool $forzar = false): array
    {
        $this->validar($d);
        $this->log = $log;
        AltaNegocio::asegurarPermisos();
        $actual = app(NegocioActual::class);
        $antes = $actual->get();

        try {
            if ($neg && ! $forzar && ($que = $this->usadoDespues($neg))) {
                throw new ErrorNegocio('Este negocio ya tiene '.$que.' hechos en el sistema nuevo. Volver a importar la copia los mezclaría con los del sistema '
                    .'anterior y podría deshacer anulaciones, pagos o comprobantes. Para pasar los datos del día, empieza con una base nueva y trae tu copia '
                    .'desde «Bienvenido» (ver README › Pasar los datos del día).');
            }
            // todo o nada: si algo falla a la mitad, no queda un negocio a medias (por ejemplo, sin usuarios)
            $neg = DB::transaction(function () use ($d, $neg, $slug, $actual) {
                $neg = $this->negocio($d['negocio'] ?? [], $neg, $slug);
                $actual->set($neg);
                $this->decir('Negocio: '.$neg->nombre);
                $this->rolesYUsuarios($d['usuarios'] ?? []);
                $this->catalogo($d['catalog'] ?? []);
                $this->stock($d['stockBase'] ?? [], $d['stockLog'] ?? [], $d['stock'] ?? []);
                $this->maquinas($d['maquinas'] ?? []);
                $this->clientes($d['clientes'] ?? []);
                $dias = $d['dias'] ?? [];
                ksort($dias);
                foreach ($dias as $k => $dia) {
                    $this->dia((string) $k, (array) $dia);
                }
                $this->decir(count($dias).' días de ventas');
                $this->pedidos($d['pedidos'] ?? [], $d['encargos'] ?? []);
                $this->plantillas($d['plantillas'] ?? []);
                $this->compras($d['compras'] ?? []);
                $this->documentos($d['documentos'] ?? [], $d['modelos'] ?? []);
                $neg->refresh();
                $neg->fijarAjuste('importado_at', now()->toIso8601String());
                $neg->save();

                return $neg;
            });
        } finally {
            $actual->set($antes ?? $neg);
        }

        return ['negocio' => $neg] + $this->cuenta;
    }

    /**
     * Qué se hizo en el sistema nuevo después de la última importación (null = nada).
     * Sin fecha de importación (negocio creado de cero, o importado antes de guardarla) cuenta todo.
     */
    public function usadoDespues(Negocio $neg): ?string
    {
        $desde = $neg->ajuste('importado_at');
        $actual = app(NegocioActual::class);
        $antes = $actual->get();
        $actual->set($neg);
        try {
            $tablas = ['ventas' => Venta::class, 'ventas anuladas' => VentaAnulada::class, 'comprobantes' => Comprobante::class,
                'movimientos de caja' => CajaMovimiento::class, 'turnos de caja' => TurnoCaja::class, 'fiados y pagos' => ClienteMovimiento::class,
                'pedidos' => Pedido::class, 'compras' => Compra::class, 'movimientos de stock' => StockMovimiento::class, 'documentos' => Documento::class];
            foreach ($tablas as $nombre => $modelo) {
                if ($modelo::query()->when($desde, fn ($q) => $q->where('updated_at', '>', Carbon::parse($desde)))->exists()) {
                    return $nombre;
                }
            }

            return null;
        } finally {
            $actual->set($antes);
        }
    }

    private function decir(string $t): void
    {
        if ($this->log) {
            ($this->log)($t);
        }
    }

    private function contar(string $que, int $n = 1): void
    {
        $this->cuenta[$que] = ($this->cuenta[$que] ?? 0) + $n;
    }

    /** milisegundos del sistema anterior → fecha y hora de Lima */
    private function ms(mixed $t): ?Carbon
    {
        if (! is_numeric($t) || $t <= 0) {
            return null;
        }

        return Carbon::createFromTimestampMs((int) $t)->setTimezone(config('app.timezone'));
    }

    /** listas guardadas como mapas {id: objeto} o como arreglos → arreglo de objetos */
    private function lista(mixed $m): array
    {
        if (! is_array($m)) {
            return [];
        }
        $out = array_values(array_filter($m, 'is_array'));
        usort($out, fn ($a, $b) => ($a['t'] ?? $a['creado'] ?? 0) <=> ($b['t'] ?? $b['creado'] ?? 0));

        return $out;
    }

    private function usuarioId(?string $uid): ?int
    {
        return $uid ? ($this->usuarios[$uid] ?? null) : null;
    }

    private function fecha(?string $k): ?string
    {
        return $k && preg_match('/^\d{4}-\d{2}-\d{2}$/', $k) ? $k : null;
    }

    // ---------------------------------------------------------------- negocio

    private function negocio(array $n, ?Negocio $neg, ?string $slug): Negocio
    {
        $campos = ['nombre' => 'nombre', 'giro' => 'giro', 'titular' => 'titular', 'ruc' => 'ruc', 'dir' => 'direccion', 'cel' => 'celular', 'ciudad' => 'ciudad', 'rubro' => 'rubro'];
        $fuera = array_merge(array_keys($campos), ['qrLogo', 'qrYape', 'qrPlin', 'ultCopia', 'modsOff']);
        $datos = [];
        foreach ($campos as $de => $a) {
            if (isset($n[$de]) && $n[$de] !== '') {
                $datos[$a] = $n[$de];
            }
        }
        $datos['nombre'] ??= 'Mi negocio';
        $datos['logo'] = $n['qrLogo'] ?? null;
        $datos['qr_yape'] = $n['qrYape'] ?? null;
        $datos['qr_plin'] = $n['qrPlin'] ?? null;
        $datos['ultima_copia_at'] = $this->ms($n['ultCopia'] ?? null);
        $ajustes = Arr::except($n, $fuera);

        if ($neg) {
            $neg->fill($datos);
            $neg->ajustes = array_merge($neg->ajustes ?? [], $ajustes);
            $neg->save();
        } else {
            $neg = Negocio::create($datos + [
                'slug' => $slug ?: app(AltaNegocio::class)->slugLibre($datos['nombre']),
                'ajustes' => $ajustes,
            ]);
        }
        $off = is_array($n['modsOff'] ?? null) ? $n['modsOff'] : (Catalogos::RUBROS[$neg->rubro][1] ?? []);
        foreach (Catalogos::MODULOS_OPCIONALES as $m) {
            ModuloNegocio::updateOrCreate(['negocio_id' => $neg->id, 'modulo' => $m], ['activo' => ! in_array($m, $off, true)]);
        }

        return $neg;
    }

    // ---------------------------------------------------------------- usuarios

    private function rolesYUsuarios(array $u): void
    {
        $roles = $this->lista($u['roles'] ?? []) ?: [
            ['id' => 'admin', 'nombre' => 'Administrador', 'fijo' => true, 'perms' => []],
            ['id' => 'vendedor', 'nombre' => 'Vendedor', 'perms' => Catalogos::PERMISOS_VENDEDOR],
        ];
        $conocidos = array_keys(Catalogos::permisos());
        $mapa = [];
        foreach ($roles as $i => $r) {
            if (empty($r['id'])) {
                continue;
            }
            $admin = $r['id'] === 'admin' || ! empty($r['fijo']);
            $rol = Rol::updateOrCreate(['uid' => $r['id']], [
                'nombre' => $r['nombre'] ?? 'Rol', 'es_admin' => $admin, 'orden' => $r['o'] ?? $i,
                'desc_max' => $r['descMax'] ?? null, 'desc_pct' => $r['descPct'] ?? null,
            ]);
            $rol->sincronizarPermisos($admin ? $conocidos : array_values(array_intersect($r['perms'] ?? [], $conocidos)));
            $mapa[$r['id']] = $rol->id;
            $this->contar('roles');
        }
        if (! isset($mapa['vendedor'])) {
            app(AltaNegocio::class)->rolesIniciales(app(NegocioActual::class)->obligatorio());
            $mapa['vendedor'] = Rol::where('uid', 'vendedor')->value('id');
            $mapa['admin'] ??= Rol::where('uid', 'admin')->value('id');
        }

        $vistos = [];
        foreach ($this->lista($u['items'] ?? []) as $i => $x) {
            if (empty($x['id'])) {
                continue;
            }
            $usuario = Texto::usuario($x['usuario'] ?? '') ?: Texto::usuario(Texto::primerNombre($x['nombre'] ?? ''));
            $base = $usuario ?: 'usuario';
            $n = 1;
            while (in_array($usuario, $vistos, true) || Usuario::where('usuario', $usuario)->where('uid', '!=', $x['id'])->exists()) {
                $usuario = $base.(++$n);
            }
            $vistos[] = $usuario;
            $existe = Usuario::where('uid', $x['id'])->first();
            $datos = [
                'nombre' => $x['nombre'] ?? $usuario, 'usuario' => $usuario,
                'rol_id' => $mapa[$x['rol'] ?? 'vendedor'] ?? $mapa['vendedor'],
                'activo' => empty($x['inactivo']), 'orden' => $x['o'] ?? $i,
                'ultimo_ingreso_at' => $this->ms($x['ultimo'] ?? null),
            ];
            // el PIN del sistema anterior solo se toma si en el servidor todavía no lo cambió
            if (! $existe || ! $existe->pin) {
                $datos += ['pin_legado' => $x['pin'] ?? null, 'pin_sal' => $x['salt'] ?? null, 'pin_largo' => $x['len'] ?? null];
            }
            $usr = Usuario::updateOrCreate(['uid' => $x['id']], $datos);
            $this->usuarios[$x['id']] = $usr->id;
            $this->contar('usuarios');
        }
        $this->decir(($this->cuenta['usuarios'] ?? 0).' usuarios y '.($this->cuenta['roles'] ?? 0).' roles');
    }

    // ---------------------------------------------------------------- catálogo y stock

    private function catalogo(array $items): void
    {
        $conocidos = ['id', 'g', 'n', 'c', 'quick', 'free', 'desc', 'tasa', 'fav', 'oculto', 'costo', 'min', 'um', 'cb', 'ops', 'ins', 'o'];
        foreach (array_values($items) as $i => $it) {
            if (! is_array($it) || empty($it['id'])) {
                continue;
            }
            $p = Producto::withTrashed()->updateOrCreate(['uid' => $it['id']], [
                'rol' => Redaccion::ROLES_LEGADO[$it['id']] ?? null, 'grupo' => $it['g'] ?? 'Otros', 'nombre' => $it['n'] ?? $it['id'], 'color' => $it['c'] ?? 't-otro',
                'rapido' => ! empty($it['quick']), 'monto_libre' => ! empty($it['free']), 'pide_detalle' => ! empty($it['desc']),
                'es_tasa' => ! empty($it['tasa']), 'favorito' => ! empty($it['fav']), 'oculto' => ! empty($it['oculto']),
                'costo' => isset($it['costo']) && $it['costo'] !== '' ? round((float) $it['costo'], 4) : null,
                'stock_minimo' => $it['min'] ?? null, 'unidad' => $it['um'] ?? null, 'codigos_barra' => $it['cb'] ?? null,
                'orden' => $i, 'extra' => Arr::except($it, $conocidos) ?: null, 'deleted_at' => null,
            ]);
            $p->opciones()->delete();
            foreach (array_values($it['ops'] ?? []) as $j => $o) {
                $p->opciones()->create(['etiqueta' => (string) ($o['l'] ?? ''), 'precio' => (int) round($o['p'] ?? 0), 'orden' => $j]);
            }
            $this->productos[$it['id']] = $p->id;
            $this->contar('productos');
        }
        // insumos (hojas por copia, tóner…): cuando ya existen todos los productos
        foreach ($items as $it) {
            if (! is_array($it) || empty($it['id']) || ! isset($this->productos[$it['id']])) {
                continue;
            }
            ProductoInsumo::where('producto_id', $this->productos[$it['id']])->delete();
            foreach ($it['ins'] ?? [] as $x) {
                if (isset($this->productos[$x['id'] ?? ''])) {
                    ProductoInsumo::create(['producto_id' => $this->productos[$it['id']], 'insumo_id' => $this->productos[$x['id']], 'cantidad' => $x['q'] ?? 1]);
                }
            }
        }
        $this->decir(($this->cuenta['productos'] ?? 0).' productos y servicios');
    }

    private function productoId(?string $uid): ?int
    {
        if (! $uid) {
            return null;
        }
        if (! array_key_exists($uid, $this->productos)) {
            $this->productos[$uid] = Producto::withTrashed()->where('uid', $uid)->value('id');
        }

        return $this->productos[$uid];
    }

    private function stock(array $base, array $log, array $contados): void
    {
        if (! $base && $contados) {   // formato más antiguo: solo cantidades
            foreach ($contados as $id => $n) {
                $base[$id] = ['n' => $n, 't' => now()->getTimestampMs()];
            }
        }
        foreach ($base as $uid => $b) {
            if (! ($pid = $this->productoId((string) $uid)) || ! is_array($b)) {
                continue;
            }
            StockBase::updateOrCreate(['producto_id' => $pid], ['cantidad' => $b['n'] ?? 0, 'desde' => $this->ms($b['t'] ?? null) ?? now()]);
            $this->contar('stock');
        }
        foreach ($this->lista($log) as $l) {
            $uid = $l['k'] ?? (($l['t'] ?? '').'_'.($l['id'] ?? ''));
            StockMovimiento::updateOrCreate(['uid' => Str::limit($uid, 60, '')], [
                'producto_id' => $this->productoId($l['id'] ?? null), 'producto_uid' => $l['id'] ?? null, 'nombre' => $l['nombre'] ?? null,
                'tipo' => $l['tipo'] ?? 'entrada', 'cantidad' => $l['cant'] ?? 0, 'diferencia' => $l['dif'] ?? null,
                'costo' => isset($l['costo']) ? round((float) $l['costo'], 4) : null, 'motivo' => $l['motivo'] ?? null, 'nota' => Str::limit($l['nota'] ?? '', 250, '') ?: null,
                'compra_uid' => $l['compra'] ?? null, 'pedido_uid' => $l['ped'] ?? null,
                'usuario_id' => $this->usuarioId($l['u'] ?? null), 'vendedor' => $l['vend'] ?? null,
                'ocurrido_at' => $this->ms($l['t'] ?? null) ?? now(),
                'extra' => Arr::except($l, ['k', 'id', 'nombre', 'tipo', 'cant', 'dif', 'costo', 'motivo', 'nota', 'compra', 'ped', 'u', 'vend', 't']) ?: null,
            ]);
            $this->contar('movimientos de stock');
        }
    }

    private function maquinas(array $m): void
    {
        foreach (array_values($m['items'] ?? []) as $i => $x) {
            if (empty($x['id'])) {
                continue;
            }
            Maquina::updateOrCreate(['uid' => $x['id']], ['nombre' => $x['nombre'] ?? 'Máquina', 'contadores' => $x['conts'] ?? [], 'orden' => $i]);
            $this->contar('máquinas');
        }
        if (! empty($m['prods']) || ! empty($m['ult'])) {
            $neg = app(NegocioActual::class)->obligatorio();
            $neg->fijarAjuste('maquinas', ['prods' => $m['prods'] ?? [], 'ult' => $m['ult'] ?? []]);
            $neg->save();
        }
    }

    // ---------------------------------------------------------------- clientes

    private function clientes(array $c): void
    {
        foreach ($this->lista($c['clientes'] ?? []) as $x) {
            if (empty($x['id'])) {
                continue;
            }
            $cl = Cliente::updateOrCreate(['uid' => $x['id']], [
                'nombre' => $x['nombre'] ?? 'Cliente', 'celular' => Texto::cel9($x['cel'] ?? '') ?: null, 'documento' => ($x['dni'] ?? '') ?: null,
                'direccion' => ($x['dir'] ?? $x['dom'] ?? '') ?: null, 'nota' => ($x['nota'] ?? '') ?: null,
                'visitas' => (int) ($x['visitas'] ?? 0), 'gastado' => (int) ($x['gastado'] ?? 0),
                'ultima_visita_at' => $this->ms($x['ultima'] ?? null), 'alias' => $x['alias'] ?? null,
                'extra' => Arr::except($x, ['id', 'nombre', 'cel', 'dni', 'dir', 'dom', 'nota', 'visitas', 'gastado', 'ultima', 'alias', 'creado']) ?: null,
                'created_at' => $this->ms($x['creado'] ?? null) ?? now(),
            ]);
            $this->clientes[$x['id']] = $cl->id;
            foreach ($x['alias'] ?? [] as $a) {
                $this->clientes[$a] = $cl->id;
            }
            $this->contar('clientes');
        }
        foreach ($this->lista($c['movs'] ?? []) as $m) {
            $cid = $this->clienteId($m['cli'] ?? null);
            if (empty($m['id']) || ! $cid) {
                continue;
            }
            ClienteMovimiento::updateOrCreate(['uid' => $m['id']], [
                'cliente_id' => $cid, 'tipo' => $m['tipo'] ?? 'fiado', 'monto' => (int) ($m['monto'] ?? 0), 'metodo' => $m['metodo'] ?? null,
                'detalle' => Str::limit($m['detalle'] ?? '', 250, '') ?: null, 'venta_uid' => $m['venta'] ?? null, 'fecha' => $this->fecha($m['dia'] ?? null),
                'usuario_id' => $this->usuarioId($m['u'] ?? null), 'vendedor' => $m['vend'] ?? null, 'ocurrido_at' => $this->ms($m['t'] ?? null) ?? now(),
                'extra' => Arr::except($m, ['id', 'cli', 'tipo', 'monto', 'metodo', 'detalle', 'venta', 'dia', 'u', 'vend', 't']) ?: null,
            ]);
            $this->contar('fiados y pagos');
        }
        $this->decir(($this->cuenta['clientes'] ?? 0).' clientes');
    }

    private function clienteId(?string $uid): ?int
    {
        if (! $uid) {
            return null;
        }
        if (! array_key_exists($uid, $this->clientes)) {
            $this->clientes[$uid] = Cliente::where('uid', $uid)->value('id');
        }

        return $this->clientes[$uid];
    }

    // ---------------------------------------------------------------- días

    private function dia(string $k, array $D): void
    {
        if (! $this->fecha($k)) {
            return;
        }
        $cj = (array) ($D['caja'] ?? []);
        Dia::updateOrCreate(['fecha' => $k], [
            'cierre' => ! empty($D['cierre']), 'cierre_comprobante_uid' => $D['cierreCpe'] ?? null,
            'caja_inicial' => isset($cj['inicial']) ? (int) $cj['inicial'] : null, 'caja_arqueo' => $cj['arqueo'] ?? null,
        ]);

        foreach ($this->lista($D['ventas'] ?? []) as $v) {
            if (empty($v['id'])) {
                continue;
            }
            $venta = Venta::conAnuladas()->updateOrCreate(['uid' => $v['id']], [
                'fecha' => $k, 'numero' => $v['num'] ?? null, 'usuario_id' => $this->usuarioId($v['u'] ?? null), 'vendedor' => $v['vend'] ?? null,
                'cliente_id' => $this->clienteId($v['cliente'] ?? null), 'total' => (int) ($v['total'] ?? 0), 'descuento' => (int) ($v['descuento'] ?? 0),
                'metodo' => $v['metodo'] ?? 'efectivo', 'pago' => isset($v['pago']) ? (int) $v['pago'] : null, 'abono' => (int) ($v['abono'] ?? 0),
                'boleta' => ! empty($v['boleta']), 'comprobante_uid' => $v['cpe'] ?? null, 'comprobante_fecha' => $this->fecha($v['cpeK'] ?? null),
                'comprobante_numero' => ($v['cpeNum'] ?? '') ?: null, 'comprobante_pedido' => $v['cpeReq'] ?? null, 'al_cierre' => ! empty($v['alCierre']),
                'origen_tipo' => ! empty($v['encargo']) ? 'pedido' : null, 'origen_uid' => ($v['encargo'] ?? '') ?: null, 'vendida_at' => $this->ms($v['t'] ?? null) ?? Carbon::parse($k.' 12:00'),
                'extra' => Arr::except($v, ['id', 't', 'items', 'total', 'boleta', 'metodo', 'pago', 'descuento', 'u', 'vend', 'num', 'cpeReq', 'cliente', 'abono', 'encargo', 'cpe', 'cpeK', 'cpeNum', 'alCierre']) ?: null,
            ]);
            $venta->items()->delete();
            foreach (array_values($v['items'] ?? []) as $i => $l) {
                $venta->items()->create([
                    'producto_id' => $this->productoId($l['sid'] ?? null), 'producto_uid' => $l['sid'] ?? null,
                    'origen_tipo' => ! empty($l['doc']) ? 'documento' : null, 'origen_uid' => ($l['doc'] ?? '') ?: null,
                    'nombre' => $l['nombre'] ?? '?', 'detalle' => ($l['det'] ?? '') ?: null, 'cantidad' => $l['cant'] ?? 1,
                    'precio' => (int) ($l['precio'] ?? 0), 'precio_lista' => isset($l['lista']) ? (int) $l['lista'] : null,
                    'subtotal' => (int) ($l['sub'] ?? 0), 'costo' => isset($l['costo']) ? round((float) $l['costo'], 4) : null,
                    'tercero' => ! empty($l['tercero']), 'orden' => $i,
                ]);
            }
            $this->contar('ventas');
        }

        foreach ($this->lista($D['cpes'] ?? []) as $c) {
            if (empty($c['id'])) {
                continue;
            }
            $tipo = $c['tipo'] ?? '03';
            $serie = strtoupper(Str::limit($c['serie'] ?? 'EB01', 4, ''));
            $num = ($c['num'] ?? '') !== '' ? (string) $c['num'] : null;
            $num = $num !== null && ctype_digit($num) ? (string) (int) $num : $num;
            if ($num !== null && ($c['estado'] ?? 'emitido') !== 'anulado' && Comprobante::where('uid', '!=', $c['id'])->where('serie', $serie)
                ->where('numero', $num)->where('tipo', $tipo === '07' ? '=' : '!=', '07')->where('estado', '!=', 'anulado')->exists()) {
                // número repetido en la copia: no se pierde (queda en «extra») y se corrige en Facturación › Emitidos
                $c['numeroRepetido'] = $num;
                $this->decir('Comprobante '.$serie.'-'.$num.' repetido: quedó sin número para corregirlo en Facturación.');
                $num = null;
            }
            Comprobante::updateOrCreate(['uid' => $c['id']], [
                'fecha' => $k, 'tipo' => $tipo, 'serie' => $serie, 'numero' => $num,
                'cliente' => $c['cliente'] ?? null, 'total' => (int) ($c['total'] ?? 0), 'gravado' => (int) ($c['grav'] ?? 0), 'exonerado' => (int) ($c['exo'] ?? 0),
                'inafecto' => (int) ($c['ina'] ?? 0), 'igv' => (int) ($c['igv'] ?? 0), 'descripcion' => Str::limit($c['desc'] ?? '', 250, '') ?: null,
                'estado' => $c['estado'] ?? 'emitido', 'modo' => $c['modo'] ?? 'manual', 'referencia' => $c['ref'] ?? null, 'motivo' => $c['motivo'] ?? null,
                'cierre_de' => $this->fecha($c['cierreDe'] ?? null), 'ventas' => $c['ventas'] ?? [], 'anulado_at' => $this->ms($c['anT'] ?? null), 'anulado_por' => $c['anPor'] ?? null,
                'usuario_id' => $this->usuarioId($c['u'] ?? null), 'vendedor' => $c['vend'] ?? null, 'emitido_at' => $this->ms($c['t'] ?? null) ?? Carbon::parse($k.' 12:00'),
                'extra' => Arr::except($c, ['id', 't', 'tipo', 'serie', 'num', 'cliente', 'total', 'grav', 'exo', 'ina', 'igv', 'desc', 'estado', 'modo', 'ref', 'motivo', 'cierreDe', 'ventas', 'anT', 'anPor', 'u', 'vend']) ?: null,
            ]);
            $this->contar('comprobantes');
        }

        foreach ($this->lista($D['anuladas'] ?? []) as $a) {
            if (empty($a['id'])) {
                continue;
            }
            VentaAnulada::updateOrCreate(['uid' => $a['id']], [
                'fecha' => $k, 'venta_uid' => $a['venta'] ?? null, 'numero' => ($a['num'] ?? '') ?: null, 'venta_at' => $this->ms($a['vt'] ?? null),
                'total' => (int) ($a['total'] ?? 0), 'detalle' => Str::limit($a['detalle'] ?? '', 250, '') ?: null, 'motivo' => ($a['motivo'] ?? '') ?: null,
                'tipo' => $a['tipo'] ?? 'anulada', 'usuario_id' => $this->usuarioId($a['u'] ?? null), 'por' => $a['por'] ?? null,
                'vendedor_original' => $a['vendOrig'] ?? null, 'anulada_at' => $this->ms($a['t'] ?? null) ?? Carbon::parse($k.' 12:00'),
            ]);
            $this->contar('ventas anuladas');
        }

        foreach ($this->lista($D['act'] ?? []) as $a) {
            if (empty($a['id'])) {
                continue;
            }
            Actividad::updateOrCreate(['uid' => $a['id']], [
                'fecha' => $k, 'tipo' => Str::limit($a['tipo'] ?? 'otro', 20, ''), 'detalle' => Str::limit($a['det'] ?? '', 237),
                'usuario_id' => $this->usuarioId($a['u'] ?? null), 'vendedor' => $a['vend'] ?? null, 'equipo' => ($a['eq'] ?? '') ?: null,
                'ocurrido_at' => $this->ms($a['t'] ?? null) ?? Carbon::parse($k.' 12:00'),
            ]);
            $this->contar('actividad');
        }

        foreach ($this->lista($cj['movs'] ?? []) as $m) {
            if (empty($m['id'])) {
                continue;
            }
            CajaMovimiento::updateOrCreate(['uid' => $m['id']], [
                'fecha' => $k, 'tipo' => $m['tipo'] ?? 'gasto', 'concepto' => $m['concepto'] ?? 'Otro', 'nota' => ($m['nota'] ?? '') ?: null,
                'monto' => (int) ($m['monto'] ?? 0), 'metodo' => $m['metodo'] ?? 'efectivo', 'referencia' => $m['ref'] ?? null,
                'usuario_id' => $this->usuarioId($m['u'] ?? null), 'vendedor' => $m['vend'] ?? null, 'ocurrido_at' => $this->ms($m['t'] ?? null) ?? Carbon::parse($k.' 12:00'),
                'extra' => Arr::except($m, ['id', 't', 'tipo', 'concepto', 'nota', 'monto', 'metodo', 'ref', 'u', 'vend']) ?: null,
            ]);
            $this->contar('movimientos de caja');
        }

        foreach ($this->lista($cj['turnos'] ?? []) as $t) {
            if (empty($t['id'])) {
                continue;
            }
            TurnoCaja::updateOrCreate(['uid' => $t['id']], [
                'fecha' => $k, 'usuario_id' => $this->usuarioId($t['u'] ?? null), 'vendedor' => $t['vend'] ?? null,
                'abre_at' => $this->ms($t['abre'] ?? $t['t'] ?? null) ?? Carbon::parse($k.' 08:00'), 'cierra_at' => $this->ms($t['cierra'] ?? null),
                'inicial' => (int) ($t['inicial'] ?? 0), 'contado' => isset($t['contado']) ? (int) $t['contado'] : null,
                'esperado' => isset($t['esperado']) ? (int) $t['esperado'] : null, 'cerro_por' => $t['cerroPor'] ?? null, 'correcciones' => $t['iniLog'] ?? null,
            ]);
            $this->contar('turnos de caja');
        }

        foreach ($this->lista($D['lecturas'] ?? []) as $l) {
            if (empty($l['id'])) {
                continue;
            }
            LecturaContador::updateOrCreate(['uid' => $l['id']], [
                'fecha' => $k, 'tipo' => $l['tipo'] ?? 'fin', 'maquina_uid' => $l['maq'] ?? null, 'contador_uid' => $l['cont'] ?? null, 'grupo' => $l['g'] ?? null,
                'valor' => (int) ($l['v'] ?? 0), 'desde' => isset($l['desde']) ? (int) $l['desde'] : null,
                'usuario_id' => $this->usuarioId($l['u'] ?? null), 'vendedor' => $l['vend'] ?? null, 'ocurrido_at' => $this->ms($l['t'] ?? null) ?? Carbon::parse($k.' 20:00'),
            ]);
            $this->contar('lecturas de contadores');
        }
    }

    // ---------------------------------------------------------------- pedidos, compras, redacción

    private function pedidos(array $pedidos, array $encargos): void
    {
        if (! $pedidos && $encargos) {
            $this->decir('Aviso: la copia trae encargos del formato antiguo sin pasar a Pedidos. Ábrela una vez en el sistema actual y descarga una copia nueva para traerlos.');
        }
        foreach ($this->lista($pedidos) as $p) {
            if (empty($p['id'])) {
                continue;
            }
            $cli = (array) ($p['cliente'] ?? []);
            $ped = Pedido::updateOrCreate(['uid' => $p['id']], [
                'numero' => $p['num'] ?? null, 'etapa' => $p['etapa'] ?? 'cotizado', 'cliente_id' => $this->clienteId($p['cliId'] ?? null), 'cliente' => $cli ?: null,
                'detalle' => ($p['detalle'] ?? '') ?: null, 'total' => (int) ($p['total'] ?? $p['monto'] ?? 0), 'descuento' => (int) ($p['desc'] ?? 0),
                'fecha' => $this->fecha($p['fecha'] ?? null) ?? ($this->ms($p['t'] ?? null) ?? now())->toDateString(),
                'fecha_entrega' => $this->fecha($p['fEntrega'] ?? null), 'hora_entrega' => ($p['hora'] ?? '') ?: null,
                'entregado_at' => $this->ms($p['entregadoEn'] ?? null), 'usuario_id' => $this->usuarioId($p['u'] ?? null), 'vendedor' => $p['vend'] ?? null,
                'creado_at' => $this->ms($p['t'] ?? null) ?? now(), 'datos' => Arr::except($p, ['items', 'pagos', 'hist']),
                'validez' => max(1, (int) ($p['validez'] ?? 7)), 'cond_entrega' => ($p['cEntrega'] ?? '') ?: null, 'cond_pago' => ($p['cPago'] ?? '') ?: null,
                'notas' => ($p['notas'] ?? '') ?: null, 'responsable' => ($p['resp'] ?? '') ?: null, 'cotizada' => ! empty($p['cotizada']),
                'directa' => ! empty($p['directa']), 'aceptado_at' => $this->ms($p['aceptadoEn'] ?? null), 'listo_at' => $this->ms($p['listoEn'] ?? null),
                'cerrado_at' => $this->ms($p['cerradoEn'] ?? null),
            ]);
            $ped->historial()->delete();
            foreach ($p['hist'] ?? [] as $h) {
                $ped->historial()->create(['que' => mb_substr((string) ($h['que'] ?? ''), 0, 250), 'vendedor' => $h['vend'] ?? null, 'ocurrido_at' => $this->ms($h['t'] ?? null) ?? now()]);
            }
            $ped->items()->delete();
            foreach (array_values($p['items'] ?? []) as $i => $l) {
                $ped->items()->create([
                    'producto_id' => $this->productoId($l['sid'] ?? null), 'producto_uid' => $l['sid'] ?? null, 'nombre' => $l['nombre'] ?? '?',
                    'detalle' => ($l['det'] ?? '') ?: null, 'cantidad' => $l['cant'] ?? 1, 'precio' => (int) ($l['precio'] ?? 0),
                    'subtotal' => (int) ($l['sub'] ?? round(($l['cant'] ?? 1) * ($l['precio'] ?? 0))), 'orden' => $i,
                ]);
            }
            $ped->pagos()->delete();
            foreach (array_values($p['pagos'] ?? []) as $i => $g) {
                $ped->pagos()->create([
                    'uid' => $g['id'] ?? ($p['id'].'-'.$i), 'monto' => (int) ($g['monto'] ?? 0), 'metodo' => ($g['metodo'] ?? '') ?: null, 'tipo' => $g['tipo'] ?? null,
                    'venta_uid' => $g['venta'] ?? null, 'fecha' => $this->fecha($g['k'] ?? null), 'vendedor' => $g['vend'] ?? null,
                    'pagado_at' => $this->ms($g['t'] ?? null) ?? now(),
                ]);
            }
            $this->contar('pedidos');
        }
    }

    private function plantillas(array $pl): void
    {
        foreach ($this->lista($pl) as $x) {
            if (empty($x['id'])) {
                continue;
            }
            PlantillaUtiles::updateOrCreate(['uid' => $x['id']], ['nombre' => $x['nombre'] ?? 'Lista', 'institucion' => ($x['inst'] ?? '') ?: null, 'items' => $x['items'] ?? []]);
            $this->contar('listas de útiles');
        }
    }

    private function compras(array $c): void
    {
        foreach ($this->lista($c['proveedores'] ?? []) as $p) {
            if (empty($p['id'])) {
                continue;
            }
            $prov = Proveedor::updateOrCreate(['uid' => $p['id']], [
                'nombre' => $p['nombre'] ?? 'Proveedor', 'ruc' => ($p['ruc'] ?? '') ?: null, 'celular' => ($p['cel'] ?? '') ?: null,
                'extra' => Arr::except($p, ['id', 'nombre', 'ruc', 'cel']) ?: null,
            ]);
            $this->proveedores[$p['id']] = $prov->id;
            $this->contar('proveedores');
        }
        foreach ($this->lista($c['facturas'] ?? []) as $f) {
            if (empty($f['id'])) {
                continue;
            }
            $comp = Compra::updateOrCreate(['uid' => $f['id']], [
                'proveedor_id' => $this->proveedores[$f['prov'] ?? ''] ?? null, 'numero' => ($f['numero'] ?? '') ?: null,
                'fecha' => $this->fecha($f['fecha'] ?? null) ?? now()->toDateString(), 'total' => (int) ($f['total'] ?? 0),
                'condicion' => $f['condicion'] ?? 'contado', 'vence' => $this->fecha($f['vence'] ?? null), 'nota' => ($f['nota'] ?? '') ?: null,
                'extra' => Arr::except($f, ['id', 'prov', 'numero', 'fecha', 'total', 'condicion', 'vence', 'nota', 'items', 'pagos']) ?: null,
            ]);
            $comp->items()->delete();
            foreach (array_values($f['items'] ?? []) as $i => $l) {
                $comp->items()->create([
                    'producto_id' => $this->productoId($l['sid'] ?? null), 'producto_uid' => $l['sid'] ?? null, 'nombre' => $l['nombre'] ?? '?',
                    'cantidad' => $l['cant'] ?? 1, 'unidad' => $l['un'] ?? null, 'factor' => $l['f'] ?? 1, 'costo_unitario' => round((float) ($l['costoU'] ?? 0), 4),
                    'subtotal' => (int) round($l['sub'] ?? ($l['cant'] ?? 1) * ($l['costoU'] ?? 0)),
                    'costo_antes' => $l['costoAnt'] ?? null, 'costo_nuevo' => $l['costoNuevo'] ?? null, 'orden' => $i,
                    'extra' => Arr::except($l, ['sid', 'nombre', 'cant', 'un', 'f', 'costoU', 'sub', 'costoAnt', 'costoNuevo', 'unid']) ?: null,
                ]);
            }
            $comp->pagos()->delete();
            foreach ($f['pagos'] ?? [] as $g) {
                $comp->pagos()->create(['monto' => (int) ($g['monto'] ?? 0), 'metodo' => $g['metodo'] ?? null, 'caja_mov_uid' => is_array($g['mov'] ?? null) ? ($g['mov']['id'] ?? null) : ($g['mov'] ?? null), 'pagado_at' => $this->ms($g['t'] ?? null) ?? now()]);
            }
            $this->contar('compras');
        }
    }

    private function documentos(array $docs, array $modelos): void
    {
        foreach ($docs as $r) {
            if (! is_array($r) || empty($r['id'])) {
                continue;
            }
            Documento::updateOrCreate(['uid' => $r['id']], [
                'plantilla' => $r['tpl'] ?? null, 'titulo' => Str::limit($r['titulo'] ?? '', 250, '') ?: null, 'estado' => $r['estado'] ?? 'borrador',
                'cliente_id' => $this->clienteId(($r['clis'] ?? [])[0] ?? null), 'venta_uid' => is_array($r['venta'] ?? null) ? ($r['venta']['id'] ?? null) : null,
                'datos' => Arr::except($r, ['html']), 'html' => $r['html'] ?? null,
                'created_at' => $this->ms($r['t'] ?? null) ?? now(),
            ]);
            $this->contar('documentos');
        }
        foreach ($modelos as $m) {
            if (! is_array($m) || empty($m['id'])) {
                continue;
            }
            ModeloDocumento::updateOrCreate(['uid' => $m['id']], ['nombre' => Str::limit($m['nombre'] ?? 'Modelo', 250, ''), 'datos' => $m]);
            $this->contar('modelos de documento');
        }
    }
}
