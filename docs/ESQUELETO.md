# Ojitos Caja: esqueleto del proyecto

Documento para darle contexto a otra IA (o a otro programador). Describe qué es el sistema, cómo está armado, qué ya existe y qué se quiere hacer. Actualizado el 30/09/2026.

---

## 1. En una frase

**Ojitos Caja** es un punto de venta web para un negocio de copias, impresiones y trámites (Ojitos, Tingo María, Perú). Reemplaza a un sistema anterior hecho en un solo archivo HTML (`docs/legado/caja-rapida.html`, ~6 700 líneas de HTML y JavaScript) y está preparado para que después lo usen otros negocios (varios negocios en la misma instalación).

## 2. Contexto del negocio y del dueño

- **Dueño:** Alex Simon Santiago. **No es programador**: todo se le explica en español de Perú, en lenguaje sencillo, con capturas.
- **Negocio:** copias, impresiones, útiles, anillados, redacción de documentos (contratos, solicitudes, currículums) y trámites. RUC 10, régimen RER, exonerado de IGV (Amazonía).
- **Usuarios hoy:** Alex (administrador) y Jeremy (vendedor).
- **Forma de trabajo acordada:**
  1. Antes de cada módulo: analizar cómo lo hace el sistema anterior, proponer mejoras y **esperar la aprobación** de Alex.
  2. Después: hacerlo por partes, probar, tomar capturas y entregar un resumen sencillo.
  3. Las pruebas automáticas deben pasar antes de cada commit.
  4. Las reglas del negocio salen del sistema anterior; no se inventan.
  5. Los datos reales no se borran; antes de migrar la base se guarda una copia.

## 3. Tecnología

| Cosa | Elección |
|---|---|
| Lenguaje y framework | PHP 8.3+ (probado en 8.4), **Laravel 13** |
| Pantallas | **Livewire 3.8** (componentes de página completa) + Alpine.js (el que trae Livewire) |
| Base de datos | SQLite en desarrollo y en la PC del negocio; MySQL 8 / MariaDB 10.6 en un servidor |
| PDF | `barryvdh/laravel-dompdf` |
| Pruebas | Pest 4 (154 pruebas) |
| Formato de código | Laravel Pint |
| Node / npm | **No se usa.** CSS y JS escritos a mano en `public/` |
| Inteligencia artificial | **No hay IA dentro del sistema** (decisión del dueño). La redacción nueva la hace Claude Code desde fuera |

## 4. Estructura de carpetas

