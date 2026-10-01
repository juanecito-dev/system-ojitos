# Ojitos Caja

Punto de venta para Ojitos (copias e impresiones, Tingo María), hecho en **Laravel 13 + Livewire 3**.
Es la nueva versión de `caja-rapida.html`, con el mismo aspecto, y está pensado para que después lo usen otros negocios.

- **Etapa 1:** entrada con rol, usuario y PIN; Inicio; Punto de venta; Ventas; Caja y gastos; Productos y precios; ticket en PDF e imagen; Usuarios y roles; Configuración; importador de la copia del sistema anterior.
- **Etapa 2:** Clientes y fiados (con límite de fiado), Pedidos (cotizaciones, encargos, entregas, proforma y orden de trabajo) y Facturación (por emitir, boleta de cierre, notas de crédito, registro de ventas para el contador).
- **Próximas etapas:** Inventario, Compras y Reportes (etapa 3); Redacción con IA (etapa 4); SUNAT con Greenter y planes para varios negocios (etapa 5).

Capturas de pantalla: carpeta [`docs/capturas`](docs/capturas).

---

## Probarlo en tu computadora (Windows)

1. **Instala Laravel Herd** (gratis): <https://herd.laravel.com/windows>. Trae PHP y Composer. Solo se hace una vez.
2. **Baja el sistema:** en GitHub, en la rama `claude/laravel-migration-woke0o`, botón verde **Code › Download ZIP**. Descomprímelo, por ejemplo en `Documentos\ojitos-pos`.
3. **Doble clic en `iniciar-windows.bat`.** La primera vez instala lo necesario (unos minutos). Luego se abre el navegador en `http://localhost:8000`.
4. En «Bienvenido» elige **Traer mi copia** y sube tu copia del sistema actual (*Configuración › Datos y copias › Descargar copia*). Entras con tu mismo usuario y PIN.
   - Si la copia es muy grande y no sube: arrastra el archivo `.json` y suéltalo encima de **`importar-copia-windows.bat`**.
5. Para apagarlo, cierra la ventana negra. Para volver a usarlo, doble clic otra vez en `iniciar-windows.bat`.

Todo queda solo en tu PC (en `database\database.sqlite`). No toca los datos del sistema que usas hoy.

## Guía de instalación

### Qué necesita el servidor

| Cosa | Versión |
|---|---|
| PHP | 8.3 o más nuevo, con las extensiones `pdo_mysql` (o `pdo_sqlite`), `mbstring`, `gd`, `intl`, `zip`, `dom` |
| Base de datos | MySQL 8 o MariaDB 10.6 en el servidor real; SQLite sirve para pruebas |
| Composer | 2.x |

No se necesita Node ni npm: los estilos y el JavaScript ya están listos en la carpeta `public/`.
Funciona en un **VPS** o en un **hosting compartido** (cPanel) que tenga PHP 8.3.

### Paso a paso

1. **Bajar el código**
   ```bash
   git clone https://github.com/aIex-simon/ojitos-pos.git
   cd ojitos-pos
   ```
2. **Instalar y preparar**
   ```bash
   composer install --no-dev --optimize-autoloader
   cp .env.example .env
   php artisan key:generate
   ```
3. **Poner los datos de la base de datos** en el archivo `.env`:
   ```
   APP_ENV=production
   APP_DEBUG=false
   APP_URL=https://tu-dominio.pe
   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=ojitos
   DB_USERNAME=usuario_de_la_base
   DB_PASSWORD=clave_de_la_base
   ```
4. **Crear las tablas**
   ```bash
   php artisan migrate --force --seed
   php artisan optimize
   ```
5. **Apuntar el dominio a la carpeta `public/`**. En cPanel: *Dominios › Raíz del documento* → `ojitos-pos/public`.
6. **Abrir el sistema en el navegador.** La primera vez aparece «Bienvenido», con dos caminos:
   - **Traer mi copia** (recomendado): en el sistema actual entra a *Configuración › Datos y copias › Descargar copia* y sube ese archivo aquí. Se traen ventas, caja, clientes, productos, usuarios y todo lo demás. Alex y Jeremy entran con **su mismo usuario y PIN de siempre**.
   - **Empezar de cero**: crea el negocio y el usuario administrador.

### Importar una copia grande desde la terminal

Si el archivo de la copia pesa mucho y el hosting no deja subirlo:

```bash
php artisan ojitos:importar /ruta/ojitos-copia-2026-09-29.json --negocio=ojitos
```

Lo que ya está se actualiza y no se duplica. Se puede repetir **mientras no se haya usado el sistema nuevo**: si el negocio ya tiene ventas, caja, pedidos o comprobantes hechos aquí, el sistema no deja importar encima, porque se mezclarían con los de la copia y podrían deshacer anulaciones o pagos. Desde la terminal se puede forzar con `--forzar`, sabiendo que se mezclan.

### Pasar los datos del día (cuando se deja el sistema anterior)

1. En el sistema anterior: *Configuración › Datos y copias › Descargar copia*.
2. Empieza con una base nueva. En la PC: cierra la ventana negra y **cambia el nombre** de `database\database.sqlite` (por ejemplo a `database-pruebas.sqlite`; así no se pierde nada). En un servidor: `php artisan migrate:fresh --force --seed` sobre una base vacía.
3. Abre el sistema: aparece «Bienvenido». Elige **Traer mi copia** y sube el archivo del paso 1.
4. Desde ese momento se trabaja solo en el sistema nuevo.

### Copias de seguridad del servidor

Ahora los datos viven en la base de datos del servidor. Programa una copia diaria. En cPanel: *Cron Jobs*, una vez al día:

```bash
mysqldump -u USUARIO -pCLAVE ojitos | gzip > ~/copias/ojitos-$(date +\%F).sql.gz
```

### Varios equipos y varios negocios

- Cada PC entra con la dirección del sistema. En *Configuración › Datos y copias › Este equipo* se le pone nombre (por ejemplo «PC caja 1») y el ancho del ticket de su impresora (80 o 58 mm).
- Si en el mismo servidor hay más de un negocio, cada equipo entra una vez a `https://tu-dominio.pe/n/CODIGO` (por ejemplo `/n/ojitos`) y queda recordado.

---

## Para programadores

```bash
composer install
cp .env.example .env && php artisan key:generate
touch database/database.sqlite
OJITOS_DEMO=1 php artisan migrate --seed     # negocio de prueba: alex / 2580 (admin), jeremy / 1470 (vendedor)
php artisan serve
php artisan test                              # pruebas automáticas (Pest)
./vendor/bin/pint                             # formato del código
```

- Las reglas del negocio vienen de `docs/legado/caja-rapida.html` (el sistema anterior, versión 43). Antes de cambiar una regla, búscala ahí.
- El plan completo y las decisiones tomadas están en `docs/traspaso-migracion-laravel.md`.
- Montos siempre en **céntimos** (enteros). Fechas del negocio en hora de Lima.
- Cada tabla del negocio tiene `negocio_id`; el filtro se aplica solo (`app/Models/Concerns/PerteneceANegocio.php`).
