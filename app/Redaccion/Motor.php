<?php

namespace App\Redaccion;

use App\Models\ModeloRedaccion;
use App\Services\Comprobantes;
use App\Support\Dinero;
use App\Support\NegocioActual;
use Illuminate\Support\Facades\Blade;

/**
 * Arma un documento con un modelo y los datos del formulario (contrato(), build() y clausulasDe() del sistema anterior).
 * Los modelos de contrato llevan nivel (simple, intermedio, avanzado), cláusulas que se prenden o apagan,
 * cláusulas propias y testigos; su texto se numera solo (PRIMERA, SEGUNDA…).
 */
class Motor
{
    public const NIVELES = [1 => ['Simple', 'Lo esencial, corto y directo'], 2 => ['Intermedio', 'Protección normal para ambas partes'], 3 => ['Avanzado', 'Máxima protección: riesgos, penalidades y controversias']];

    public const ORDINALES = ['PRIMERA', 'SEGUNDA', 'TERCERA', 'CUARTA', 'QUINTA', 'SEXTA', 'SÉPTIMA', 'OCTAVA', 'NOVENA', 'DÉCIMA', 'UNDÉCIMA', 'DUODÉCIMA',
        'DÉCIMO TERCERA', 'DÉCIMO CUARTA', 'DÉCIMO QUINTA', 'DÉCIMO SEXTA', 'DÉCIMO SÉPTIMA', 'DÉCIMO OCTAVA', 'DÉCIMO NOVENA', 'VIGÉSIMA', 'VIGÉSIMO PRIMERA',
        'VIGÉSIMO SEGUNDA', 'VIGÉSIMO TERCERA', 'VIGÉSIMO CUARTA', 'VIGÉSIMO QUINTA'];

    public function __construct(public ModeloRedaccion $m) {}

    public function esContrato(): bool
    {
        return (bool) $this->m->def('contrato', false);
    }

    /** currículum: se arma con Curriculum, no con líneas de texto */
    public function esCv(): bool
    {
        return $this->m->def('tipo') === 'cv';
    }

    /** frases de ejemplo de un campo; con «frasesPor» dependen de otro campo (el tipo de trabajo del CV) */
    public static function frasesDe(array $c, array $d): array
    {
        $f = $c['frases'] ?? [];

        return isset($c['frasesPor']) ? array_values((array) ($f[(string) ($d[$c['frasesPor']] ?? '')] ?? [])) : array_values((array) $f);
    }

    public static function nivel(array $d): int
    {
        return in_array((int) ($d['nivel'] ?? 0), [1, 2, 3], true) ? (int) $d['nivel'] : 2;
    }

    // ---------------------------------------------------------------- campos

    /** todos los campos del modelo; a los contratos se les agregan testigos, ejemplares, ciudad y fecha */
    public function definicionCampos(): array
    {
        $c = $this->m->campos();
        if ($this->esContrato()) {
            $c = [...$c,
                ['t' => 'h', 'label' => 'Testigos', 'si' => 'cl:testigos'],
                ['t' => 'persona', 'id' => 'tes1', 'label' => 'Testigo 1', 'sinEc' => true, 'sinDom' => true, 'si' => 'cl:testigos'],
                ['t' => 'persona', 'id' => 'tes2', 'label' => 'Testigo 2 (opcional)', 'sinEc' => true, 'sinDom' => true, 'opcional' => true, 'si' => 'cl:testigos'],
                ['t' => 'h', 'label' => 'Firma'],
                ['id' => 'ejemplares', 'label' => 'Ejemplares que se firman', 'type' => 'num', 'def' => '2'],
            ];
        }
        if (! collect($c)->contains(fn ($x) => ($x['id'] ?? '') === 'fecha') && $this->m->def('ciudadFecha', true)) {
            $c = [...$c, ['id' => 'ciudad', 'label' => 'Ciudad', 'def' => '@ciudad'], ['id' => 'fecha', 'label' => 'Fecha del documento', 'type' => 'date', 'def' => '@hoy']];
        }

        return $c;
    }

