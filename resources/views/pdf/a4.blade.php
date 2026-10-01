{{-- Hoja A4 con los datos del negocio arriba y un recuadro con el título (proforma, estado de cuenta…) --}}
<!DOCTYPE html>
<html lang="es"><head><meta charset="utf-8"><title>{{ $titulo }}</title>
<style>
    @page { margin: 16mm 16mm 22mm 16mm; }
    body { font-family: Helvetica, Arial, sans-serif; font-size: 9.5pt; color: #111; }
    .head { width: 100%; border-collapse: collapse; margin-bottom: 8mm; }
    .head td { vertical-align: top; }
    .biz { font-size: 18pt; font-weight: bold; letter-spacing: .02em; }
    .small { font-size: 8.6pt; color: #464646; line-height: 1.45; }
    .box { border: 1.4pt solid #111; border-radius: 6pt; padding: 6pt 10pt; text-align: center; width: 58mm; }
    .box .t { font-size: 13pt; font-weight: bold; }
    .cli { background: #f3f5f7; padding: 7pt 9pt; margin-bottom: 6mm; }
    .cli td { padding: 1.5pt 0; font-size: 9.5pt; }
    .cli td.k { font-weight: bold; width: 26mm; }
    table.tab { width: 100%; border-collapse: collapse; }
    table.tab th { background: #16191d; color: #fff; font-size: 9pt; padding: 5pt 7pt; text-align: left; }
    table.tab td { padding: 5pt 7pt; border-bottom: .5pt solid #dcdcdc; font-size: 9.3pt; vertical-align: top; }
    table.tab tr:nth-child(even) td { background: #f7f8fa; }
    .r { text-align: right !important; white-space: nowrap; }
    .c { text-align: center !important; }
    .tot { width: 72mm; margin: 4mm 0 0 auto; border-collapse: collapse; }
    .tot td { padding: 2.5pt 4pt; font-size: 10pt; }
    .tot .grand td { background: #16191d; color: #fff; font-weight: bold; font-size: 12pt; padding: 5pt 7pt; }
    .foot { position: fixed; bottom: -14mm; left: 0; right: 0; text-align: center; font-size: 8.5pt; color: #6e6e6e; }
    .cond { margin-top: 6mm; font-size: 9.3pt; }
    .cond div { margin: 2pt 0; }
</style></head>
<body>
<table class="head"><tr>
    <td>
        <div class="biz">{{ mb_strtoupper($neg->nombre) }}</div>
        <div class="small">{{ $neg->giro }}<br>{{ $neg->titular }}@if ($neg->ruc) · RUC {{ $neg->ruc }}@endif<br>{{ $neg->direccion }}{{ $neg->ciudad ? ', '.$neg->ciudad : '' }}@if ($neg->celular) · Cel. {{ $neg->celular }}@endif</div>
    </td>
    <td style="width:62mm;text-align:right"><div class="box" style="margin-left:auto">{!! $recuadro !!}</div></td>
</tr></table>
{!! $cuerpo !!}
<div class="foot">{{ $pie ?? 'Gracias por su preferencia.' }}</div>
</body></html>
