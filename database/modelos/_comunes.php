<?php

/*
 * Cláusulas que llevan todos los contratos (COMUNES del sistema anterior).
 * n = desde qué nivel entra (1 simple, 2 intermedio, 3 avanzado); opt = opcional, se marca a mano.
 * Un modelo puede quitar algunas con 'sin_comunes' => ['decl'].
 */
return [
    ['id' => 'decl', 'h' => 'DECLARACIONES DE LAS PARTES', 'n' => 3, 't' => 'Las partes declaran tener plena capacidad para contratar, que los datos consignados en el presente documento son verdaderos y que celebran este contrato de manera libre, voluntaria y de buena fe, sin que medie error, dolo, violencia ni intimidación que pudiera invalidarlo.'],
    ['id' => 'fuerza', 'h' => 'CASO FORTUITO O FUERZA MAYOR', 'n' => 3, 't' => 'Ninguna de las partes será responsable por el incumplimiento de sus obligaciones cuando este se deba a caso fortuito o fuerza mayor, entendido como un evento extraordinario, imprevisible e irresistible, conforme al artículo 1315 del Código Civil. La parte afectada deberá comunicarlo por escrito a la otra en el más breve plazo, y cumplirá sus obligaciones apenas cese el impedimento.'],
    ['id' => 'controv', 'h' => 'SOLUCIÓN DE CONTROVERSIAS', 'n' => 3, 't' => 'Cualquier discrepancia sobre la interpretación o el cumplimiento del presente contrato será resuelta, en primer lugar, mediante trato directo entre las partes, dentro de los quince (15) días siguientes a la comunicación escrita de una de ellas. De no llegar a un acuerdo, podrán acudir a un centro de conciliación extrajudicial antes de recurrir a la vía judicial señalada en el presente contrato.'],
    ['id' => 'integ', 'h' => 'INTEGRIDAD Y MODIFICACIONES', 'n' => 3, 't' => 'El presente contrato contiene la totalidad de los acuerdos de las partes sobre su objeto y deja sin efecto cualquier acuerdo verbal o escrito anterior. Toda modificación deberá constar por escrito y ser firmada por ambas partes. Si alguna cláusula fuera declarada inválida, las demás mantendrán su plena vigencia.'],
    ['id' => 'legal', 'h' => 'LEGALIZACIÓN DE FIRMAS', 'opt' => true, 't' => 'Las partes acuerdan legalizar sus firmas en el presente contrato ante notario público, a fin de otorgarle fecha cierta, siendo los gastos de dicha legalización asumidos en partes iguales, salvo pacto distinto.'],
    ['id' => 'testigos', 'h' => 'TESTIGOS', 'opt' => true, 't' => 'Suscriben también el presente contrato, en calidad de testigos, las personas que firman al pie, quienes declaran haber presenciado su celebración y la manifestación libre de voluntad de las partes.'],
    ['id' => 'juris', 'h' => 'DOMICILIO Y JURISDICCIÓN', 'n' => 1, 't' => 'Para todos los efectos del presente contrato, las partes señalan como sus domicilios los indicados en la introducción, donde se tendrán por válidamente realizadas todas las comunicaciones. Cualquier cambio de domicilio deberá ser comunicado por escrito a la otra parte. En caso de controversia, las partes se someten a la competencia de los jueces y tribunales de {{ $h->x(\'ciudad\') }}. En todo lo no previsto, se aplican supletoriamente las disposiciones del Código Civil.'],
];
