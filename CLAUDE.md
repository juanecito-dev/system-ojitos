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
- Toda acción importante queda anotada con `Bitacora::registrar(tipo, detalle)`.
- Sin Node: el CSS está en `public/css/ojitos.css` (copiado del sistema anterior) y `extra.css`, y el JS en `public/js/`.
- En Blade, no pegues una directiva a una palabra (`texto@endif`): Blade no la reconoce. Deja un espacio o algún símbolo antes.
