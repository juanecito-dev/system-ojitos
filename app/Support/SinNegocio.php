<?php

namespace App\Support;

use RuntimeException;

/** Se intentó leer o guardar datos de un negocio sin saber de cuál: se detiene para no mezclar negocios. */
class SinNegocio extends RuntimeException {}
