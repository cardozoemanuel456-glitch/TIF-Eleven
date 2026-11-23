<?php

namespace Tests\Concerns;

use App\Enums\Rol;
use App\Models\Acta;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/** Ayudas para los tests del flujo de actas. Requiere CreaUsuarios y Storage::fake('local'). */
trait SubeActas
{
    protected function fixture(string $nombre): string
    {
        return base_path('tests/Fixtures/'.$nombre);
    }

    protected function pdfSubido(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('LIBRO_156.pdf', file_get_contents($this->fixture('acta_ejemplo.pdf')));
    }

    protected function planillaReal(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('SEGUIMIENTO_ACADEMICO_TUP.xlsx', file_get_contents($this->fixture('planilla_ejemplo.xlsx')));
    }

    /** La planilla real con algunas celdas modificadas: [[pestaña, celda, valor], ...]. */
    protected function planillaConCeldas(array $celdas): UploadedFile
    {
        $libro = IOFactory::load($this->fixture('planilla_ejemplo.xlsx'));

        foreach ($celdas as [$hoja, $celda, $valor]) {
            $libro->getSheetByName($hoja)->setCellValueExplicit($celda, $valor, DataType::TYPE_STRING);
        }

        return $this->aUploadedFile($libro, 'SEGUIMIENTO_ACADEMICO_TUP.xlsx');
    }

    /** Una planilla mínima: [pestaña => [celda => valor]]. */
    protected function planillaMinima(array $hojas): UploadedFile
    {
        $libro = new Spreadsheet;
        $primera = true;

        foreach ($hojas as $titulo => $celdas) {
            $hoja = $primera ? $libro->getActiveSheet() : $libro->createSheet();
            $primera = false;
            $hoja->setTitle((string) $titulo);

            foreach ($celdas as $celda => $valor) {
                $hoja->setCellValue($celda, $valor);
            }
        }

        return $this->aUploadedFile($libro, 'planilla.xlsx');
    }

    protected function aUploadedFile(Spreadsheet $libro, string $nombre): UploadedFile
    {
        $ruta = tempnam(sys_get_temp_dir(), 'plan');
        (new Xlsx($libro))->save($ruta);
        $contenido = file_get_contents($ruta);
        @unlink($ruta);

        return UploadedFile::fake()->createWithContent($nombre, $contenido);
    }

    protected function preceptor(): User
    {
        return $this->crearUsuario(Rol::Preceptor);
    }

    /** Sube el acta real y devuelve el acta creada, en estado «analizada». */
    protected function actaAnalizada(User $usuario, ?UploadedFile $planilla = null): Acta
    {
        $this->actingAs($usuario)->from('/actas/subir')->post('/actas/analizar', [
            'acta_pdf' => $this->pdfSubido(),
            'planilla' => $planilla ?? $this->planillaReal(),
        ])->assertRedirect();

        return Acta::latest('id')->firstOrFail();
    }

    /** Sube y confirma: devuelve el acta ya procesada, con una planilla generada. */
    protected function actaProcesada(User $usuario, ?UploadedFile $planilla = null): Acta
    {
        $acta = $this->actaAnalizada($usuario, $planilla);
        $this->actingAs($usuario)->post(route('actas.confirmar', $acta))->assertSessionHas('estado');

        return $acta->refresh();
    }

    /** Reprocesa con una planilla: deja la acta con una planilla pendiente. */
    protected function reprocesarCon(User $usuario, Acta $acta, ?UploadedFile $planilla = null): void
    {
        $this->actingAs($usuario)
            ->post(route('actas.reprocesar.guardar', $acta), ['planilla' => $planilla ?? $this->planillaReal()])
            ->assertRedirect(route('actas.show', $acta));
    }

    /** Hace que el acta parezca tener más de $horas de antigüedad (para probar la limpieza). */
    protected function envejecer(Acta $acta, int $horas): void
    {
        Acta::whereKey($acta->id)->update(['updated_at' => now()->subHours($horas)]);
    }
}
