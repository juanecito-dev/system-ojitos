<?php

namespace App\Support;

/**
 * Listas fijas del sistema, copiadas de caja-rapida.html para que todo se llame igual.
 */
class Catalogos
{
    /** Módulos del menú: clave => [título, sección, descripción, ruta] */
    public const MODULOS = [
        'inicio' => ['Inicio', 'Operación', 'Resumen del día', 'inicio'],
        'vender' => ['Punto de venta', 'Operación', 'Cobrar copias, impresiones y útiles', 'vender'],
        'encargos' => ['Pedidos', 'Operación', 'Cotizaciones, encargos y entregas', 'pedidos'],
        'documentos' => ['Redacción', 'Operación', 'Contratos, solicitudes, cartas poder y más', 'redaccion'],
        'caja' => ['Caja y gastos', 'Operación', 'Apertura, gastos y cuadre de caja', 'caja'],
        'clientes' => ['Clientes y fiados', 'Operación', 'Quién te debe y cuánto', 'clientes'],
        'ventas' => ['Ventas', 'Control', 'Historial, tickets y anulaciones', 'ventas'],
        'comprobantes' => ['Facturación', 'Control', 'Boletas, facturas y registro de ventas', 'facturacion'],
        'inventario' => ['Inventario', 'Control', 'Stock, entradas y por reponer', 'inventario'],
        'compras' => ['Compras y proveedores', 'Control', 'Facturas, deudas y vencimientos', 'compras'],
        'reportes' => ['Reportes', 'Control', 'Ventas, gastos y ganancia', 'reportes'],
        'usuarios' => ['Usuarios y roles', 'Sistema', 'Quién entra y qué puede hacer', 'usuarios'],
        'ajustes' => ['Configuración', 'Sistema', 'Negocio, productos y precios', 'ajustes'],
    ];

    /** Módulos que el negocio puede apagar */
    public const MODULOS_OPCIONALES = ['encargos', 'documentos', 'caja', 'clientes', 'comprobantes', 'inventario', 'compras', 'reportes'];

    public const PERMISOS_MODULO = [
        'vender' => 'Punto de venta',
        'encargos' => 'Pedidos (cotizaciones y encargos)',
        'documentos' => 'Redacción de documentos',
        'caja' => 'Caja y gastos',
        'clientes' => 'Clientes y fiados',
        'ventas' => 'Ventas',
        'comprobantes' => 'Facturación (boletas y facturas)',
        'inventario' => 'Inventario',
        'compras' => 'Compras y proveedores',
        'reportes' => 'Reportes',
        'ajustes' => 'Configuración (productos, precios y datos)',
    ];

    public const PERMISOS_ACCION = [
        'descuentos' => 'Dar descuentos y cambiar precios al cobrar',
        'fiar' => 'Vender al fiado',
        'anular' => 'Anular ventas y comprobantes',
        'borrar' => 'Borrar movimientos de caja, fiados y pedidos',
        'gastos' => 'Registrar gastos, retiros e ingresos de caja',
        'verTodo' => 'Ver las ventas de los demás usuarios',
        'costos' => 'Ver costos y ganancias',
        'precios' => 'Cambiar productos y precios',
        'negocio' => 'Cambiar datos del negocio, ticket, cobros y facturación',
        'datos' => 'Copias de seguridad y restaurar datos',
    ];

    /** Permisos del rol «Vendedor» que se crea con cada negocio nuevo */
    public const PERMISOS_VENDEDOR = ['vender', 'encargos', 'documentos', 'caja', 'clientes', 'ventas', 'comprobantes',
        'inventario', 'descuentos', 'fiar', 'gastos', 'verTodo'];

    /** Tipo de negocio => [nombre, módulos que se apagan] */
    public const RUBROS = [
        'imprenta' => ['Imprenta, copias e impresiones', []],
        'libreria' => ['Librería y útiles', ['documentos']],
        'bodega' => ['Bodega o minimarket', ['documentos', 'encargos']],
        'servicios' => ['Servicios o taller', ['documentos', 'inventario']],
        'otro' => ['Otro negocio', []],
    ];

    public const METODOS = [
        'efectivo' => 'Efectivo',
        'yape' => 'Yape',
        'plin' => 'Plin',
        'tarjeta' => 'Tarjeta',
        'transferencia' => 'Transferencia',
        'otro' => 'Otro',
    ];

    /** Apagados si el negocio nunca configuró sus métodos de pago */
    public const METODOS_APAGADOS_INICIO = ['tarjeta', 'transferencia'];

    public const CONCEPTOS = [
        'gasto' => ['Papel', 'Tinta o tóner', 'Útiles para vender', 'Luz', 'Internet', 'Alquiler', 'Mantenimiento', 'Pasajes', 'Comida', 'Otro'],
        'retiro' => ['Para el banco', 'Uso personal', 'Pago a proveedor', 'Otro'],
        'ingreso' => ['Sencillo agregado', 'Préstamo', 'Otro'],
    ];

    public const TIPOS_ACTIVIDAD = [
        'entrada' => 'Ingreso', 'precio' => 'Precios', 'producto' => 'Productos', 'config' => 'Configuración',
        'usuario' => 'Usuarios', 'rol' => 'Roles', 'datos' => 'Datos', 'autoriza' => 'Autorización',
        'bloqueo' => 'PIN bloqueado', 'anula' => 'Anulación', 'caja' => 'Caja', 'pedido' => 'Pedidos', 'factura' => 'Facturación', 'stock' => 'Inventario', 'compra' => 'Compras',
    ];

    /** etapa del pedido => [color de la etiqueta, nombre] */
    public const ETAPAS_PEDIDO = [
        'cotizado' => ['p', 'Cotización'], 'vencido' => ['r', 'Cotización vencida'], 'proceso' => ['m', 'En proceso'],
        'listo' => ['b', 'Listo'], 'entregado' => ['s', 'Entregado'], 'rechazado' => ['s', 'No aceptó'],
    ];

    public const TIPOS_COMPROBANTE = ['03' => 'Boleta', '01' => 'Factura', '07' => 'Nota de crédito', '08' => 'Nota de débito'];

    public const TIPOS_DOC = ['1' => 'DNI', '6' => 'RUC', '4' => 'Carnet de extranjería', '7' => 'Pasaporte', '0' => 'Sin documento'];

    public const REGIMENES = [
        'nrus' => ['Nuevo RUS', false], 'rer' => ['Régimen Especial (RER)', true],
        'rmt' => ['Régimen MYPE Tributario (RMT)', true], 'general' => ['Régimen General', true],
    ];

    /** Ventas de hasta S/ 5 van a la boleta de cierre del día */
    public const LIMITE_CIERRE = 500;

    /** Boletas de más de S/ 700 deben llevar el documento del comprador */
    public const MAX_SIN_DOC = 70000;

    /** Aviso de stock bajo si no se definió un mínimo */
    public const STOCK_BAJO = 3;

    public static function permisos(): array
    {
        return self::PERMISOS_MODULO + self::PERMISOS_ACCION;
    }
}
