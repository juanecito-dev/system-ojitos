# Traspaso: migración de "Ojitos Caja" a Laravel + Livewire

Documento para continuar el trabajo en una conversación nueva de Claude Code. El sistema anterior completo está en `docs/legado/caja-rapida.html` y es la fuente de verdad de todas las reglas del negocio.

## 0. Estado actual (29/09/2026)

**Etapa 1 terminada** en la rama `claude/laravel-migration-woke0o`:

- Proyecto Laravel 13 + Livewire 3.8, sin Node (CSS y JS en `public/`), probado en PHP 8.3 y 8.4. `composer.json` fija la plataforma en PHP 8.3 (`config.platform`) para que `composer update` nunca traiga paquetes que exijan 8.4.
- Todas las tablas del modelo de datos (también las de pedidos, compras, comprobantes y redacción, que usarán las próximas etapas).
- Varios negocios desde el inicio: `negocio_id` en todo, con un filtro automático (`PerteneceANegocio`) y un negocio actual (`NegocioActual`).
- Entrada con rol, usuario y PIN, bloqueo después de 5 intentos (vale en todos los equipos), autorización con el PIN de un administrador (`ConAutorizacion`) y registro de actividad (`Bitacora`).
- Acepta los PIN del sistema anterior (PBKDF2 `p2:` y SHA-256) y los cambia al formato de Laravel la primera vez que el usuario entra.
- Importador de la copia v3: `php artisan ojitos:importar archivo.json --negocio=ojitos`, en la pantalla «Primer uso» o en Configuración › Datos. Se puede repetir sin duplicar nada.
- Módulos: Inicio, Punto de venta, Ventas, Caja y gastos (turnos por usuario), Usuarios y roles, Configuración (Negocio, Ticket, Cobros, Facturación, Productos y precios, Seguridad, Datos).
- Ticket (nota de venta) en PDF de 80 o 58 mm, del alto justo, y en imagen PNG para WhatsApp.
- 45 pruebas automáticas con Pest (`php artisan test`).

**Etapa 2 terminada** (propuesta aprobada en `docs/propuestas/etapa-2.md`):

- **Clientes y fiados** (`app/Services/Clientes.php`, `app/Livewire/Clientes.php`): lo que debe cada uno y desde cuándo, pagos (entran a la caja), estado de cuenta en PDF, recordatorio por WhatsApp, deuda anterior (permiso `fiar`), borrar movimientos (permiso `borrar`), unir duplicados, historial de compras. Nuevo: **límite de fiado** por cliente; pasarlo pide el PIN de un administrador.
- **Pedidos** (`app/Services/Pedidos.php`, `app/Livewire/Pedidos.php`): cotización → en proceso → listo → entregado, validez de la cotización, adelantos y pagos a cuenta (se guardan como ventas), entrega cobrando el saldo en el Punto de venta (`/vender?pedido=uid`), venta directa de una cotización, historial de cada pedido, responsable, entregas por día, listas de útiles, proforma A4 y orden de trabajo en PDF, mensajes de WhatsApp. Anular la venta de un pedido deshace el pago o la entrega.
- **Facturación** (`app/Services/Comprobantes.php`, `app/Livewire/Facturacion.php`): por emitir (boleta de cierre del día y ventas que necesitan la suya, con el plazo de 7 días), guía para registrarlos uno por uno, emitidos (poner o corregir el número con PIN de administrador, anular, notas de crédito con su propio correlativo), registro de ventas del mes en Excel para el contador y aviso de números saltados. Cada comprobante guarda su detalle de productos (`comprobante_items`), listo para la etapa 5 (SUNAT).
- 80 pruebas automáticas. Capturas 14 a 21 en `docs/capturas`.

**Etapa 3 terminada** (propuesta aprobada en `docs/propuestas/etapa-3.md`):