```
ojitos-pos/
├── CLAUDE.md                      notas para la IA: cómo trabajar y convenciones
├── README.md                      guía de instalación
├── iniciar-windows.bat            prende el sistema en la PC (migra, sincroniza modelos, sirve en el puerto 8000)
├── app/
│   ├── Casts/Fecha.php            fecha del día como AAAA-MM-DD (nunca el cast 'date')
│   ├── Console/Commands/
│   │   ├── ImportarCopia.php      php artisan ojitos:importar copia.json --negocio=slug
│   │   └── SincronizarModelos.php php artisan ojitos:modelos
│   ├── Http/
│   │   ├── Controllers/           DocumentosController (PDF, Word, CSV), TicketController, SesionController
│   │   └── Middleware/            ElegirNegocio (negocio de la petición), PermisoModulo ('modulo:xxx')
│   ├── Livewire/                  una clase por pantalla (ver punto 6)
│   │   └── Concerns/              ConAutorizacion (PIN de un administrador), ConDia (navegar por día)
│   ├── Models/                    37 modelos Eloquent (ver punto 7)
│   │   └── Concerns/PerteneceANegocio.php   filtra y llena negocio_id solo
│   ├── Redaccion/                 motor de documentos sin IA
│   │   ├── Catalogo.php           lee database/modelos y los copia a la tabla modelos_redaccion
│   │   ├── Motor.php              arma el texto: campos, niveles, cláusulas numeradas
│   │   ├── Ayudas.php             montos y fechas en letras, don/doña, cónyuge, cronograma de cuotas
│   │   ├── Condicion.php          condiciones de campos: pago=partes, cl:fiador, nivel>=2, &&, ||
│   │   ├── Formato.php            formato por líneas del documento ↔ bloques ↔ HTML del sistema anterior
│   │   └── Curriculum.php         el CV en 4 diseños (se dibuja con los datos, no con texto)
│   ├── Services/                  reglas del negocio (ver punto 8)
│   └── Support/
│       ├── Dinero.php             montos en céntimos: s(), n(), aCentimos(), aCosto(), leer()
│       ├── Texto.php              norm(), cel9(), nuevoUid(), letras(), sunat()
│       ├── Valida.php             fecha(), mes(), dni(), ruc(), celular(), serie(), numeroCpe()
│       ├── Catalogos.php          listas fijas: módulos, permisos, métodos de pago, regímenes, límites
│       ├── Acceso.php             qué módulos puede abrir el usuario
│       ├── NegocioActual.php      el negocio de esta petición
│       └── Avisos.php             avisos del menú y de Inicio
├── database/
│   ├── migrations/                11 migraciones (base, catálogo, operación, etapas 2, 3 y 4)
│   ├── data/catalogo-inicial.json productos con los que nace un negocio
│   └── modelos/                   modelos de documentos como datos (PHP que devuelve un arreglo)
│       ├── _campos.php, _comunes.php          grupos de campos y cláusulas comunes
│       ├── contratos/             compraventa de terreno y de vehículo, arrendamientos, locación, préstamo, compromiso
│       ├── tramites/              solicitud, carta poder, autorización, declaración jurada
│       ├── laboral/               renuncia, certificado de trabajo
│       └── cv/                    simple, con foto (cv_medio), Harvard, moderno
├── public/
│   ├── css/ojitos.css             estilos copiados del sistema anterior; extra.css para lo nuevo
│   └── js/
│       ├── ojitos.js              app general, copiar, hoja editable, foto del CV
│       ├── pos.js                 el punto de venta arma el pedido en el navegador
│       ├── campos.js              reglas de tecleo por campo (data-solo="dni|ruc|cel|pin|monto|…")
│       └── ticket.js              ticket como imagen
├── resources/views/
│   ├── components/layouts/        app.blade.php (menú lateral) y login.blade.php
│   ├── livewire/                  una vista por pantalla, con partials/
│   ├── pdf/                       ticket, a4, proforma, estado de cuenta, inventario, reporte, redaccion, cv
│   └── redaccion/                 hoja, campo, word, cv, cv-word
├── routes/web.php                 todas las rutas (ver punto 6)
├── tests/                         Feature (16 archivos) y Unit (2)
└── docs/
    ├── PROYECTO.md                resumen para el dueño
    ├── ESQUELETO.md               este documento
    ├── traspaso-migracion-laravel.md   detalle técnico y decisiones
    ├── auditoria-2026-09-29.md    errores encontrados y corregidos
    ├── propuestas/                propuestas aprobadas de las etapas 2, 3 y 4
    ├── capturas/                  capturas y PDF de ejemplo
    └── legado/caja-rapida.html    el sistema anterior: fuente de las reglas del negocio
```

## 5. Convenciones que no se deben romper

- **Montos en céntimos (int).** Se leen y muestran con `App\Support\Dinero`. Los costos pueden tener fracción de céntimo (`decimal(14,4)`).
- **Fechas del día** con el cast `App\Casts\Fecha`. Zona horaria `America/Lima`.
- **Varios negocios:** todos los modelos del negocio usan el trait `PerteneceANegocio` (filtro automático por `negocio_id`).
- **`uid`:** identificador de texto heredado del sistema anterior, o uno nuevo con `Texto::nuevoUid()`. El importador lo usa para no duplicar.
- **Permisos en el servidor.** En Livewire, una acción con permiso llama a `$this->requiere('permiso', ...)`; si falta, se abre la ventana del PIN de un administrador. Las propiedades que abren ventanas sensibles llevan `#[Locked]`.
- **Toda acción importante** se anota con `Bitacora::registrar(tipo, detalle)`.
- **Errores de negocio:** los servicios lanzan `App\Services\ErrorNegocio` con un mensaje para el usuario.
- **Validación en dos capas:** `public/js/campos.js` limita lo que se teclea; `Valida` y `Dinero` lo revisan otra vez al guardar.
- **Blade:** no pegar una directiva a una palabra (`texto@endif`).
- **Textos** de pantalla en español de Perú, sencillos.

## 6. Pantallas y rutas

Todas las rutas están en `routes/web.php`, dentro de `auth` y con el middleware `modulo:<permiso>`.

