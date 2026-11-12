<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * La operación no se puede hacer sobre esta acta (ya fue procesada, sus archivos
 * temporales ya no existen o no hay nada para cargar). El mensaje se muestra tal cual.
 */
class ActaNoDisponibleException extends RuntimeException {}
