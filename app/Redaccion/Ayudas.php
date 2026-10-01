<?php

namespace App\Redaccion;

use App\Support\Dinero;
use App\Support\Texto;
use Carbon\Carbon;

/**
 * Piezas de texto formal que usan los modelos (las mismas del sistema anterior): datos que faltan como
 * [COMPLETAR], montos y fechas en letras, don/doña, datos de cada persona, cuotas, precios y firmas.
 * En las plantillas se usa como $h: {{ $h->persona('vend') }}, {{ $h->monto('precio') }}…
 */
class Ayudas
{
    public const FALTA = '[COMPLETAR]';

    public const MESES = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];

    public const ESTADOS_CIVILES = ['soltero' => ['soltero', 'soltera'], 'casado' => ['casado', 'casada'], 'conviviente' => ['conviviente', 'conviviente'],
        'viudo' => ['viudo', 'viuda'], 'divorciado' => ['divorciado', 'divorciada']];

    public function __construct(public array $d, public int $nivel = 2) {}

    // ---------------------------------------------------------------- datos

    /** el dato tal como está (texto vacío si no hay) */
    public function v(string $k): string
    {
        $v = $this->d[$k] ?? '';

        return is_scalar($v) ? trim((string) $v) : '';
    }

    public function hay(string $k): bool
    {
        return $this->v($k) !== '';
    }

    /** el dato o [COMPLETAR] */
    public function x(string $k): string
    {
        return self::xv($this->v($k));
    }

    public static function xv(?string $v): string
    {
        return trim((string) $v) !== '' ? trim((string) $v) : self::FALTA;
    }

    public function up(string $k): string
    {
        return mb_strtoupper($this->x($k));
    }

    /** casillas: marcadas salvo que digan false */
    public function on(string $k): bool
    {
        $v = $this->d[$k] ?? false;

        return $v !== false && $v !== 'false' && $v !== '' && $v !== null && $v !== 0 && $v !== '0';
    }

    /** texto escrito con palabras del cliente, ordenado: sin espacios de más ni punto final */
    public function t(string $k): string
    {
        return self::limpiar($this->v($k));
    }

    public static function limpiar(?string $t): string
    {
        $t = preg_replace('/[ \t]+/', ' ', (string) $t);
        $t = preg_replace('/ ([,.;:])/', '$1', $t);
        $t = preg_replace("/\n{3,}/", "\n\n", $t);

        return rtrim(trim($t), '.');
    }

    /** texto con mayúscula inicial y punto final */
    public static function oracion(string $t): string
    {
        $t = self::limpiar($t);

        return $t === '' ? '' : mb_strtoupper(mb_substr($t, 0, 1)).mb_substr($t, 1).'.';
    }

    /** quita del inicio lo que la plantilla ya dice (p. ej. «me dedico a») */
    public function sin(string $k, string $prefijo): string
    {
        return preg_replace('/^'.preg_quote($prefijo, '/').'\s+/iu', '', $this->t($k));
    }

    /** una línea por elemento, sin viñetas */
    public function lineas(string $k): array
    {
        return array_values(array_filter(array_map(fn ($x) => trim(preg_replace('/^[\s•\-–*]+/u', '', $x)), preg_split("/\r?\n/", $this->v($k)))));
    }

    /** párrafos que empiezan con «Que,» (solicitudes y declaraciones) */
    public function parrafosQue(string $k): array
    {
        $ps = array_values(array_filter(array_map('trim', preg_split("/\r?\n\s*\r?\n|\r?\n/", $this->v($k)))));

        return array_map(function ($t) {
            $t = self::oracion($t);

            return preg_match('/^que[\s,]/iu', $t) ? $t : 'Que, '.mb_strtolower(mb_substr($t, 0, 1)).mb_substr($t, 1);
        }, $ps);
    }

    /** a) uno; b) dos… */
    public static function enumerar(array $xs, string $sep = '; '): string
    {
        return implode($sep, array_map(fn ($t, $i) => chr(97 + $i).') '.rtrim($t, '.;'), $xs, array_keys($xs)));
    }

    // ---------------------------------------------------------------- números, montos y fechas

    public static function letras(int|float $n): string
    {
        return Texto::letras((int) floor(abs($n)));
    }

    /** «veintiún» en lugar de «veintiuno» antes de un sustantivo */
    public static function apoc(string $t): string
    {
        return preg_replace(['/veintiuno$/u', '/uno$/u'], ['veintiún', 'un'], $t);
    }

    /** «tres (3) cuotas», «un (1) mes» */
    public static function numLetras(int|string|null $n, string $uno, string $varios): string
    {
        $n = (int) $n;

        return ($n === 1 ? 'un' : self::apoc(self::letras($n))).' ('.$n.') '.($n === 1 ? $uno : $varios);
    }

    public function c(string $k): int
    {
        return Dinero::aCentimos($this->v($k)) ?? 0;
    }

    /** «S/ 1,500.00 (MIL QUINIENTOS Y 00/100 SOLES)» */
    public static function montoTxt(int $c): string
    {
        return $c > 0 ? 'S/ '.number_format($c / 100, 2, '.', ',').' ('.Texto::montoLetras($c).')' : 'S/ '.self::FALTA;
    }

    public function monto(string $k): string
    {
        return self::montoTxt($this->c($k));
    }

    public static function soles(int $c): string
    {
        return 'S/ '.number_format($c / 100, 2, '.', ',');
    }

    private static function dia(?string $k): ?Carbon
    {
        return $k && preg_match('/^\d{4}-\d{2}-\d{2}/', $k) ? Carbon::parse(substr($k, 0, 10)) : null;
    }

    /** «29 de septiembre de 2026» */
    public static function fechaTxt(?string $k): string
    {
        $d = self::dia($k);

        return $d ? $d->day.' de '.self::MESES[$d->month - 1].' de '.$d->year : self::FALTA;
    }

    public function fecha(string $k): string
    {
        return self::fechaTxt($this->v($k));
    }

    /** «a los 29 días del mes de septiembre de 2026» */
    public function cierre(string $k): string
    {
        $d = self::dia($this->v($k));

        return $d ? ($d->day === 1 ? 'al primer día' : 'a los '.$d->day.' días').' del mes de '.self::MESES[$d->month - 1].' de '.$d->year : self::FALTA;
    }

    /** fecha de término: inicio + meses − 1 día */
    public static function sumarMeses(?string $k, int|string|null $m): ?string
    {
        $d = self::dia($k);

        return $d && (int) $m > 0 ? $d->addMonthsNoOverflow((int) $m)->subDay()->toDateString() : null;
    }

    // ---------------------------------------------------------------- personas

    public function fem(string $p): bool
    {
        return ($this->d[$p.'_trato'] ?? '') === 'Doña';
    }

    /** terminación o/a: identificad{{ $h->o('vend') }} */
    public function o(string $p): string
    {
        return $this->fem($p) ? 'a' : 'o';
    }

    public function don(string $p): string
    {
        return $this->fem($p) ? 'doña' : 'don';
    }

    /** «don **JUAN PÉREZ**, identificado con DNI N° …, de estado civil …, con domicilio en …» */
    public function persona(string $p, bool $sinTrato = false, bool $sinEc = false, bool $sinDom = false): string
    {
        $f = $this->fem($p);
        $ec = $this->v($p.'_ec');
        $t = ($sinTrato ? '' : ($f ? 'doña ' : 'don ')).'**'.$this->up($p.'_nombre').'**, identificad'.($f ? 'a' : 'o').' con DNI N° '.$this->x($p.'_dni');
        if (! $sinEc && isset(self::ESTADOS_CIVILES[$ec])) {
            $t .= ', de estado civil '.self::ESTADOS_CIVILES[$ec][$f ? 1 : 0];
        }
        if (! $sinEc && $ec === 'casado' && $this->hay($p.'_cony')) {
            $t .= ', con '.($f ? 'su cónyuge don ' : 'su cónyuge doña ').'**'.$this->up($p.'_cony').'**, identificad'.($f ? 'o' : 'a').' con DNI N° '.$this->x($p.'_conyDni');
        }

        return $sinDom ? $t : $t.', con domicilio en '.$this->x($p.'_dom');
    }

    /** línea de firma: «[firma] NOMBRE | DNI | rol» */
    public function firma(string $p, string $rol, bool $huella = true): string
    {
        return self::firmaTxt($this->up($p.'_nombre'), $this->x($p.'_dni'), $rol, $huella);
    }

    public static function firmaTxt(string $nombre, string $dni, string $rol, bool $huella = true): string
    {
        return '[firma] '.str_replace('|', '/', $nombre).' | '.str_replace('|', '/', $dni).' | '.str_replace('|', '/', $rol).($huella ? '' : ' | sin huella');
    }

    /** la firma de la persona y, si es casada, la de su cónyuge */
    public function firmasCony(string $p, string $rol): string
    {
        $out = [$this->firma($p, $rol)];
        if ($this->v($p.'_ec') === 'casado' && $this->hay($p.'_cony')) {
            $out[] = self::firmaTxt($this->up($p.'_cony'), $this->x($p.'_conyDni'), str_replace('de el ', 'del ', 'Cónyuge de '.mb_strtolower($rol)));
        }

        return implode("\n", $out);
    }

    public static function introPartes(string $tipo, string $a, string $ra, string $b, string $rb): string
    {
        return 'Conste por el presente documento el '.$tipo.' que celebran, de una parte, '.$a.', a quien en adelante se le denominará **'.$ra.'**; y, de la otra parte, '.$b.', a quien en adelante se le denominará **'.$rb.'**; en los términos y condiciones siguientes:';
    }

    // ---------------------------------------------------------------- piezas de contratos

    /** precio al contado o con adelanto y saldo */
    public function clPrecio(): string
    {
        $p = $this->c('precio');
        if ($this->v('pago') === 'partes') {
            $a = $this->c('adelanto');
            $sal = $p > 0 && $a > 0 ? $p - $a : 0;

            return 'El precio pactado por la venta es de **'.self::montoTxt($p).'**, que será pagado de la siguiente manera: a) '.self::montoTxt($a).' a la firma del presente contrato, que EL VENDEDOR declara recibir a su entera satisfacción; y b) el saldo de '.self::montoTxt($sal).', a más tardar el '.$this->fecha('saldoFecha').'. En caso de que EL COMPRADOR no cumpla con pagar el saldo en la fecha indicada, EL VENDEDOR podrá resolver el presente contrato de pleno derecho, comunicándolo por conducto notarial, conforme al artículo 1430 del Código Civil.';
        }

        return 'El precio pactado por la venta es de **'.self::montoTxt($p).'**, que EL COMPRADOR paga a EL VENDEDOR en dinero en efectivo a la firma del presente contrato, declarando EL VENDEDOR haberlo recibido a su entera satisfacción, sin más constancia que las firmas puestas en este documento.';
    }

    public function incumplimientoVenta(): string
    {
        return $this->v('pago') === 'partes'
            ? 'Si EL COMPRADOR no pagara el saldo del precio en la fecha pactada, EL VENDEDOR podrá requerirle por escrito que cumpla en un plazo no menor de quince (15) días, bajo apercibimiento de que, en caso contrario, el contrato quede resuelto de pleno derecho, conforme al artículo 1429 del Código Civil, debiendo las partes restituirse lo recibido.'
            : 'Si alguna de las partes incumpliera sus obligaciones, la otra podrá requerirle por escrito que cumpla en un plazo no menor de quince (15) días, bajo apercibimiento de que, en caso contrario, el contrato quede resuelto de pleno derecho, conforme al artículo 1429 del Código Civil, sin perjuicio de la indemnización por los daños y perjuicios causados.';
    }

    /** cuotas iguales (la última ajusta los céntimos): [[n, fecha, monto]] */
    public static function cronograma(int $total, int|string|null $n, ?string $primera, ?string $per): array
    {
        $n = max(1, (int) $n);
        $base = intdiv($total, $n);
        $d0 = self::dia($primera);
        $out = [];
        for ($i = 0; $i < $n; $i++) {
            $f = null;
            if ($d0) {
                $f = match ($per) {
                    'semanal' => $d0->copy()->addDays(7 * $i),
                    'quincenal' => $d0->copy()->addDays(15 * $i),
                    default => $d0->copy()->addMonthsNoOverflow($i),
                };
            }
            $out[] = ['n' => $i + 1, 'f' => $f?->toDateString(), 'm' => $i === $n - 1 ? $total - $base * ($n - 1) : $base];
        }

        return $out;
    }

    public function cuotas(int $total): array
    {
        return $this->v('forma') === 'unica' ? [] : array_map(fn ($c) => 'Cuota N° '.$c['n'].': '.self::soles($c['m']).', a más tardar el '.self::fechaTxt($c['f']).'.',
            self::cronograma($total, $this->v('ncuotas'), $this->v('primera'), $this->v('periodo')));
    }

    public function pagoTxt(int $total, string $quien, string $a): string
    {
        if ($this->v('forma') === 'unica') {
            return $quien.' se obliga a pagar a '.$a.' la suma total de **'.self::montoTxt($total).'** a más tardar el '.$this->fecha('fechaUnica').'.';
        }
        $per = ['mensual' => 'mensuales', 'quincenal' => 'quincenales', 'semanal' => 'semanales'][$this->v('periodo')] ?? 'mensuales';

        return $quien.' se obliga a pagar a '.$a.' la suma total de **'.self::montoTxt($total).'** en '.self::numLetras($this->v('ncuotas') ?: 1, 'cuota', 'cuotas').' '.$per.', según el siguiente cronograma:';
    }

    public function lugarTxt(string $cobra): string
    {
        return match ($this->v('lugarPago')) {
            'deposito' => 'Los pagos se efectuarán mediante depósito o transferencia a la cuenta de '.$cobra.($this->hay('cuenta') ? ' N° '.$this->v('cuenta') : ' que este indique por escrito').'; el comprobante de la operación servirá de constancia de pago.',
            'yape' => 'Los pagos se efectuarán mediante Yape o Plin al número '.$this->x('cuenta').' de '.$cobra.'; la constancia de la operación servirá de constancia de pago.',
            default => 'Los pagos se efectuarán en dinero en efectivo, en el domicilio de '.$cobra.' señalado en el presente documento, quien entregará un recibo firmado por cada pago.',
        };
    }

    /** lo que se devuelve en un préstamo: monto + interés mensual por los meses que dura */
    public function prestamoTotal(): int
    {
        $m = $this->c('monto');
        if ($this->v('interes') !== 'si') {
            return $m;
        }
        $tasa = (float) str_replace(',', '.', $this->v('tasa'));
        if ($this->v('forma') === 'unica') {
            $a = self::dia($this->v('fecha'));
            $b = self::dia($this->v('fechaUnica'));
            $meses = $a && $b ? max(1, (int) ceil($a->diffInDays($b, false) / 30)) : 1;
        } else {
            $n = max(1, (int) $this->v('ncuotas'));
            $meses = match ($this->v('periodo')) {
                'semanal' => max(1, (int) ceil($n * 7 / 30)),
                'quincenal' => max(1, (int) ceil($n / 2)),
                default => $n,
            };
        }

        return $m + (int) round($m * $tasa / 100 * $meses);
    }

    /** tiempo entre dos fechas: «dos (2) años y tres (3) meses» */
    public static function duracion(?string $desde, ?string $hasta): string
    {
        $a = self::dia($desde);
        $b = self::dia($hasta);
        if (! $a || ! $b || $b->lt($a)) {
            return '';
        }
        $m = ($b->year - $a->year) * 12 + $b->month - $a->month - ($b->day < $a->day ? 1 : 0);
        $y = intdiv($m, 12);
        $mm = $m % 12;

        return implode(' y ', array_filter([$y ? ($y === 1 ? 'un (1) año' : self::apoc(self::letras($y)).' ('.$y.') años') : '',
            $mm ? ($mm === 1 ? 'un (1) mes' : self::apoc(self::letras($mm)).' ('.$mm.') meses') : '']));
    }

    /** área en letras: «250.00 m² (doscientos cincuenta metros cuadrados)» */
    public function area(string $k): string
    {
        $a = (float) str_replace(',', '.', $this->v($k));
        if (! ($a > 0)) {
            return self::FALTA.' m²';
        }
        $dec = (int) round(fmod($a, 1) * 100);

        return number_format($a, 2, '.', '').' m² ('.self::letras($a).($dec ? ' coma '.self::letras($dec) : '').' metros cuadrados)';
    }
}