    /** los datos de una persona: trato, nombre, DNI, estado civil, domicilio, cónyuge y celular */
    public static function camposPersona(array $c): array
    {
        $p = $c['id'];
        $req = empty($c['opcional']);
        $L = [
            ['id' => $p.'_trato', 'label' => 'Trato', 'type' => 'select', 'opts' => ['Don' => 'Don (varón)', 'Doña' => 'Doña (mujer)']],
            ['id' => $p.'_nombre', 'label' => 'Nombres y apellidos completos', 'req' => $req],
            ['id' => $p.'_dni', 'label' => 'DNI', 'req' => $req, 'num' => true],
        ];
        if (empty($c['sinEc'])) {
            $L[] = ['id' => $p.'_ec', 'label' => 'Estado civil', 'type' => 'select', 'opts' => ['' => '(no indicar)', 'soltero' => 'Soltero(a)', 'casado' => 'Casado(a)', 'conviviente' => 'Conviviente', 'viudo' => 'Viudo(a)', 'divorciado' => 'Divorciado(a)']];
        }
        if (empty($c['sinDom'])) {
            $L[] = ['id' => $p.'_dom', 'label' => 'Domicilio', 'full' => true, 'req' => $req];
        }
        if (! empty($c['cony'])) {
            $L[] = ['id' => $p.'_cony', 'label' => 'Nombre del cónyuge', 'si' => $p.'_ec=casado'];
            $L[] = ['id' => $p.'_conyDni', 'label' => 'DNI del cónyuge', 'num' => true, 'si' => $p.'_ec=casado'];
        }
        $L[] = ['id' => $p.'_cel', 'label' => 'Celular (para tu registro de clientes)', 'num' => true, 'ph' => 'Opcional, no sale en el documento'];

        return $L;
    }

    public function visible(array $c, array $d): bool
    {
        return Condicion::cumple($c['si'] ?? null, $d, fn ($id) => $this->clOnId($id, $d), self::nivel($d));
    }

    /** valores iniciales del formulario */
    public function iniciales(): array
    {
        $d = [];
        $neg = app(NegocioActual::class)->get();
        foreach ($this->definicionCampos() as $c) {
            if (($c['t'] ?? null) === 'persona') {
                $d[$c['id'].'_trato'] = 'Don';

                continue;
            }
            if (($c['t'] ?? null) === 'lista') {
                $d[$c['id']] = [[]];

                continue;
            }
            if (isset($c['id']) && array_key_exists('def', $c)) {
                $d[$c['id']] = match ($c['def']) {
                    '@hoy' => today()->toDateString(), '@ciudad' => (string) ($neg?->ciudad ?? ''),
                    '@ciudadPeru' => $neg?->ciudad ? $neg->ciudad.', Perú' : '', default => $c['def']
                };
            }
        }
        if ($this->m->niveles) {
            $d['nivel'] = 2;
        }

        return $d;
    }

    /** los campos requeridos que faltan (se avisa, pero se puede generar igual) */
    public function faltan(array $d): array
    {
        $out = [];
        foreach ($this->definicionCampos() as $c) {
            if (! $this->visible($c, $d)) {
                continue;
            }
            if (($c['t'] ?? null) === 'persona') {
                foreach (self::camposPersona($c) as $f) {
                    if (! empty($f['req']) && $this->visible($f, $d) && trim((string) ($d[$f['id']] ?? '')) === '') {
                        $out[] = $c['label'].': '.mb_strtolower($f['label']);
                    }
                }
            } elseif (empty($c['t']) && ! empty($c['req']) && trim((string) ($d[$c['id']] ?? '')) === '') {
                $out[] = $c['label'];
            }
        }

        return $out;
    }