- **Contadores de las máquinas** ✅ (`app/Services/Contadores.php`, `app/Livewire/CajaContadores.php` dentro de Caja, `app/Livewire/AjustesMaquinas.php` en Configuración › Máquinas): lectura al cerrar (solo el día de hoy; no acepta una menor que la anterior y pide confirmar más de 20 000 copias), copias de prueba o malogradas, cuadre por grupo (B/N, color o total) con las copias sin cobrar y su valor, solo para el administrador. Nuevo: historial de lecturas por máquina (60 días) y «Copias sin cobrar (7 días)» en Inicio para el administrador. Los productos que cuentan se guardan en el ajuste `maquinas.prods` (uid => {g, f}); la lectura anterior sale de `lecturas_contador` o, si es más nueva, de `maquinas.ult` de la copia. Capturas 22 a 26.
- **Inventario** ✅ (`app/Services/Inventario.php`, `app/Livewire/Inventario.php`, `app/Livewire/InventarioToma.php`): filtros Con stock, Por reponer, Sin control e Insumos de servicios; cifras de arriba (el dinero solo con el permiso `costos`); entrada con costo promedio, contar con motivo de la diferencia, nuevo insumo, qué gasta cada servicio («gasta» o «1 rinde»), «te alcanza ~N días» (promedio de 14 días), ficha con ajustes (mínimo, costo, unidad de compra en `extra.uc`). Mejoras: kárdex de cualquier fecha, toma de inventario guardada en el servidor (tabla `toma_conteos`, el lector suma 1 aunque el cursor no esté en el buscador) e inventario valorizado en PDF. Los costos ahora aceptan fracciones de céntimo (`decimal(14,4)` en productos, venta_items y stock_movimientos; `Dinero::aCosto()`, `nCosto()`, `sCosto()`), y `producto_insumos.cantidad` tiene 10 decimales para que «1 rinde 7000» vuelva a mostrar 7000. Capturas 27 a 31.
- **Compras y proveedores** ✅ (`app/Services/Compras.php`, `app/Livewire/Compras.php`): por pagar, pagadas, por reponer y proveedores; cifras (debes, vencido, vence en 7 días, compras del mes); compra con líneas por presentación (unidad, la de compra del producto u «otra», que se guarda en `extra.uc`), a crédito con vencimiento o al contado; suma al stock y recalcula el costo promedio (guarda `costo_antes`/`costo_nuevo` para deshacer); pagos, y en efectivo salen de la caja como retiro «Pago a proveedor» (borrarlo en Caja devuelve la deuda); eliminar una compra (permiso `borrar`) deshace stock, costo y caja; por reponer agrupado por el último proveedor con pedido por WhatsApp y «Llegó: registrar compra»; registro de compras del mes en Excel. Mejoras: sin archivo por mes (buscador y selector de mes), aviso en Inicio y en el menú de las facturas vencidas o que vencen en 3 días, e historial de precios por producto en la ficha del Inventario (el más barato en verde). `Stock::ahoraPara()` anota los movimientos siempre después del último conteo. Capturas 32 a 37.
- **Reportes** ✅ (`app/Services/Reportes.php`, `app/Livewire/Reportes.php`): periodos Hoy, 7 días, 30 días, Este mes, Mes pasado, Este año y Elegir fechas; resultado (vendido, costo de lo vendido con su cobertura, gastos, pérdidas, ganancia, ticket promedio), proyección del mes y meta, dinero que entró y salió, gráfico por día o mes a mes (periodos de más de 92 días), por grupo, lo que más te deja, mapa por día y hora, por vendedor (ventas, descuentos, anuladas, cuadre), anuladas, documentos, clientes y pedidos, en qué gastas y cómo te pagan; reporte en PDF (`pdf/reporte.blade.php`) y ventas del periodo en Excel. Sin el permiso `costos` no se ve el costo, la ganancia, el margen ni el PDF. Mejoras: todo se calcula en el servidor con consultas agrupadas (sin los resúmenes diarios de antes); comparar con el periodo anterior o con las mismas fechas del año pasado; en Caja, el administrador puede mandarse el resumen del día por WhatsApp (`Reportes::resumenDia()`). Capturas 38 y 39 (PDF de ejemplo).
- Arreglo para Windows: `artisan serve` ahora pasa `TEMP`/`TMP` al servidor (subir archivos fallaba) y `iniciar-windows.bat` escucha en toda la red y muestra la dirección para el celular.
- 116 pruebas automáticas.

