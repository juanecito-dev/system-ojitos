<?php

namespace App\Redaccion;

use App\Support\Texto;

/**
 * El currículum en 4 diseños (cvBuild() y cvmData() del sistema anterior). Se arma siempre con los datos
 * del formulario: los textos se cambian en «Editar datos» y el diseño se cambia con un toque sin perder nada.
 * Segmentos de texto: [texto, negrita, cursiva].
 */
class Curriculum
{
    /** diseño => [nombre corto, opción del producto s_cv] */
    public const DISENOS = ['cv_simple' => ['Simple', 0], 'cv_medio' => ['Con foto', 1], 'cv_harvard' => ['Harvard', 2], 'cv_moderno' => ['Moderno', 3]];

    public const COLORES = ['#223A5E' => 'Azul', '#1E5B4F' => 'Verde', '#7A1F3D' => 'Vino', '#4B3A78' => 'Morado', '#0E6E7E' => 'Turquesa',
        '#B4532A' => 'Terracota', '#B23A6B' => 'Rosa', '#2B2B2B' => 'Gris'];

    public const TEMA = ['color' => '#223A5E', 'fuente' => 'sans', 'foto' => 'circulo', 'lado' => 'izq', 'tam' => 'normal'];

    /** opciones del panel de diseño del moderno */
    public const TEMA_OPCIONES = [
        'fuente' => ['Letra', ['sans' => 'Moderna', 'serif' => 'Clásica']],
        'foto' => ['Foto', ['circulo' => 'Círculo', 'cuadrado' => 'Cuadrada', 'sin' => 'Sin foto']],
        'lado' => ['Columna', ['izq' => 'Izquierda', 'der' => 'Derecha']],
        'tam' => ['Tamaño', ['normal' => 'Normal', 'compacto' => 'Compacto']],
    ];

    public static function es(?string $plantilla): bool
    {
        return isset(self::DISENOS[(string) $plantilla]);
    }

    // ------------------------------------------------------------ ayudas

    /** filas de una lista con algún dato (los vacíos se ignoran) */
    public static function lista(mixed $v): array
    {
        return array_values(array_filter(is_array($v) ? $v : [], fn ($x) => is_array($x) && collect($x)->contains(fn ($y) => is_scalar($y) && trim((string) $y) !== '')));
    }

    /** texto en líneas, sin viñetas escritas a mano */
    public static function lineas(mixed $v): array
    {
        return array_values(array_filter(array_map(fn ($t) => trim(preg_replace('/^[\s•\-–*]+/u', '', $t)), preg_split("/\r?\n/", (string) $v)), fn ($t) => $t !== ''));
    }

    public static function fechas(mixed $a, mixed $b): string
    {
        return implode(' – ', array_filter([trim((string) $a), trim((string) $b)], fn ($x) => $x !== ''));
    }

    public static function v(array $x, string $k): string
    {
        return trim((string) ($x[$k] ?? ''));
    }

    /** «Tema: detalle» sale con el tema en negrita */
    public static function tema(string $t, int $max = 45): array
    {
        return preg_match('/^([^:]{2,'.$max.'}):\s*(.+)$/u', $t, $m) ? [[$m[1].': ', true, false], [$m[2], false, false]] : [[$t, false, false]];
    }

    public static function celular(mixed $cel): string
    {
        $c = preg_replace('/\D/', '', (string) $cel);
        if (strlen($c) === 11 && str_starts_with($c, '51')) {
            $c = substr($c, 2);
        }

        return $c === '' ? '' : (strlen($c) === 9 ? '+51 '.Texto::fmtCel($c) : trim((string) $cel));
    }

    public static function contacto(array $d): string
    {
        return implode('  ·  ', array_filter([self::v($d, 'ciudadCv'), self::celular($d['cel'] ?? ''), self::v($d, 'correo'), self::v($d, 'linkedin')], fn ($x) => $x !== ''));
    }

    /** la foto guardada (data:image/…), si es válida */
    public static function foto(array $d): ?string
    {
        $f = (string) ($d['foto'] ?? '');

        return preg_match('#^data:image/(jpeg|jpg|png|webp);base64,[A-Za-z0-9+/=]+$#', $f) ? $f : null;
    }

    // ------------------------------------------------------------ simple, con foto y Harvard

