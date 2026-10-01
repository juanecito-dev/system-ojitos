# Propuesta de la Etapa 3: Inventario, Compras, Reportes y contadores de las máquinas

Estado: **aprobada por Alex el 29/09/2026** (todo, en el orden: contadores, inventario, compras, reportes).

Avance: 1. Contadores de las máquinas ✅. 2. Inventario ✅. 3. Compras y proveedores ✅. 4. Reportes ✅. **Etapa 3 terminada.**

Todo lo que hacen hoy estos módulos en `caja-rapida.html` se mantiene igual. Abajo están las mejoras propuestas.

## 1. Inventario

**Se mantiene:** los filtros (Con stock, Por reponer, Sin control, Insumos de servicios); las cifras de arriba (productos con control, por reponer, mercadería a precio de costo, pérdidas del mes); «+ Entrada» y «Contar» con el motivo de la diferencia; los insumos (papel, tóner, anillos) que bajan solos con cada venta, con «gasta» o «1 rinde»; cuánto te deja cada servicio después de sus insumos; «te alcanza ~N días»; la ficha del producto con su mínimo, costo, unidad de compra y kárdex; y la toma de inventario con el lector de códigos.

**Mejoras:**
1. **Kárdex completo:** los movimientos de cualquier fecha, no solo de los últimos 30 días.
2. **La toma de inventario se guarda en el servidor:** si empiezas a contar en un equipo, puedes terminar en otro (hoy se guarda solo en el equipo donde empezaste).
3. **Inventario valorizado en PDF**, para el contador o el cierre del año.

## 2. Compras y proveedores

**Se mantiene:** Por pagar, Pagadas, Por reponer y Proveedores; las cifras (lo que debes, vencido, vence en 7 días, compras del mes); registrar una compra con su detalle, que suma al stock y recalcula el costo promedio; al contado o a crédito con fecha de vencimiento; los pagos al proveedor (en efectivo salen de la caja); «Por reponer» agrupado por proveedor, con el pedido por WhatsApp y «Llegó: registrar compra»; el último costo y el proveedor más barato; y el Registro de compras del mes para el contador.

**Mejoras:**
1. **Sin archivo por mes:** todas las compras quedan guardadas y se pueden buscar (esto resuelve el pendiente «archivar las compras por mes»).
2. **Aviso en Inicio** cuando una factura de proveedor vence en los próximos 3 días.
3. **Historial de precios por producto:** cuánto te costó en cada compra y con qué proveedor.

## 3. Reportes

**Se mantiene:** los periodos (Hoy, 7 días, 30 días, Este mes, Mes pasado, Este año, Elegir fechas) comparados con el periodo anterior; la ganancia real (vendido − costo de lo vendido − gastos − pérdidas); el dinero que entró y salió; la proyección del mes y la meta diaria; los gráficos por día y mes a mes; por grupo; lo que más te deja; cuándo vendes más (mapa por día y hora); por vendedor (ventas, descuentos, anuladas, cuadre de caja); ventas anuladas; documentos redactados; clientes y pedidos; en qué gastas y cómo te pagan; el reporte en PDF y las ventas en Excel.

**Mejoras:**
1. **Más rápido y exacto:** el servidor calcula todo directamente de las ventas, sin los «resúmenes diarios» que usaba el sistema anterior para no pasarse del límite de la plataforma.
2. **Comparar con el mismo mes del año pasado**, además del periodo anterior.
3. **Resumen del día por WhatsApp:** un botón en Caja para mandarte el resumen al cerrar (vendido, gastos, cuadre, pendientes de SUNAT).

## 4. Contadores de las máquinas

**Se mantiene:** en Configuración › Máquinas agregas cada fotocopiadora o impresora con sus contadores (B/N, color o total) y eliges qué productos gastan contador y cuántas hojas cuenta cada uno (A3 = 2). En Caja se anota la lectura al cerrar, las copias de prueba o malogradas, y el cuadre muestra las copias sin cobrar y cuánto dinero son. Solo el administrador ve el cuadre.

**Mejoras:**
1. **Historial de lecturas por máquina** con las copias de cada día, para ver cuánto trabaja cada una.
2. **Las copias sin cobrar de la semana** aparecen en Inicio para el administrador.

## Para confirmar

- El resumen por WhatsApp se abre en tu WhatsApp para que lo envíes tú (no se manda solo).