**Quedó para después o pendiente de decidir:**

- Del formato antiguo `encargos/lista` solo se importan los que ya pasaron a `pedidos`. Si una copia trae encargos sin migrar, el importador avisa.
- «Copia de seguridad» y «Exportar a Excel» de productos y clientes: ahora la copia se hace en el servidor (ver README). Se pueden agregar exportaciones más adelante.

**Etapa 4 en curso: Redacción** (propuesta aprobada en `docs/propuestas/etapa-4.md`: sin IA dentro del sistema; lo que antes hacía la IA lo hacen frases listas, y los documentos raros se «encargan a Claude»):

- **Parte 1 ✅ Modelos como datos y formulario.** Los modelos son archivos PHP en `database/modelos/{paquete}/{id}.php` (campos, cláusulas y una plantilla Blade que escribe el texto) y se copian a la tabla `modelos_redaccion` con `php artisan ojitos:modelos` (también solos con `Catalogo::alDia()`). El texto de cada documento se guarda en `documentos.texto` con un formato por líneas fácil de corregir, también para Claude: `[title]`, `[p]`, `[left]`, `[right]`, `[center]`, `[li]`, `[sum]`, `[firma] NOMBRE | DNI | rol [| sin huella]` y `**negrita**` (`app/Redaccion/Formato.php`). Motor en `app/Redaccion/Motor.php` y ayudas (montos y fechas en letras, don/doña, cónyuge, cronograma) en `Ayudas.php`. Los documentos del sistema anterior se convierten desde su HTML la primera vez que se abren.
- **Parte 2 ✅ Vista del documento, PDF, Word y cobro.** Se corrige tocando la hoja (`hojaEditable()` en `public/js/ojitos.js` vuelve a escribir el formato por líneas y lo guarda con `Redaccion::corregir()`; volver a generar con los datos avisa que se pierden las correcciones). PDF A4 con dompdf (`pdf/redaccion.blade.php`: Times 11.5, márgenes 25/25/25/30 mm, firmas con huella y el último párrafo junto a las firmas) y Word (`redaccion/word.blade.php`, HTML que Word abre como .doc). Las páginas se cuentan con dompdf y se guardan en `documentos.paginas` hasta que cambie el texto. Cobrar: por página (`s_contrato`) o por documento (`s_solicitud`), y los ejemplares adicionales como `bn_a4`; pasa a la caja con `/vender?documento=uid&ej=N`, se suma a lo que ya había y la línea guarda `venta_items.documento_uid`. Al vender, el documento queda «cobrado» (con `venta_uid`); al anular esa venta vuelve a «sin cobrar». «Marcar entregado». Los documentos también salen en la ficha del cliente. Capturas 40 a 43.
- **Parte 3 ✅ Currículum en 4 diseños** (simple, con foto, Harvard y moderno; ids `cv_simple`, `cv_medio`, `cv_harvard`, `cv_moderno` como en el sistema anterior). Los modelos están en `database/modelos/cv/` (campos comunes en `_cv.inc`, con listas `t => lista` de estudios, experiencia y cursos, y foto). El currículum no tiene texto por líneas: se dibuja siempre con sus datos (`app/Redaccion/Curriculum.php` y `resources/views/redaccion/cv.blade.php`, la misma vista para la pantalla y el PDF; Word en `redaccion/cv-word.blade.php`). Por eso el diseño se cambia con un toque sin perder nada, y el moderno tiene color, letra, foto, columna y tamaño (`datos.data.tema`). En lugar de la IA, el perfil y las funciones traen frases de ejemplo según el tipo de trabajo (`frasesPor => rubro`). La foto se recorta en el navegador (300 × 378, `ojitosFoto()`) y en el moderno se recorta en círculo con GD. En el PDF del moderno la columna lateral es absoluta y su fondo fijo, para que la principal siga en la hoja siguiente. Se cobra con `s_cv` según el diseño. Capturas 44 a 47.
- **Falta:** parte 4 («Encargar a Claude» y «Guardar como modelo»).