    /**
     * Bloques del CV (cvBuild): [k => cvname|cvcontact|cvh|cvrow|cvsub|bul|cvsum|cvp, …].
     * cvrow y cvsub llevan l y r (izquierda y derecha); bul, cvsum y cvp llevan s (segmentos).
     */
    public static function bloques(array $d, string $diseno): array
    {
        $H = $diseno === 'cv_harvard';
        $I = fn ($t) => [(string) $t, false, true];
        $B = fn ($t) => [(string) $t, true, false];
        $N = fn ($t) => [(string) $t, false, false];
        $X = fn ($t) => trim((string) $t) !== '' ? trim((string) $t) : Ayudas::FALTA;
        $out = [['k' => 'cvname', 't' => $X($d['nombre'] ?? ''), 'sub' => $H ? '' : self::v($d, 'titulo')],
            ['k' => 'cvcontact', 't' => self::contacto($d) ?: Ayudas::FALTA]];
        $h = function ($t) use (&$out) {
            $out[] = ['k' => 'cvh', 't' => $t];
        };
        if (($perfil = self::v($d, 'perfil')) !== '') {
            if (! $H) {
                $h('Perfil profesional');
            }
            $out[] = ['k' => 'cvsum', 's' => $H ? [$I(preg_replace('/\s+/u', ' ', $perfil))] : array_map(fn ($s) => [$s[0], $s[1], false], Formato::negritas(preg_replace('/\s+/u', ' ', $perfil)))];
        }
        if (! $H) {
            $dp = array_filter(['DNI' => self::v($d, 'dni'), 'Fecha de nacimiento' => ($d['nac'] ?? '') ? Ayudas::fechaTxt((string) $d['nac']) : '',
                'Estado civil' => self::v($d, 'ecCv'), 'Dirección' => self::v($d, 'dirCv')], fn ($v) => $v !== '');
            if ($dp) {
                $h('Datos personales');
                foreach ($dp as $k => $v) {
                    $out[] = ['k' => 'cvp', 's' => [$B($k.': '), $N($v)]];
                }
            }
        }
        if ($edu = self::lista($d['edu'] ?? [])) {
            $h($H ? 'Educación' : 'Formación académica');
            foreach ($edu as $e) {
                $out[] = ['k' => 'cvrow', 'l' => [$B($H ? mb_strtoupper($X($e['inst'] ?? '')) : $X($e['inst'] ?? ''))], 'r' => [$H ? $B(self::v($e, 'lugar')) : $I(self::fechas($e['desde'] ?? '', $e['hasta'] ?? ''))]];
                $out[] = ['k' => 'cvsub', 'l' => [$H ? $N(self::v($e, 'grado')) : $I(implode(' – ', array_filter([self::v($e, 'grado'), self::v($e, 'lugar')])))],
                    'r' => $H ? [$I(self::fechas($e['desde'] ?? '', $e['hasta'] ?? ''))] : []];
                foreach (self::lineas($e['det'] ?? '') as $t) {
                    $out[] = ['k' => 'bul', 's' => [$N($t)]];
                }
            }
        }
        if ($exp = self::lista($d['exp'] ?? [])) {
            $h('Experiencia laboral');
            foreach ($exp as $e) {
                $out[] = ['k' => 'cvrow', 'l' => [$B($X($e['cargo'] ?? ''))], 'r' => [$I(self::fechas($e['desde'] ?? '', $e['hasta'] ?? ''))]];
                $out[] = ['k' => 'cvsub', 'l' => [$I(implode(' – ', array_filter([self::v($e, 'emp'), self::v($e, 'lugar')])))], 'r' => []];
                foreach (self::lineas($e['fun'] ?? '') as $t) {
                    $out[] = ['k' => 'bul', 's' => self::tema($t)];
                }
            }
        }
        if ($cert = self::lista($d['cert'] ?? [])) {
            $h($H ? 'Certificaciones' : 'Cursos y certificaciones');
            foreach ($cert as $c) {
                $out[] = ['k' => 'cvrow', 'l' => [$B($X($c['nom'] ?? ''))], 'r' => [$I(self::v($c, 'fecha'))]];
                if (self::v($c, 'inst') !== '') {
                    $out[] = ['k' => 'cvsub', 'l' => [$I(self::v($c, 'inst'))], 'r' => []];
                }
            }
        }
        $hab = self::lineas($d['hab'] ?? '');
        if ($hab || self::v($d, 'idiomas') !== '') {
            $h($H ? 'Habilidades técnicas' : 'Habilidades');
            foreach ($hab as $t) {
                $out[] = ['k' => 'bul', 's' => self::tema($t, 40)];
            }
            if (self::v($d, 'idiomas') !== '') {
                $out[] = ['k' => 'bul', 's' => [$B('Idiomas: '), $N(self::v($d, 'idiomas'))]];
            }
        }
        if ($refs = self::lineas($d['refs'] ?? '')) {
            $h('Referencias');
            foreach ($refs as $t) {
                $out[] = ['k' => 'bul', 's' => [$N($t)]];
            }
        }

        return $out;
    }

