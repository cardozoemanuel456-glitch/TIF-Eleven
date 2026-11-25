<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * La planilla de seguimiento no se puede usar: está dañada, no tiene la estructura
 * esperada o no tiene una columna para la materia del acta (M1-11). El mensaje se
 * le muestra tal cual a la preceptora.
 */
class PlanillaInvalidaException extends RuntimeException {}
