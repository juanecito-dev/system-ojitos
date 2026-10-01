<?php

namespace App\Redaccion;

/**
 * El texto de un documento, en líneas fáciles de escribir y corregir (también para Claude):
 *   [title] CONTRATO DE …          título centrado
 *   [p] Conste por el presente…     párrafo justificado (también una línea sin marca)
 *   [left] / [right] / [center]     alineados
 *   [li] a) …                       ítem de lista
 *   [sum] **SUMILLA:** …            sumilla (a la derecha, como en las solicitudes)
 *   [firma] NOMBRE | DNI | rol      firmas (las seguidas van juntas); «| sin huella» al final quita la huella
 * **texto** va en negrita. Una línea en blanco separa los párrafos sin marca.
 */
class Formato
{
    public const TIPOS = ['title', 'p', 'left', 'right', 'center', 'li', 'sum', 'firma'];

    /** texto → bloques: [k => title, t] | [k => p…, s => [[texto, negrita]]] | [k => sign, f => [[n, d, r, nh]]] */
    public static function bloques(string $texto): array
    {
        $out = [];
        $cur = null;
        $cerrar = function () use (&$cur, &$out) {
            if ($cur && trim($cur['raw']) !== '') {
                $out[] = $cur['k'] === 'title' ? ['k' => 'title', 't' => trim($cur['raw'])] : ['k' => $cur['k'], 's' => self::negritas(trim($cur['raw']))];
            }
            $cur = null;
        };
        foreach (preg_split("/\r?\n/", $texto) as $l) {
            if (preg_match('/^\s*\[(\w+)\]\s?(.*)$/u', $l, $m) && in_array($m[1], self::TIPOS, true)) {
                $cerrar();
                if ($m[1] === 'firma') {
                    $p = array_map('trim', explode('|', $m[2]));
                    $f = ['n' => $p[0] ?? '', 'd' => $p[1] ?? '', 'r' => $p[2] ?? '', 'nh' => ($p[3] ?? '') === 'sin huella'];
                    $ult = count($out) - 1;
                    if ($ult >= 0 && $out[$ult]['k'] === 'sign') {
                        $out[$ult]['f'][] = $f;
                    } else {
                        $out[] = ['k' => 'sign', 'f' => [$f]];
                    }

                    continue;
                }
                $cur = ['k' => $m[1], 'raw' => $m[2]];

                continue;
            }
            if (trim($l) === '') {
                $cerrar();

                continue;
            }
            if ($cur) {
                $cur['raw'] .= ' '.trim($l);
            } else {
                $cur = ['k' => 'p', 'raw' => trim($l)];
            }
        }
        $cerrar();

        return $out;
    }

    /** «un **texto** así» → [[un , false], [texto, true], [ así, false]] */
    public static function negritas(string $s): array
    {
        $out = [];
        foreach (explode('**', $s) as $i => $t) {
            if ($t !== '') {
                $out[] = [$t, $i % 2 === 1];
            }
        }

        return $out;
    }

    /** bloques → texto */
    public static function texto(array $bloques): string
    {
        $L = [];
        foreach ($bloques as $b) {
            if ($b['k'] === 'title') {
                $L[] = '[title] '.$b['t'];
            } elseif ($b['k'] === 'sign') {
                foreach ($b['f'] as $f) {
                    $L[] = Ayudas::firmaTxt($f['n'], $f['d'], $f['r'], empty($f['nh']));
                }
            } else {
                $L[] = '['.$b['k'].'] '.implode('', array_map(fn ($s) => $s[1] ? '**'.$s[0].'**' : $s[0], $b['s']));
            }
        }

        return implode("\n", $L);
    }

    /** cuántos datos faltan */
    public static function faltan(string $texto): int
    {
        return substr_count($texto, Ayudas::FALTA);
    }

    /** el HTML guardado por el sistema anterior (<h1 data-k>, <p data-k>, firmas en data-f) → texto */
    public static function desdeHtml(?string $html): string
    {
        if (! trim((string) $html)) {
            return '';
        }
        $dom = new \DOMDocument;
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="utf-8"?><div id="r">'.$html.'</div>', LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        $xp = new \DOMXPath($dom);
        $out = [];
        foreach ($xp->query('//*[@data-k]') as $n) {
            $k = $n->getAttribute('data-k');
            if ($k === 'title') {
                $out[] = ['k' => 'title', 't' => trim(preg_replace('/\s+/u', ' ', $n->textContent))];
            } elseif ($k === 'sign') {
                $f = json_decode($n->getAttribute('data-f'), true);
                if (is_array($f)) {
                    $out[] = ['k' => 'sign', 'f' => array_map(fn ($x) => ['n' => (string) ($x['n'] ?? ''), 'd' => (string) ($x['d'] ?? ''), 'r' => (string) ($x['r'] ?? ''), 'nh' => ! empty($x['nh'])], $f)];
                }
            } elseif (in_array($k, self::TIPOS, true)) {
                $segs = [];
                $walk = function ($node, $b) use (&$walk, &$segs) {
                    foreach ($node->childNodes as $ch) {
                        if ($ch->nodeType === XML_TEXT_NODE) {
                            $segs[] = [preg_replace('/\s+/u', ' ', $ch->nodeValue), $b];
                        } elseif ($ch->nodeName === 'br') {
                            $segs[] = [' ', $b];
                        } else {
                            $walk($ch, $b || in_array($ch->nodeName, ['b', 'strong'], true));
                        }
                    }
                };
                $walk($n, false);
                if (trim(implode('', array_column($segs, 0))) !== '') {
                    $out[] = ['k' => $k, 's' => $segs];
                }
            }
        }

        return self::texto($out);
    }
}
