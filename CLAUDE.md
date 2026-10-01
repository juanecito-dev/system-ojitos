# Ojitos Caja — notas para Claude

Punto de venta en Laravel 13 + Livewire 3.8 que reemplaza a `docs/legado/caja-rapida.html`. Lee primero `docs/traspaso-migracion-laravel.md` (estado, decisiones y plan por etapas).

## Cómo trabajar con Alex
- Todo en español de Perú, en lenguaje sencillo. Alex no es programador.
- Antes de cada módulo: analizar el sistema anterior, proponer mejoras y esperar su aprobación. Después: hacerlo por partes, probar, tomar capturas y hacer un resumen sencillo.
- Las reglas del negocio salen de `docs/legado/caja-rapida.html`. Búscalas ahí antes de inventar una.

## Comandos
- `php artisan test`: pruebas con Pest. Tienen que pasar antes de cada commit.
- `./vendor/bin/pint`: formato del código.
- `OJITOS_DEMO=1 php artisan migrate:fresh --seed`: negocio de prueba (alex/2580 admin, jeremy/1470 vendedor).
- `php artisan ojitos:importar tests/fixtures/copia-v3.json --negocio=ojitos`: importa la copia de prueba.

## Convenciones
- Montos en céntimos (int). Úsalos con `App\Support\Dinero` (`s()`, `n()`, `aCentimos()`).
- Fechas del día con el cast `App\Casts\Fecha` (nunca `'date'`). Las horas en `America/Lima`.
- Los modelos del negocio usan `PerteneceANegocio`: filtran y llenan `negocio_id` solos según `NegocioActual`.
- `uid` es el id del sistema anterior (o uno nuevo con `Texto::nuevoUid()`). Sirve para que el importador no duplique.
- Los permisos se revisan en el servidor. En Livewire, las acciones que necesitan permiso llaman a `$this->requiere(...)` (trait `ConAutorizacion`): si falta el permiso, se abre la ventana para el PIN de un administrador.
- Ajustes del negocio: se leen con `$neg->ajuste(clave)` (el valor por defecto y la validación están en `App\Support\AjustesNegocio`) y se guardan con `$neg->guardarAjuste()` / `guardarAjustes()`, que no pisan lo que otro equipo guardó. Los números que suben solos (último comprobante `ult:SERIE`, correlativos) van en `secuencias` con `Numeracion` (`siguiente`, `subirA`, `fijar`), nunca en ajustes.
- Toda acción importante queda anotada con `Bitacora::registrar(tipo, detalle)`.
- Nada se borra. Una venta anulada queda con `anulada_at` (no sale en las consultas normales; `Venta::conAnuladas()` para verla). Los movimientos de caja, fiados y pagos usan borrado suave. Para quitar dinero de la caja usa `CuadreCaja::quitar()`: si su turno ya se cerró, no lo toca y anota el contrario en la caja de hoy. En las consultas con `DB::table` agrega `whereNull('anulada_at')` / `whereNull('deleted_at')`.
- El núcleo (Ventas, Caja, Clientes, Comprobantes) no nombra a los módulos de un rubro (Pedidos, Redacción, Contadores); lo revisa `tests/Unit/ArquitecturaTest.php`. Los módulos se enteran por avisos (`App\Events`: `VentaRegistrada`, `VentaAnulada`, `ClientesUnidos`) con escuchas en `app/Listeners`, dentro de la misma transacción. De dónde viene una venta o una línea va en `origen_tipo` / `origen_uid`, y un módulo reconoce sus productos por `productos.rol`, nunca por su código.
- Sin negocio elegido, las tablas del negocio no se pueden leer (`SinNegocio`). El código de plataforma que necesita ver todo usa `NegocioActual::plataforma(fn () => ...)`.
- Sin Node: el CSS está en `public/css/ojitos.css` (copiado del sistema anterior) y `extra.css`, y el JS en `public/js/`.
- En Blade, no pegues una directiva a una palabra (`texto@endif`): Blade no la reconoce. Deja un espacio o algún símbolo antes.