| Ruta | Componente Livewire | Permiso de módulo | Qué hace |
|---|---|---|---|
| `/primer-uso` | `PrimerUso` | — | Crear el negocio o traer la copia del sistema anterior |
| `/entrar` | `Entrar` | — | Rol, usuario y PIN |
| `/` | `Inicio` | — | Resumen del día y avisos |
| `/vender` | `Vender` | `vender` | Punto de venta y cobro |
| `/ventas` | `Ventas` | `ventas` | Ventas del día, anular, ticket |
| `/caja` | `Caja` (+ `CajaContadores`) | `caja` | Turnos, gastos, cuadre y contadores de máquinas |
| `/clientes` | `Clientes` | `clientes` | Fiados, pagos, ficha, estado de cuenta |
| `/pedidos` | `Pedidos` | `encargos` | Cotizaciones, encargos, entregas, listas de útiles |
| `/facturacion` | `Facturacion` | `comprobantes` | Por emitir, emitidos, registro del mes |
| `/inventario`, `/inventario/toma` | `Inventario`, `InventarioToma` | `inventario` | Stock, kárdex, insumos, toma de inventario |
| `/compras` | `Compras` | `compras` | Compras, pagos y proveedores |
| `/reportes` | `Reportes` | `reportes` | Ganancia, flujo, comparaciones |
| `/redaccion` | `Redaccion` | `documentos` | Modelos y documentos hechos |
| `/redaccion/nuevo/{modelo}`, `/redaccion/{uid}` | `RedaccionDocumento` | `documentos` | Formulario, hoja, diseño del CV, cobro |
| `/usuarios` | `Usuarios` | solo administrador | Usuarios, roles y actividad |
| `/ajustes` | `Ajustes` (+ `AjustesMaquinas`) | `ajustes` | Negocio, ticket, cobros, facturación, productos, seguridad, datos |

Descargas (controladores): ticket en PDF y JSON, ventas en CSV, estado de cuenta, proforma, orden de trabajo, inventario valorizado, registro de ventas y de compras, reporte, y PDF y Word de documentos.

**Permisos de acción:** `descuentos`, `fiar`, `anular`, `borrar`, `gastos`, `verTodo`, `costos`, `precios`, `negocio`, `datos`.

## 7. Datos (tablas principales)

| Grupo | Tablas |
|---|---|
| Negocio y acceso | `negocios`, `modulos_negocio`, `usuarios`, `roles`, `permisos`, `permiso_rol`, `actividades`, `secuencias` |
| Catálogo | `productos`, `producto_opciones` (precios), `producto_insumos` (qué gasta cada servicio) |
| Venta y caja | `ventas`, `venta_items`, `ventas_anuladas`, `dias`, `turnos_caja`, `caja_movimientos` |
| Clientes | `clientes`, `cliente_movimientos` (fiados y abonos) |
| Pedidos | `pedidos`, `pedido_items`, `pedido_pagos`, `pedido_historial`, `plantillas_utiles` |
| Comprobantes | `comprobantes`, `comprobante_items` |
| Inventario | `stock_bases` (último conteo), `stock_movimientos`, `toma_conteos` |
| Compras | `proveedores`, `compras`, `compra_items`, `compra_pagos` |
| Máquinas | `maquinas`, `lecturas_contador` |
| Redacción | `modelos_redaccion` (modelos del sistema), `modelos_documento` (modelos propios), `documentos` |

Reglas clave de los datos:
- **Stock** = último conteo + entradas − salidas − lo vendido después (también los insumos de cada servicio).
- **Pedidos:** cada adelanto o pago es una venta del día (`ventas.pedido_uid`).
- **Documentos:** `texto` guarda el documento en un formato por líneas; `datos.data` guarda lo que se llenó en el formulario; `estado` = borrador → cobrado → entregado; `venta_items.documento_uid` une la venta con el documento.
- **Comprobantes:** hoy se emiten a mano en el portal de SUNAT y aquí solo se registran.

## 8. Servicios (reglas del negocio)

| Servicio | Responsabilidad |
|---|---|
| `Ventas` | Registrar, anular y deshacer ventas; revisar el comprobante pedido |
| `Numeracion` | Correlativos sin repetir (tickets, pedidos) |
| `CajaDia` | Cuentas del día y de cada turno |
| `Clientes` | Saldos, pagos, deudas, estado de cuenta, unir duplicados |
| `Pedidos`, `PedidoTextos` | Ciclo del pedido, pagos, stock al entregar, textos de WhatsApp |
| `Comprobantes` | Pendientes, registrar, anular, notas de crédito, numeración, validación de RUC |
| `Stock`, `Inventario` | Cálculo del stock, entradas, conteos, kárdex, velocidad de salida |
| `Compras` | Compras, costo promedio, pagos, sugerencias de reposición |
| `Contadores` | Lecturas y cuadre de copias de las máquinas |
| `Reportes` | Todas las cuentas de un periodo |
| `Redaccion` | Generar documentos, PDF, Word, páginas, cobro, clientes del documento |
| `Ticket` | Nota de venta en PDF y en filas para imagen |
| `AccesoPin` | Verificar el PIN, bloqueo por intentos, PIN del sistema anterior |
| `AltaNegocio` | Crear un negocio con su catálogo, roles y usuarios |
| `ImportadorCopia` | Traer la copia v3 del sistema anterior |
| `Bitacora` | Registro de actividad |

## 9. Redacción sin IA (cómo funciona)