    /** datos que no cuadran (validarDoc): DNI de 8 números, RUC, montos, fechas al revés, adelantos, cuotas, intereses */
    public function observaciones(array $d): array
    {
        $o = [];
        $dnis = [];
        $vis = fn ($id) => collect($this->definicionCampos())->contains(fn ($c) => ($c['id'] ?? '') === $id && $this->visible($c, $d));
        $c0 = fn ($k) => Dinero::aCentimos($d[$k] ?? '') ?? 0;
        foreach ($this->definicionCampos() as $c) {
            if (! $this->visible($c, $d)) {
                continue;
            }
            if (($c['t'] ?? null) === 'persona') {
                $v = trim((string) ($d[$c['id'].'_dni'] ?? ''));
                if ($v !== '' && ! preg_match('/^\d{8}$/', $v)) {
                    $o[] = 'El DNI de «'.$c['label'].'» debe tener 8 números (dice «'.$v.'»).';
                }
                if ($v !== '' && isset($dnis[$v])) {
                    $o[] = '«'.$c['label'].'» y «'.$dnis[$v].'» tienen el mismo DNI.';
                } elseif ($v !== '') {
                    $dnis[$v] = $c['label'];
                }

                continue;
            }
            if (! empty($c['t'])) {
                continue;
            }
            $v = trim((string) ($d[$c['id']] ?? ''));
            if ($v === '') {
                continue;
            }
            if (preg_match('/ruc/i', $c['id']) && ! Comprobantes::rucValido($v)) {
                $o[] = '«'.$c['label'].'» no es un RUC válido.';
            }
            if (($c['type'] ?? '') === 'money' && ! ($c0($c['id']) > 0)) {
                $o[] = '«'.$c['label'].'»: escribe un monto mayor que cero.';
            }
        }
        foreach ([['inicio', 'fin', 'La fecha de término es anterior a la de inicio.'], ['desde', 'hasta', 'La fecha «hasta» es anterior a «desde».']] as [$a, $b, $msg]) {
            if ($vis($a) && $vis($b) && ! empty($d[$a]) && ! empty($d[$b]) && $d[$b] < $d[$a]) {
                $o[] = $msg;
            }
        }
        if (($d['pago'] ?? '') === 'partes' && $c0('adelanto') > 0 && $c0('precio') > 0 && $c0('adelanto') >= $c0('precio')) {
            $o[] = 'El adelanto es igual o mayor que el precio total.';
        }
        if ($vis('adelanto') && ($d['pagoT'] ?? '') === 'partes' && $c0('monto') > 0 && $c0('adelanto') >= $c0('monto')) {
            $o[] = 'El adelanto es igual o mayor que el monto del servicio.';
        }
        if ($vis('ncuotas') && ! ((int) ($d['ncuotas'] ?? 0) > 0)) {
            $o[] = 'Escribe el número de cuotas.';
        }
        foreach ([['primera', 'La primera cuota es antes de la fecha del documento.'], ['fechaUnica', 'La fecha de pago es anterior a la fecha del documento.'], ['saldoFecha', 'La fecha para pagar el saldo es anterior a la fecha del documento.']] as [$k, $msg]) {
            if ($vis($k) && ! empty($d[$k]) && ! empty($d['fecha']) && $d[$k] < $d['fecha']) {
                $o[] = $msg;
            }
        }
        if ($vis('plazo') && ! ((int) ($d['plazo'] ?? 0) > 0)) {
            $o[] = 'El plazo debe ser de al menos 1 mes.';
        }
        if ($vis('tasa') && (float) str_replace(',', '.', (string) ($d['tasa'] ?? '')) > 10) {
            $o[] = 'Un interés mayor al 10% mensual es muy alto y podría superar el máximo legal.';
        }

        return $o;
    }

    // ---------------------------------------------------------------- cláusulas

    /** las cláusulas del contrato en su orden (las comunes al final, jurisdicción siempre última) */
    public function clausulas(array $d): array
    {
        if (! $this->esContrato()) {
            return [];
        }
        $sin = (array) $this->m->def('sin_comunes', []);
        $base = [...(array) $this->m->def('clausulas', []), ...array_filter(Catalogo::comunes(), fn ($c) => ! in_array($c['id'], $sin, true))];
        $custom = array_map(fn ($c) => ['id' => (string) $c['id'], 'h' => (string) ($c['h'] ?? 'Cláusula'), 't' => (string) ($c['t'] ?? ''), 'custom' => true], (array) ($d['_cl']['custom'] ?? []));
        $juris = array_values(array_filter($base, fn ($c) => $c['id'] === 'juris'));
        $all = [...array_values(array_filter($base, fn ($c) => $c['id'] !== 'juris')), ...$custom, ...$juris];
        $ord = (array) ($d['_cl']['orden'] ?? []);
        if ($ord) {
            $pos = fn ($id) => ($i = array_search($id, $ord, true)) === false ? 999 : $i;
            $idx = array_flip(array_column($all, 'id'));
            usort($all, fn ($a, $b) => ($pos($a['id']) <=> $pos($b['id'])) ?: ($idx[$a['id']] <=> $idx[$b['id']]));
        }

        return $all;
    }

    public function clOn(array $c, array $d): bool
    {
        $on = (array) ($d['_cl']['on'] ?? []);
        if (array_key_exists($c['id'], $on)) {
            return filter_var($on[$c['id']], FILTER_VALIDATE_BOOL);
        }
        if (! empty($c['custom'])) {
            return true;
        }

        return empty($c['opt']) && ($c['n'] ?? 1) <= self::nivel($d);
    }

    public function clOnId(string $id, array $d): bool
    {
        foreach ($this->clausulas($d) as $c) {
            if ($c['id'] === $id) {
                return $this->clOn($c, $d);
            }
        }

        return false;
    }