    // ------------------------------------------------------------ moderno

    public static function temaDe(array $d): array
    {
        $t = array_merge(self::TEMA, array_intersect_key(is_array($d['tema'] ?? null) ? $d['tema'] : [], self::TEMA));
        if (! preg_match('/^#[0-9a-f]{6}$/i', (string) $t['color'])) {
            $t['color'] = self::TEMA['color'];
        }
        foreach (self::TEMA_OPCIONES as $k => [, $ops]) {
            if (! isset($ops[$t[$k]])) {
                $t[$k] = self::TEMA[$k];
            }
        }

        return $t;
    }

    /** el color elegido, el del encabezado (más claro) y el de la columna (casi blanco): cvmColors() */
    public static function colores(string $hex): array
    {
        $v = hexdec(ltrim($hex, '#'));
        $c = [($v >> 16) & 255, ($v >> 8) & 255, $v & 255];
        [$h, $s] = self::hsl($c);
        $a = fn ($rgb) => sprintf('#%02x%02x%02x', ...$rgb);

        return ['c' => $a($c), 'h' => $a(self::rgb([$h, min(.5, $s * .85), .86])), 's' => $a(self::rgb([$h, min(.65, $s * .9), .955]))];
    }

    private static function hsl(array $c): array
    {
        [$r, $g, $b] = array_map(fn ($x) => $x / 255, $c);
        $mx = max($r, $g, $b);
        $mn = min($r, $g, $b);
        $l = ($mx + $mn) / 2;
        $h = $s = 0;
        if ($mx !== $mn) {
            $dd = $mx - $mn;
            $s = $l > .5 ? $dd / (2 - $mx - $mn) : $dd / ($mx + $mn);
            $h = ($mx === $r ? ($g - $b) / $dd + ($g < $b ? 6 : 0) : ($mx === $g ? ($b - $r) / $dd + 2 : ($r - $g) / $dd + 4)) / 6;
        }

        return [$h, $s, $l];
    }

    private static function rgb(array $x): array
    {
        [$h, $s, $l] = $x;
        if (! $s) {
            return array_fill(0, 3, (int) round($l * 255));
        }
        $q = $l < .5 ? $l * (1 + $s) : $l + $s - $l * $s;
        $p = 2 * $l - $q;
        $f = function ($t) use ($p, $q) {
            $t = fmod($t + 1, 1);

            return $t < 1 / 6 ? $p + ($q - $p) * 6 * $t : ($t < .5 ? $q : ($t < 2 / 3 ? $p + ($q - $p) * (2 / 3 - $t) * 6 : $p));
        };

        return array_map(fn ($v) => (int) round($v * 255), [$f($h + 1 / 3), $f($h), $f($h - 1 / 3)]);
    }

    /** el nombre en dos líneas (con 4 palabras o más, dos arriba) */
    public static function nombreDos(mixed $n): array
    {
        $w = preg_split('/\s+/u', trim((string) $n), -1, PREG_SPLIT_NO_EMPTY);
        if (! $w) {
            return [Ayudas::FALTA, ''];
        }
        $k = count($w) >= 4 ? 2 : 1;

        return [implode(' ', array_slice($w, 0, $k)), implode(' ', array_slice($w, $k))];
    }

    public static function iniciales(mixed $n): string
    {
        return mb_strtoupper(implode('', array_map(fn ($x) => mb_substr($x, 0, 1), array_slice(preg_split('/\s+/u', trim((string) $n), -1, PREG_SPLIT_NO_EMPTY), 0, 2)))) ?: '?';
    }

