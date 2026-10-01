# Auditoría del sistema (29/09/2026)

Revisión de todo el código antes de la etapa 5 (SUNAT): reglas del negocio comparadas con `docs/legado/caja-rapida.html`, permisos, datos escritos a mano y fechas. Cada arreglo tiene su prueba en `tests/Feature/AuditoriaTest.php` y `tests/Unit/ValidaTest.php`.

## Errores corregidos

| # | Qué pasaba | Arreglo |
|---|---|---|
| 1 | **Contratos:** un monto escrito «1,500» (mil quinientos) se leía como S/ 1.50 y el contrato decía «UNO Y 50/100 SOLES». Venía del sistema anterior. | `Dinero::leer()` entiende la coma de miles como se escribe en Perú (1,500 · 1,500.00 · 1.500.000). En los costos la coma sigue siendo decimal (0,025). |
| 2 | Los montos aceptaban notación científica («1e3» = 1000) y cifras absurdas que en MySQL harían fallar la venta. | Solo números; tope de S/ 10 millones por monto escrito. |
| 3 | Una fecha imposible (2026-13-45) en Reportes, Inventario, Ventas o las descargas hacía caer la pantalla; 2026-02-31 se guardaba y luego se corría al 3 de marzo. | `App\Support\Valida::fecha()` y `::mes()` con fechas reales, en todos esos lugares. |
| 4 | Una compra podía tener fecha futura y vencer antes de hacerse. | Se rechaza con un mensaje claro. |
| 5 | Configuración del negocio: RUC, celular, Yape, series, régimen, IGV, ancho del ticket, bloqueo y meta se guardaban sin revisar (una serie «X1» o un RUC mal escrito llegarían a SUNAT). | `Ajustes::problemaAjuste()`: RUC válido, celular de 9 números con 9 al inicio, serie de boletas con B/EB y de facturas con F/E, opciones solo de la lista. |
| 6 | Al registrar un comprobante (Facturación, «Ya la emití» al cobrar y notas de crédito) la serie podía ser cualquier texto y el número de cualquier largo. | Serie de 4 letras o números según el tipo; número de 1 a 8 cifras. |
| 7 | Se podía cobrar a un cliente más de lo que debía (500 en vez de 50) y quedaba con saldo a favor. Venía del sistema anterior. | Igual que en pedidos y compras: no se cobra más que la deuda. |
| 8 | Desde el navegador se podía mandar «fiado» como método de un adelanto de pedido, sin el permiso de fiar. | Los pagos de pedidos solo aceptan los métodos activos. |
| 9 | «Rechazar», «reabrir» y «renovar» un pedido no revisaban la etapa (se podía «rechazar» un encargo ya pagado). | Solo desde la etapa que corresponde. |
| 10 | Un DNI incompleto de un documento (1234) quedaba guardado en la ficha del cliente. | Solo se guarda un DNI de 8 números o un RUC válido. |
| 11 | Una línea cualquiera de la caja podía marcar un documento de Redacción como cobrado. | Solo la redacción (contrato, solicitud, CV) y sus copias B/N. |
| 12 | El tope de descuento de un rol de 150 % quedaba en «sin tope» sin avisar. | Pide un número de 1 a 100. |
| 13 | La lectura de un contador aceptaba números de cualquier largo; las copias de prueba, cualquier cantidad. | Hasta 9 cifras y hasta 100 000 copias. |
| 14 | Cerrar caja volvía a leer el efectivo contado sin revisar que no fuera negativo. | Se revisa también al confirmar. |

## Campos que solo dejan escribir lo que corresponde

`public/js/campos.js` (en todas las pantallas, también en la de entrada) filtra lo que se teclea o se pega según `data-solo`:

| Regla | Campos | Qué deja |
|---|---|---|
| `dni` | DNI de las personas en Redacción y del currículum | solo 8 números |
| `ruc` | RUC del negocio, de proveedores y de contratos | solo 11 números |
| `doc` | DNI o RUC de clientes, pedidos, boletas y facturas | solo números, hasta 11 |
| `cel` | celulares | solo 9 números (quita el +51, espacios y guiones) |
| `pin` | PIN al entrar, autorizar, cambiarlo y en usuarios | solo números, hasta 6 |
| `monto` | precios, pagos, caja, descuentos, adelantos | números con un punto y 2 decimales |
| `soles` | montos de contratos | números con comas de miles y punto (1,500.00) |
| `costo` | costos | hasta 4 decimales |
| `cantidad` | stock, mínimos, cantidades de compras | hasta 3 decimales |
| `entero` | números de comprobante, lecturas, cuotas, días | solo números |
| `serie` | series de comprobantes | 4 letras o números, en mayúsculas |

El servidor vuelve a revisar todo al guardar (`App\Support\Valida` y `App\Support\Dinero`).

## Lo que se revisó y está bien

- Permisos en el servidor: las acciones con permiso (anular, borrar, fiar, descuentos, gastos, datos, precios, costos, negocio) piden el PIN como en el sistema anterior; las ventanas que abren esas acciones están bloqueadas (`#[Locked]`) y no se pueden abrir desde el navegador sin permiso.
- Cada negocio solo ve sus datos (`PerteneceANegocio`) y las pantallas vuelven a revisar el módulo en cada acción (`addPersistentMiddleware`).
- Venta: el precio y el descuento se recalculan en el servidor; el tope por rol, el límite de fiado y las boletas de más de S/ 700 con DNI funcionan como antes.
- Caja por turnos, pedidos con adelantos, compras con costo promedio, stock, notas de crédito y numeración sin repetir.
- Entrada: bloqueo por intentos (vale en todos los equipos) y límite por equipo.

## Para decidir más adelante

- **Carnet de extranjería:** los campos de DNI de Redacción ahora solo aceptan 8 números. Si un cliente extranjero necesita un contrato, habría que agregar la opción «Carnet de extranjería» (9 a 12 caracteres).
- **Cobrar el mismo pedido en dos equipos a la vez:** muy improbable, pero hoy ambos cobros podrían pasar. Se puede bloquear el pedido mientras se cobra.
- **Stock negativo:** el stock nunca baja de 0 en pantalla; si se vende más de lo contado no se nota hasta el próximo conteo.