**Auditoría del 29/09/2026** (`docs/auditoria-2026-09-29.md`): 14 errores corregidos (montos «1,500» en contratos, fechas imposibles, configuración y series sin revisar, cobros de más, pagos de pedidos «fiados», etapas de pedidos, entre otros), reglas de tecleo en todos los campos (`public/js/campos.js` con `data-solo`) y revisión en el servidor con `App\Support\Valida`. 154 pruebas automáticas.

## 1. El negocio y el dueño

- **Negocio:** Ojitos, copias e impresiones, Jr. San Alejandro 382, Tingo María (Huánuco, Perú).
- **Titular:** Alex Simon Santiago. RUC 10745784548. Régimen RER, exonerado de IGV (Amazonía).
- **Usuarios hoy:** Alex, administrador, usuario `alex`, usa su laptop. Jeremy, vendedor, usuario `jeremy`, usa la PC 1.
- **Idioma:** todo en español de Perú, en lenguaje sencillo y sin tecnicismos. Alex no es programador: explícale paso a paso, con capturas cuando tenga que hacer algo en una pantalla.
- **Forma de trabajar que le gusta:** primero analizar el módulo y proponer mejoras «pensando a futuro». Él aprueba. Después se hace en partes y se prueba todo.

## 2. Sistema anterior (lo que se migra)

- **Archivo:** `caja-rapida.html` (copia en `docs/legado/`), unas 6 700 líneas en HTML y JavaScript.
- **Publicación:** artifact https://claude.ai/artifact/KdnPqb9fwGvMh3Yq3a6Mb8, versión 43.
- **Datos:** en la base compartida del artifact (documentos y colecciones) y en el `localStorage` de cada equipo.

| Módulo | Qué hace | Estado |
|---|---|---|
| Inicio | Resumen del día, meta, avisos | ✅ Etapa 1 |
| Punto de venta | Buscador y lector de código de barras, favoritos, F2/F9, cambio de precio, descuentos con tope y autorización, métodos de pago, fiado, DNI para la boleta, ticket | ✅ Etapa 1 |
| Pedidos | Cotización → en proceso → listo → entregado, pagos parciales, orden de trabajo, listas de útiles | ✅ Etapa 2 |
| Redacción | Modelos, contratos por niveles, IA, CV en 4 diseños | Etapa 4: modelos, PDF, Word, cobro y CV ✅; faltan encargos |
| Caja y gastos | Turnos por usuario con cuadre, gastos, retiros, ingresos, contadores de máquinas | ✅ Etapa 1 (contadores ✅ etapa 3) |
| Clientes y fiados | Deudas, pagos, estado de cuenta, unir duplicados | ✅ Etapa 2 |
| Ventas | Historial por día, anular con motivo y autorización, ver solo lo propio | ✅ Etapa 1 |
| Facturación | Emisión manual en SEE-SOL, boleta de cierre, notas de crédito | ✅ Etapa 2 |
| Inventario | Stock base + movimientos − ventas, insumos, toma de inventario | ✅ Etapa 3 |
| Compras | Proveedores, facturas, pagos, vencimientos | ✅ Etapa 3 |
| Reportes | Ganancia real, flujo, comparaciones, resúmenes | ✅ Etapa 3 |
| Usuarios y roles | Rol, usuario y PIN, bloqueo, 11 módulos y 10 acciones, topes, actividad | ✅ Etapa 1 |
| Configuración | Negocio, Ticket, Cobros, Facturación, Máquinas, Productos, Seguridad, Datos | ✅ Etapa 1 (Máquinas ✅ etapa 3) |

### Estructura de la copia v3 (lo que lee el importador)

`{app: "ojitos-caja", v: 3, creado, negocio, catalog, stock, stockBase, stockLog, encargos, pedidos, plantillas, clientes: {clientes, movs}, compras: {proveedores, facturas}, maquinas: {items, prods, ult}, dias: {AAAA-MM-DD: {ventas, cpes, lecturas, anuladas, act, caja: {movs, turnos, inicial, arqueo}, cierre, cierreCpe}}, documentos, modelos, usuarios: {items, roles}}`.
Las listas pueden venir como arreglos o como mapas `{id: objeto}`. Los montos vienen en céntimos y las horas en milisegundos.

