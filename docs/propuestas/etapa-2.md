# Propuesta de la Etapa 2: Clientes y fiados, Pedidos y Facturación

Estado: **esperando la aprobación de Alex**.

Todo lo que hacen hoy estos módulos en `caja-rapida.html` se mantiene igual. Abajo están las mejoras propuestas.

## 1. Clientes y fiados

**Se mantiene:** la lista con los filtros (Me deben, Más frecuentes, No vuelven hace 30 días o más, Todos), la ficha (visitas, cuánto gastó, última vez), registrar pagos (que entran a la caja), el estado de cuenta en PDF, el recordatorio por WhatsApp, anotar una deuda anterior, editar, unir duplicados, y ver sus compras y pedidos.

**Mejoras:**
1. **Límite de fiado por cliente** (opcional). Si alguien quiere fiar por encima de ese límite, se pide el PIN de un administrador.
2. **Deudas antiguas en rojo:** se muestra desde cuándo debe cada cliente y se marcan las deudas de más de 30 días.
3. **Historial completo de compras**, no solo de los últimos 90 días.

## 2. Pedidos

**Se mantiene:** cotización → en proceso → listo → entregado; adelantos y pagos a cuenta (cada uno entra como venta); la proforma en PDF A4 con el monto en letras; la orden de trabajo para la ticketera; los mensajes de WhatsApp; las listas de útiles por colegio; duplicar y renovar cotizaciones; el aviso de «listo y no lo recogen»; el stock que sale al entregar. El saldo se sigue cobrando en el Punto de venta.

**Mejoras:**
1. **Sin archivo mensual:** se puede buscar cualquier pedido de cualquier fecha.
2. **Vista «Entregas»:** los pedidos agrupados por día (atrasados, hoy, mañana, esta semana).
3. **Historial completo** de cada pedido (hoy solo guarda los últimos 12 cambios).

## 3. Facturación

**Se mantiene:** Por emitir (la boleta de cierre del día con las ventas de hasta S/ 5, y las ventas de más de S/ 5 o en las que el cliente pidió boleta), el aviso del plazo, la guía para emitir una por una, los botones de copiar (descripción, importe, DNI), Emitidos (poner número, anular, nota de crédito) y el Registro de ventas del mes para el contador.

**Mejoras:**
1. **Corregir un número mal anotado** (con el PIN de un administrador). Esto sirve para arreglar las boletas EB01-123 a 129 que hoy no coinciden con SUNAT.
2. **Aviso de números saltados:** por ejemplo, si están la EB01-440 y la EB01-442 pero falta la 441.
3. **Guardar el detalle de productos de cada comprobante.** Así se prepara el envío automático a SUNAT de la etapa 5.

## Para confirmar

- El plazo que se muestra sigue siendo de 7 días hasta que el contador confirme el plazo real.
