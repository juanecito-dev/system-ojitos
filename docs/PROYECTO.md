# Ojitos Caja: el proyecto completo

Documento de resumen para Alex. Dice qué es el sistema, qué ya está hecho, qué falta y qué decisiones quedan pendientes.
Actualizado el 30/09/2026.

---

## 1. Qué es

**Ojitos Caja** es el sistema del negocio **Ojitos** (copias e impresiones, Jr. San Alejandro 382, Tingo María).
Reemplaza al sistema anterior, que era un solo archivo (`caja-rapida.html`, unas 6 700 líneas) publicado como página.

| | Sistema anterior | Sistema nuevo |
|---|---|---|
| Dónde vive | Una página publicada en internet | Un programa propio (Laravel 13 + Livewire 3) |
| Datos | Mezclados entre la página y cada equipo | Una sola base de datos, con copias de seguridad |
| Usuarios | Rol, usuario y PIN | Igual, y acepta los mismos PIN |
| Otros negocios | No | Sí: está preparado para varios negocios |
| Inteligencia artificial | Dentro del sistema (con costo por uso) | Fuera: modelos con frases listas y «Encargar a Claude» |
| Aspecto | Menú lateral, colores de imprenta, modo oscuro | El mismo |

**Quiénes lo usan hoy:** Alex (administrador, en su laptop) y Jeremy (vendedor, en la PC 1).

**Dónde está el código:** GitHub, repositorio `juanecito-dev/system-ojitos`, rama `claude/analiza-8fkfbl` (antes `aIex-simon/ojitos-pos`, rama `claude/laravel-migration-woke0o`). Cada parte terminada queda guardada como una versión.

**Análisis para convertirlo en SaaS (01/10/2026):** `docs/analisis-saas-2026-10-01.html` (ábrelo con el navegador). Problemas graves, qué es núcleo y qué es de la imprenta, y la hoja de ruta en 5 fases. La fase 0 ya está hecha (ver `docs/traspaso-migracion-laravel.md`); capturas 49 a 64 en `docs/capturas`.

---

## 2. Qué ya está hecho

### Etapa 1: la base ✅
- Entrada con rol, usuario y PIN. Se bloquea después de 5 intentos fallidos.
- Autorización con el PIN de un administrador cuando el usuario no tiene permiso.
- Registro de actividad: queda anotado quién hizo cada cosa importante.
- **Inicio:** resumen del día, meta y avisos.
- **Punto de venta:** buscador, lector de código de barras, favoritos, cambio de precio, descuentos con tope, métodos de pago, fiado, DNI para la boleta.
- **Ventas:** historial por día, anular con motivo y autorización.
- **Caja y gastos:** turnos por usuario con cuadre, gastos, retiros e ingresos.
- **Usuarios y roles:** 11 módulos y 10 permisos de acción, topes de descuento por rol.
- **Configuración:** negocio, ticket, cobros, facturación, máquinas, productos y precios, seguridad y datos.
- **Ticket** en PDF (80 o 58 mm) y en imagen para WhatsApp.
- **Importador** de la copia del sistema anterior. Se puede repetir sin duplicar nada.

### Etapa 2: clientes, pedidos y facturación ✅
- **Clientes y fiados:** lo que debe cada uno y desde cuándo, pagos, estado de cuenta en PDF, recordatorio por WhatsApp, unir duplicados y **límite de fiado** por cliente.
- **Pedidos:** cotización → en proceso → listo → entregado. Adelantos, pagos a cuenta, proforma, orden de trabajo, listas de útiles y mensajes de WhatsApp.
- **Facturación (a mano, en el portal de SUNAT):** lo que falta emitir, boleta de cierre del día, guía paso a paso, notas de crédito, registro del mes en Excel para el contador y aviso de números saltados.

### Etapa 3: inventario, compras y reportes ✅
- **Contadores de las máquinas:** lectura al cerrar, copias de prueba y cuadre de copias sin cobrar.
- **Inventario:** stock, entradas, conteos con motivo, insumos que gasta cada servicio, «te alcanza para N días», kárdex, toma de inventario e inventario valorizado en PDF.
- **Compras y proveedores:** compras a crédito o al contado, pagos, vencimientos, costo promedio, qué reponer y pedido por WhatsApp.
- **Reportes:** ganancia real, dinero que entró y salió, por grupo, por vendedor, por día y hora, comparación con el periodo anterior, PDF y Excel.

### Etapa 4: Redacción (3 de 4 partes) ✅
- **Parte 1 ✅ Modelos y formulario:** 13 modelos (7 contratos con 3 niveles, solicitud, carta poder, autorización, declaración jurada, renuncia y certificado de trabajo). Frases formales listas en lugar de la IA, cláusulas que se prenden, apagan y mueven, y avisos de datos que faltan.
- **Parte 2 ✅ Documento hecho:** se corrige tocando la hoja, PDF para imprimir, Word, cobro en caja (por página o por documento) y estados Sin cobrar → Cobrado → Entregado.
- **Parte 3 ✅ Currículum:** 4 diseños (simple, con foto, Harvard y moderno). Se cambia de diseño con un toque. El moderno tiene color, letra, forma de foto y columna. Frases de ejemplo por tipo de trabajo.