## 3. Decisiones ya tomadas por Alex

1. **Tecnología:** Laravel (la versión estable más reciente: 13) + Livewire 3. MySQL/MariaDB en producción y SQLite para desarrollo y pruebas.
2. **Varios negocios desde el inicio**, pensando en vender la plataforma. Ojitos es el primero.
3. **Servidor:** todavía sin decidir. Debe funcionar en un VPS o en un hosting compartido; usa colas si existen pero no depende de ellas (`QUEUE_CONNECTION=sync`).
4. **Código en GitHub:** `aIex-simon/ojitos-pos`.
5. **SUNAT:** más adelante, con la API directa usando Greenter. Hay que confirmar con el contador el plazo del resumen diario, las leyendas de exoneración de la Amazonía, el régimen y el certificado digital.
6. **Diseño:** el mismo aspecto del sistema actual: menú lateral (Operación, Control, Sistema), colores de imprenta (#0096C7, #C81E63, #E0AC00, #16191D), fuente Archivo, modo oscuro, entrada con panel de marca oscuro, tramas de puntos y reloj.

## 4. Plan por etapas

- **Etapa 1:** base, entrada, tablas, importador, Punto de venta, Ventas, Caja, Productos, ticket, pruebas y guía. ✅
- **Etapa 2:** Clientes y fiados, Pedidos, Facturación manual. ✅
- **Etapa 3:** Inventario, Compras, Reportes, contadores de máquinas. ✅
- **Etapa 4:** Redacción: modelos como datos, en paquetes por rubro. Sin IA dentro del sistema (decisión de Alex del 29/09/2026): frases listas y «Encargar a Claude».
- **Etapa 5:** API de SUNAT con Greenter, alta de negocios y planes, servidor MCP opcional.

Al final de cada etapa: pruebas, capturas y un resumen para Alex en lenguaje sencillo.

## 5. Notas técnicas

- Reglas exactas (boleta de cierre de hasta S/ 5 → `Catalogos::LIMITE_CIERRE`; boletas de más de S/ 700 con DNI → `MAX_SIN_DOC`; stock; costo; tope de descuento) en `app/Support/Catalogos.php`, `app/Models/Venta.php`, `app/Services/Ventas.php` y `app/Services/Stock.php`.
- Permisos de módulo: `vender`, `encargos`, `documentos`, `caja`, `clientes`, `ventas`, `comprobantes`, `inventario`, `compras`, `reportes`, `ajustes`. Permisos de acción: `descuentos`, `fiar`, `anular`, `borrar`, `gastos`, `verTodo`, `costos`, `precios`, `negocio`, `datos`.
- El ticket de venta es una «Nota de venta», no un comprobante. Numeración por usuario y día: inicial del nombre + 2 últimas letras de su id + correlativo (`A1-001`), con el número completo `AAAAMMDD-A1-001`.
- El pedido del Punto de venta se arma en el navegador (`public/js/pos.js`) para que sea instantáneo. Al cobrar, el servidor vuelve a revisar los precios: un precio distinto al del catálogo se guarda como precio cambiado (descuento) y pasa por el permiso y el tope del rol.
- Fechas del día: se guardan como `AAAA-MM-DD` con el cast `App\Casts\Fecha`. No uses el cast `date` de Laravel, porque en SQLite guarda también la hora.
- **Cuidado con los datos reales:** la base del artifact tiene datos reales. No los borres. La importación se hace siempre desde el archivo de copia.

## 6. Pendientes del sistema anterior

- Corregir los números de las boletas EB01-123 a 129, que no coinciden con SUNAT. SUNAT va por el EB01-445 y el E001-134. Ya se puede hacer en Facturación › Emitidos › «Corregir número» (pide el PIN de un administrador).
- Poner el artifact en privado (hoy es «cualquiera con el enlace»).
- ~~Archivar las compras por mes.~~ Ya no hace falta: todas las compras quedan guardadas y se buscan (etapa 3).