- Un **modelo** es un archivo PHP en `database/modelos/{paquete}/{id}.php` que devuelve: `id`, `nombre`, `seccion`, `cobro` (`pagina` | `doc` | `cv`), `niveles`, `campos` y una plantilla Blade en texto (o `titulo`, `intro`, `clausulas`, `firmas` en los contratos).
- **Campos:** `['id', 'label', 'type' => select|area|date|money|num|check|photo, 'req', 'si' => condición, 'frases' => [...]]`; `['t' => 'persona', 'id' => 'ven']` pide trato, nombre, DNI, estado civil, domicilio, cónyuge y celular; `['t' => 'lista']` repite un grupo (estudios, experiencia).
- **Frases listas:** reemplazan al «pulir con IA». Pueden depender de otro campo (`frasesPor => 'rubro'`).
- **Formato del texto** que se guarda (una línea por bloque):
  ```
  [title] CONTRATO DE …
  [p] Conste por el presente… **texto en negrita**
  [left] / [right] / [center] / [li] / [sum]
  [firma] NOMBRE | DNI | rol [| sin huella]
  ```
- **Currículum:** no usa texto por líneas; se dibuja con los datos (`Curriculum` + `resources/views/redaccion/cv.blade.php`), la misma vista para pantalla y PDF.

## 10. Estado

| Etapa | Contenido | Estado |
|---|---|---|
| 1 | Base, entrada, Punto de venta, Ventas, Caja, Productos, ticket, usuarios, importador | ✅ |
| 2 | Clientes y fiados, Pedidos, Facturación manual | ✅ |
| 3 | Contadores de máquinas, Inventario, Compras, Reportes | ✅ |
| 4 | Redacción: modelos y formulario ✅, PDF/Word/cobro ✅, currículum ✅, **encargos y modelos propios ⏳** | 3 de 4 partes |
| Auditoría | 14 errores corregidos, reglas de tecleo, validaciones | ✅ |
| 5 | SUNAT directo, otros negocios y planes | Pendiente |

## 11. Lo que se quiere hacer (pendiente)

### 11.1 Etapa 4, parte 4
- **«Encargar a Claude»:** un modelo de texto libre. En el mostrador se escribe el contexto con las palabras del cliente y los datos de las personas; el documento queda con `encargo = pendiente` («Por redactar»). Cuando el dueño abre Claude Code y dice «redacta los pendientes», Claude lee esos documentos, escribe el texto formal en el formato por líneas, lo guarda en `documentos.texto` y pone `encargo = listo` («Listo para revisar»). También debe servir mandar el contexto por el chat. Los documentos «con IA» del sistema anterior (`plantilla = ia`) pasan a este modelo.
- **«Guardar como modelo»:** convertir un documento hecho en un modelo propio del negocio (tabla `modelos_documento`), con campos para llenar. Las plantillas propias usan marcadores seguros, **no Blade**.

### 11.2 Etapa 5
- **SUNAT directo** con la librería **Greenter**: boletas, facturas, notas de crédito y resumen diario desde el sistema. Los comprobantes ya guardan su detalle (`comprobante_items`). Falta confirmar con el contador: régimen, leyendas de exoneración de la Amazonía, plazo del resumen diario y certificado digital.
- **Alta de otros negocios y planes** (la base ya separa los datos por negocio).
- **Servidor MCP opcional** para consultar el sistema desde Claude.

### 11.3 Puesta en marcha
- Decidir dónde vive el sistema: la PC del negocio o un servidor (VPS o hosting compartido con PHP 8.3).
- Importar la copia más reciente del sistema anterior el día del cambio.
- Corregir los números de las boletas EB01-123 a 129.
- Poner en privado el sistema anterior.

### 11.4 Decisiones abiertas
1. ¿Agregar «Carnet de extranjería» en los contratos? Hoy el DNI solo acepta 8 números.
2. ¿Queda prohibido cobrar a un cliente más de lo que debe? (el sistema anterior lo permitía).
3. ¿El celular del negocio debe empezar con 9, o se acepta un fijo?
4. Bloquear un pedido mientras se cobra, para que no se cobre dos veces desde dos equipos.

## 12. Comandos

```bash
php artisan test                                   # pruebas (deben pasar antes de cada commit)
./vendor/bin/pint                                  # formato del código
php artisan migrate --force                        # actualizar la base
php artisan ojitos:modelos                         # copiar database/modelos a la base
OJITOS_DEMO=1 php artisan migrate:fresh --seed     # negocio de prueba (solo en una base de pruebas)
php artisan ojitos:importar copia.json --negocio=ojitos   # traer la copia del sistema anterior
```

Repositorio: GitHub `aIex-simon/ojitos-pos`, rama `claude/laravel-migration-woke0o`.
