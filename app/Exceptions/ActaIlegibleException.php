<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * El acta no se pudo leer. El mensaje está pensado para mostrárselo tal cual a la
 * preceptora (M1-11) y nunca incluye contenido del acta.
 */
class ActaIlegibleException extends RuntimeException {}
