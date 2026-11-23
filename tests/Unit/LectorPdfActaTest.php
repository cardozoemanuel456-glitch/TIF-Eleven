<?php

namespace Tests\Unit;

use App\Exceptions\ActaIlegibleException;
use App\Services\LectorPdfActa;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class LectorPdfActaTest extends TestCase
{
    private function fixture(string $nombre): string
    {
        return dirname(__DIR__).'/Fixtures/'.$nombre;
    }

    #[Test]
    public function extrae_el_texto_de_un_acta_valida(): void
    {
        $texto = (new LectorPdfActa)->extraer($this->fixture('acta_ejemplo.pdf'));

        $this->assertStringContainsString('Asignatura', $texto);
    }

    #[Test]
    public function rechaza_un_archivo_que_no_existe(): void
    {
        $this->expectException(ActaIlegibleException::class);

        (new LectorPdfActa)->extraer($this->fixture('no_existe.pdf'));
    }

    #[Test]
    public function rechaza_un_pdf_sin_texto(): void
    {
        $this->expectException(ActaIlegibleException::class);

        (new LectorPdfActa)->extraer($this->fixture('pdf_sin_texto.pdf'));
    }
}