### Auditoría del 29/09/2026 ✅
- 14 errores corregidos. El más grave: un contrato por «1,500» salía como S/ 1.50.
- Cada campo solo deja escribir lo que corresponde: DNI 8 números, RUC 11, celular 9, PIN hasta 6, montos con 2 decimales.
- Detalle completo en `docs/auditoria-2026-09-29.md`.

**Pruebas automáticas:** 154, y todas pasan.

---

## 3. Qué falta

### 3.1 Terminar la etapa 4: Redacción, parte 4
- **«Encargar a Claude»:** en el mostrador se escribe el contexto con las palabras del cliente («Pedro le prestó 2000 soles a su cuñado…») y queda en **Por redactar**. Cuando Alex abre Claude Code y dice «redacta los pendientes», Claude escribe el texto formal y lo deja en **Listo para revisar**. También sirve mandar el contexto por el chat.
- **«Guardar como modelo»:** convertir cualquier documento hecho en un modelo propio con campos para llenar.

### 3.2 Etapa 5: SUNAT y otros negocios
- **Conexión directa con SUNAT:** que las boletas y facturas salgan solas desde el sistema, sin copiarlas a mano en el portal. Se usará la librería Greenter.
- **Alta de otros negocios y planes**, para vender la plataforma.
- **Conexión con Claude (opcional):** consultar ventas o redactar sin abrir el sistema.

### 3.3 Para dejar de usar el sistema anterior
- **Decidir dónde va a vivir el sistema** (ver punto 4).
- **Pasar los datos más recientes:** el día del cambio se importa la última copia del sistema anterior.
- **Corregir los números de las boletas EB01-123 a 129**, que no coinciden con SUNAT. Ya se puede hacer en Facturación › Emitidos › «Corregir número».
- **Poner en privado el sistema anterior**, que hoy puede abrir cualquiera que tenga el enlace.

---

## 4. Decisiones pendientes de Alex

| # | Decisión | Opciones |
|---|---|---|
| 1 | **Dónde vive el sistema** | En la PC del negocio (gratis, pero solo funciona con la PC prendida) o en un servidor en internet (se usa desde cualquier lugar, cuesta una mensualidad) |
| 2 | **SUNAT:** datos que hay que confirmar con el contador | Régimen, leyendas de exoneración de la Amazonía, plazo del resumen diario y certificado digital |
| 3 | **Clientes extranjeros en contratos** | Hoy el campo DNI solo acepta 8 números. ¿Se agrega «Carnet de extranjería»? |
| 4 | **Pagos de fiado** | El sistema anterior dejaba cobrar de más; el nuevo no lo permite. ¿Queda así? |
| 5 | **Celular del negocio** | Ahora debe empezar con 9. Si se usa un teléfono fijo, hay que cambiarlo |

---

## 5. Orden recomendado

1. **Redacción, parte 4** («Encargar a Claude» y «Guardar como modelo»). Con eso la etapa 4 queda completa.
2. **Decidir dónde vive el sistema** e instalarlo ahí.
3. **Pasar los datos del día** y empezar a usarlo de verdad (Alex y Jeremy).
4. **SUNAT** (etapa 5), con los datos del contador ya confirmados.
5. **Otros negocios y planes.**

SUNAT puede quedar después de empezar a usar el sistema, porque la facturación a mano ya funciona.

---

## 6. Cómo se trabaja

- Todo en español de Perú y en lenguaje sencillo.
- Antes de cada módulo: Claude analiza cómo lo hace el sistema anterior, propone mejoras y **espera la aprobación** de Alex.
- Después: se hace por partes, se prueba, se toman capturas y se entrega un resumen.
- Las pruebas automáticas deben pasar antes de guardar cada versión.
- Las reglas del negocio salen del sistema anterior (`docs/legado/caja-rapida.html`).
- **Datos reales:** nunca se borran. Antes de cada cambio en la base de datos se guarda una copia en `storage/app/`.

---

## 7. Cómo prenderlo en la PC

1. Doble clic en **`iniciar-windows.bat`** (en la carpeta `ojitos-pos` del Escritorio).
2. Se abre el navegador en **http://localhost:8000**.
3. La ventana negra muestra la dirección para entrar desde el celular u otra PC de la misma red.
4. Para apagarlo, se cierra la ventana negra.

---

## 8. Dónde está cada cosa

| Carpeta o archivo | Qué tiene |
|---|---|
| `docs/PROYECTO.md` | Este resumen |
| `docs/traspaso-migracion-laravel.md` | El detalle técnico para continuar el trabajo en otra conversación |
| `docs/propuestas/` | Las propuestas aprobadas de las etapas 2, 3 y 4 |
| `docs/auditoria-2026-09-29.md` | El informe de la auditoría |
| `docs/capturas/` | Capturas de pantalla y PDF de ejemplo (1 a 48) |
| `docs/legado/caja-rapida.html` | El sistema anterior completo |
| `database/modelos/` | Los modelos de documentos de Redacción |
| `README.md` | Guía de instalación en un servidor |