    // ---------------------------------------------------------------- el documento

    /** una parte del modelo (Blade) con los datos: $d, $h (ayudas), $nv (nivel) y $cl('id') (si la cláusula está incluida) */
    private function pieza(?string $src, array $d, Ayudas $h): string
    {
        if ($src === null || trim($src) === '') {
            return '';
        }
        $out = Blade::render($src, ['d' => $d, 'h' => $h, 'nv' => $h->nivel, 'cl' => fn (string $id) => $this->clOnId($id, $d)], deleteCachedView: false);

        return trim(html_entity_decode($out, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }

    /** el documento en el formato por líneas */
    public function texto(array $d): string
    {
        if ($this->esCv()) {
            return '';
        }
        $h = new Ayudas($d, self::nivel($d));
        if (! $this->esContrato()) {
            return $this->limpiarLineas($this->pieza($this->m->def('plantilla'), $d, $h));
        }
        $L = ['[title] '.$this->pieza($this->m->def('titulo'), $d, $h), '[p] '.$this->pieza($this->m->def('intro'), $d, $h)];
        if ($antes = $this->pieza($this->m->def('antes'), $d, $h)) {
            $L[] = $antes;
        }
        $i = 0;
        foreach ($this->clausulas($d) as $c) {
            if (! $this->clOn($c, $d)) {
                continue;
            }
            $t = ! empty($c['custom']) ? Ayudas::oracion($c['t']) : $this->pieza($c['t'] ?? '', $d, $h);
            if (trim($t) === '') {
                continue;
            }
            $partes = preg_split("/\r?\n/", $t);
            $h1 = ! empty($c['custom']) ? mb_strtoupper($c['h']) : $c['h'];
            $L[] = '[p] **'.(self::ORDINALES[$i] ?? (string) ($i + 1)).'.- '.$h1.':** '.trim(array_shift($partes));
            foreach ($partes as $x) {
                if (trim($x) !== '') {
                    $L[] = $x;
                }
            }
            $i++;
        }
        $ej = max(1, (int) ($d['ejemplares'] ?? 2));
        $L[] = '[p] En señal de conformidad con todas y cada una de las cláusulas del presente contrato, las partes lo firman en '.Ayudas::numLetras($ej, 'ejemplar', 'ejemplares')
            .($ej > 1 ? ' de igual tenor y valor' : '').', e imprimen su huella digital, en la ciudad de '.$h->x('ciudad').', '.$h->cierre('fecha').'.';
        $L[] = $this->pieza($this->m->def('firmas'), $d, $h);
        if ($this->clOnId('testigos', $d)) {
            foreach (['tes1', 'tes2'] as $t) {
                if ($h->hay($t.'_nombre')) {
                    $L[] = $h->firma($t, 'Testigo');
                }
            }
        }

        return $this->limpiarLineas(implode("\n", $L));
    }

    /** quita espacios sobrantes que deja Blade al inicio de cada línea */
    private function limpiarLineas(string $t): string
    {
        $L = array_map(fn ($l) => preg_replace('/^\s+(\[\w+\])/', '$1', rtrim($l)), preg_split("/\r?\n/", $t));

        return trim(preg_replace("/\n{3,}/", "\n\n", implode("\n", $L)));
    }

    /** título para el historial */
    public function titulo(array $d, string $texto): string
    {
        if ($this->m->uid === 'solicitud') {
            return 'Solicitud: '.trim((string) ($d['sumilla'] ?? ''));
        }
        if ($this->m->cobro === 'cv') {
            return 'Currículum: '.trim((string) ($d['nombre'] ?? ''));
        }
        foreach (Formato::bloques($texto) as $b) {
            if ($b['k'] === 'title') {
                return mb_strtoupper(mb_substr($b['t'], 0, 1)).mb_strtolower(mb_substr($b['t'], 1));
            }
        }

        return $this->m->nombre;
    }

    /** nombres de las personas del documento */
    public function partes(array $d): string
    {
        if ($this->m->cobro === 'cv') {
            return trim((string) ($d['nombre'] ?? ''));
        }

        return collect($this->definicionCampos())->filter(fn ($c) => ($c['t'] ?? null) === 'persona' && ! str_starts_with($c['id'], 'tes') && $this->visible($c, $d))
            ->map(fn ($c) => trim((string) ($d[$c['id'].'_nombre'] ?? '')))->filter()->join(' y ');
    }
}