    /** los datos ordenados para el diseño moderno (cvmData) */
    public static function moderno(array $d): array
    {
        $T = self::temaDe($d);
        $nac = trim((string) ($d['nac'] ?? ''));

        return [
            'tema' => $T, 'col' => self::colores($T['color']), 'nombre' => self::nombreDos($d['nombre'] ?? ''), 'titulo' => self::v($d, 'titulo'),
            'ini' => self::iniciales($d['nombre'] ?? ''), 'foto' => $T['foto'] === 'sin' ? null : self::foto($d),
            'contacto' => array_filter(['tel' => self::celular($d['cel'] ?? ''), 'mail' => self::v($d, 'correo'), 'web' => self::v($d, 'linkedin'),
                'pin' => implode(', ', array_filter([self::v($d, 'dirCv'), self::v($d, 'ciudadCv')]))], fn ($v) => $v !== ''),
            'personales' => array_filter(['DNI' => self::v($d, 'dni'), 'Nacimiento' => preg_match('/^\d{4}-\d{2}-\d{2}$/', $nac) ? implode('/', array_reverse(explode('-', $nac))) : $nac,
                'Estado civil' => self::v($d, 'ecCv')], fn ($v) => $v !== ''),
            'hab' => self::lineas($d['hab'] ?? ''), 'idiomas' => array_values(array_filter(array_map('trim', preg_split('/[,\n;]/', (string) ($d['idiomas'] ?? ''))))),
            'edu' => self::lista($d['edu'] ?? []), 'exp' => self::lista($d['exp'] ?? []), 'cert' => self::lista($d['cert'] ?? []),
            'perfil' => array_values(array_filter(array_map('trim', preg_split('/\n+/', (string) ($d['perfil'] ?? ''))))), 'refs' => self::lineas($d['refs'] ?? ''),
        ];
    }

    /** íconos de contacto como imagen SVG (sirven en la pantalla y en el PDF) */
    public static function icono(string $k, string $c): string
    {
        $in = [
            'tel' => '<rect x="8.2" y="4.5" width="7.6" height="15" rx="1.6" fill="#fff"/><rect x="10.8" y="16.6" width="2.4" height="1.2" rx=".6" fill="'.$c.'"/>',
            'mail' => '<rect x="5.5" y="7.5" width="13" height="9" rx="1" fill="none" stroke="#fff" stroke-width="1.6"/><path d="M6 8.2l6 4.6 6-4.6" fill="none" stroke="#fff" stroke-width="1.6"/>',
            'web' => '<circle cx="12" cy="12" r="6.3" fill="none" stroke="#fff" stroke-width="1.5"/><ellipse cx="12" cy="12" rx="2.7" ry="6.3" fill="none" stroke="#fff" stroke-width="1.3"/><path d="M5.8 12h12.4" stroke="#fff" stroke-width="1.3"/>',
            'pin' => '<path d="M12 19s-5-5.2-5-9a5 5 0 0 1 10 0c0 3.8-5 9-5 9z" fill="#fff"/><circle cx="12" cy="10" r="1.9" fill="'.$c.'"/>',
        ][$k] ?? '';

        return 'data:image/svg+xml;base64,'.base64_encode('<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><circle cx="12" cy="12" r="12" fill="'.$c.'"/>'.$in.'</svg>');
    }

    /** la foto recortada en círculo o con esquinas redondeadas (cvmFoto), como PNG */
    public static function fotoRecortada(?string $src, string $forma): ?string
    {
        if (! $src || ! function_exists('imagecreatefromstring')) {
            return $src;
        }
        $img = @imagecreatefromstring(base64_decode(substr($src, strpos($src, ',') + 1)));
        if (! $img) {
            return null;
        }
        $S = 400;
        $w = imagesx($img);
        $h = imagesy($img);
        $k = max($S / $w, $S / $h);
        $cuadro = imagecreatetruecolor($S, $S);
        imagecopyresampled($cuadro, $img, (int) (($S - $w * $k) / 2), (int) (($S - $h * $k) * .3), 0, 0, (int) ceil($w * $k), (int) ceil($h * $k), $w, $h);
        $out = imagecreatetruecolor($S, $S);
        imagesavealpha($out, true);
        imagealphablending($out, false);
        imagefill($out, 0, 0, imagecolorallocatealpha($out, 0, 0, 0, 127));
        $r = $forma === 'cuadrado' ? 34 : $S / 2;
        for ($y = 0; $y < $S; $y++) {
            for ($x = 0; $x < $S; $x++) {
                $dx = max($r - $x - .5, $x + .5 - ($S - $r), 0);
                $dy = max($r - $y - .5, $y + .5 - ($S - $r), 0);
                if ($dx * $dx + $dy * $dy <= $r * $r) {
                    imagesetpixel($out, $x, $y, imagecolorat($cuadro, $x, $y));
                }
            }
        }
        ob_start();
        imagepng($out);

        return 'data:image/png;base64,'.base64_encode((string) ob_get_clean());
    }
}
