<?php

namespace App\Services;

use App\Exceptions\ActaIlegibleException;
use Smalot\PdfParser\Config;
use Smalot\PdfParser\Parser;
use Throwable;

/**
 * Extrae el texto de un acta en PDF.
 *
 * Es el único punto de la aplicación que conoce la librería smalot/pdfparser: si se
 * cambia de librería, el cambio queda acá y el resto del sistema no se entera.
 */
class LectorPdfActa
{
    /**
     * @throws ActaIlegibleException si el archivo no existe, no se puede leer, está dañado
     *                               o no tiene texto (acta escaneada como imagen)
     */
    public function extraer(string $ruta): string
    {
        if (! is_file($ruta) || ! is_readable($ruta)) {
            throw new ActaIlegibleException('No se encontró el archivo del acta.');
        }

        try {
            $config = new Config;
            $config->setRetainImageContent(false);

            $texto = (new Parser([], $config))->parseFile($ruta)->getText();
        } catch (Throwable) {
            throw new ActaIlegibleException('No se pudo leer el PDF: el archivo está dañado o no es un PDF válido.');
        }

        if (trim($texto) === '') {
            throw new ActaIlegibleException(
                'El PDF no contiene texto. Si es un acta escaneada como imagen, Eleven no puede leerla (no hay reconocimiento de texto).'
            );
        }

        return $texto;
    }
}
