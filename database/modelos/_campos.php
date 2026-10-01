<?php

/*
 * Grupos de campos que se repiten en varios modelos. En un modelo se usan con ['grupo' => 'precio'].
 * Tipos de campo: texto (por defecto), area, num, money, date, select (opts), check.
 * «si» muestra el campo solo cuando se cumple la condición (pago=partes, forma!=unica, cl:fiador…).
 * «frases» son textos formales listos para tocar; «def» es el valor inicial (@hoy, @ciudad).
 */
return [
    'ubic' => [
        ['id' => 'dist', 'label' => 'Distrito', 'def' => 'Rupa Rupa'],
        ['id' => 'prov', 'label' => 'Provincia', 'def' => 'Leoncio Prado'],
        ['id' => 'dep', 'label' => 'Departamento', 'def' => 'Huánuco'],
    ],
    'precio' => [
        ['id' => 'precio', 'label' => 'Precio total (S/)', 'type' => 'money', 'req' => true],
        ['id' => 'pago', 'label' => 'Forma de pago', 'type' => 'select', 'opts' => ['contado' => 'Al contado, a la firma', 'partes' => 'Adelanto y saldo'], 'def' => 'contado'],
        ['id' => 'adelanto', 'label' => 'Adelanto (S/)', 'type' => 'money', 'si' => 'pago=partes'],
        ['id' => 'saldoFecha', 'label' => 'Fecha límite para pagar el saldo', 'type' => 'date', 'si' => 'pago=partes'],
    ],
    'gastos' => [
        ['id' => 'gastos', 'label' => 'Gastos notariales y registrales', 'type' => 'select', 'def' => 'EL COMPRADOR',
            'opts' => ['EL COMPRADOR' => 'Los paga el comprador', 'ambas partes, en partes iguales' => 'Mitad y mitad', 'EL VENDEDOR' => 'Los paga el vendedor']],
    ],
    'pago_cuotas' => [
        ['id' => 'forma', 'label' => 'Cómo se paga', 'type' => 'select', 'opts' => ['unica' => 'Todo en una fecha', 'cuotas' => 'En cuotas'], 'def' => 'cuotas'],
        ['id' => 'fechaUnica', 'label' => 'Fecha de pago', 'type' => 'date', 'req' => true, 'si' => 'forma=unica'],
        ['id' => 'ncuotas', 'label' => 'Número de cuotas', 'type' => 'num', 'def' => '3', 'req' => true, 'si' => 'forma!=unica'],
        ['id' => 'periodo', 'label' => 'Cada cuánto', 'type' => 'select', 'opts' => ['mensual' => 'Mensual', 'quincenal' => 'Quincenal (cada 15 días)', 'semanal' => 'Semanal'], 'def' => 'mensual', 'si' => 'forma!=unica'],
        ['id' => 'primera', 'label' => 'Fecha de la primera cuota', 'type' => 'date', 'req' => true, 'si' => 'forma!=unica'],
        ['id' => 'lugarPago', 'label' => 'Dónde se paga', 'type' => 'select', 'def' => 'efectivo',
            'opts' => ['efectivo' => 'En efectivo, en el domicilio de quien cobra', 'deposito' => 'Depósito o transferencia a una cuenta', 'yape' => 'Yape o Plin']],
        ['id' => 'cuenta', 'label' => 'N° de cuenta o celular (opcional)', 'si' => 'lugarPago!=efectivo', 'ph' => 'Ej.: BCP 480-12345678-0-12'],
    ],
];
