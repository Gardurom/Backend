<?php

namespace App\Actions\Importing\Personas;

use App\Actions\Importing\CreateImportBatch;
use App\Actions\Importing\StageImportRows;
use App\Importing\InvalidXlsxImportSource;
use App\Importing\Personas\InvalidPersonImportTemplate;
use App\Importing\Personas\PersonImportTemplateV2;
use App\Importing\Readers\XlsxImportSourceReader;
use App\Models\ImportBatch;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

final class CreateAndValidatePersonImportBatch
{
    private const MAX_DATA_ROWS = 5000;

    public function __construct(
        private readonly CreateImportBatch $createBatch,
        private readonly StageImportRows $stageRows,
        private readonly ValidatePersonImportBatch $validateBatch,
        private readonly XlsxImportSourceReader $reader,
        private readonly PersonImportTemplateV2 $template
    ) {}

    public function execute(
        int $userId,
        UploadedFile $file
    ): ImportBatch {
                // SIGA_PERSONAS_EJEMPLOS_SIN_ALTA_V1
        if (preg_match(
            '/ejempl|fictici/i',
            $file->getClientOriginalName()
        ) === 1) {
            throw new InvalidPersonImportTemplate(
                'No se admiten archivos de ejemplos para registrar Personas.'
            );
        }
$this->preflight(
            $file
        );

        $batch =
            $this->createBatch->execute(
                $userId,
                $this->template->importType(),
                $file,
                [
                    'template_version' => $this->template->version(),
                ]
            );

        $storedPath =
            Storage::disk(
                $batch->storage_disk
            )->path(
                $batch->storage_path
            );

        $this->stageRows->execute(
            $batch->id,
            $this->sourceRows($storedPath)
        );

        return $this->validateBatch->execute(
            $batch->id
        );
    }

    // SIGA_PERSONAS_XLSX_COLUMNAS_RELEVANTES_V1
    // Usa solo datos de Persona y conserva la plantilla v2 original.
    // SIGA_PERSONAS_PREVIEW_ROWS_V1
    // Reutiliza el mismo mapeo de columnas sin crear lotes.
    public function previewSourceRows(string $filePath): \Generator
    {
        yield from $this->sourceRows($filePath);
    }
    private function sourceRows(string $path): \Generator
    {
        $columns = $this->template->columns();
        $mapping = null;

        $aliases = [
            'NOMBRES' => 'nombres',
            'NOMBRE' => 'nombres',
            'APELLIDOPATERNO' => 'apellido_paterno',
            'APELLIDOMATERNO' => 'apellido_materno',
            'RFC' => 'rfc',
            'CURP' => 'curp',
            'FECHANACIMIENTO' => 'fecha_nacimiento',
            'SEXO' => 'sexo',
            'ESTADOCIVIL' => 'estado_civil',
            'CORREOINSTITUCIONAL' => 'correo_institucional',
            'CORREOPERSONAL' => 'correo_personal',
            'PAISORIGENALPHA3' => 'pais_origen_alpha3',
            'PAISNACIMIENTOALPHA3' => 'pais_nacimiento_alpha3',
            'TERRITORIONACIMIENTOCLAVE' => 'territorio_nacimiento_clave',
        ];

        foreach ($this->reader->read($path) as $row) {
            if ($mapping === null) {
                $headers = array_keys($row->rawData);
                $mapping = [];

                // Compatibilidad exacta con PERSONAS v2.
                if ($headers === $columns) {
                    foreach ($headers as $header) {
                        $mapping[$header] = $header;
                    }
                } else {
                    foreach ($headers as $header) {
                        $key = mb_strtoupper(trim($header), 'UTF-8');

                        $key = strtr($key, [
                            'Á' => 'A',
                            'É' => 'E',
                            'Í' => 'I',
                            'Ó' => 'O',
                            'Ú' => 'U',
                            'Ü' => 'U',
                            'Ñ' => 'N',
                        ]);

                        $key = preg_replace(
                            '/[^A-Z0-9]/u',
                            '',
                            $key
                        ) ?? '';

                        // Las columnas academicas se ignoran.
                        if (!isset($aliases[$key])) {
                            continue;
                        }

                        $field = $aliases[$key];

                        if (in_array($field, $mapping, true)) {
                            throw new \App\Importing\Personas\InvalidPersonImportTemplate(
                                'Encabezados ambiguos para Persona: '.$field.'.'
                            );
                        }

                        $mapping[$header] = $field;
                    }

                    $recognized = array_values($mapping);

                    if (
                        !in_array('nombres', $recognized, true)
                        || (
                            !in_array('apellido_paterno', $recognized, true)
                            && !in_array('apellido_materno', $recognized, true)
                        )
                        || (
                            !in_array('rfc', $recognized, true)
                            && !in_array('curp', $recognized, true)
                        )
                    ) {
                        throw new \App\Importing\Personas\InvalidPersonImportTemplate(
                            'Para importar Personas se necesitan nombres, apellidos y RFC o CURP.'
                        );
                    }
                }
            }

            // Reconstruye exactamente las 13 columnas de PERSONAS v2.
            $person = array_fill_keys($columns, null);

            foreach ($mapping as $source => $destination) {
                $person[$destination] =
                    $row->rawData[$source] ?? null;
            }

            yield new \App\Importing\ImportSourceRow(
                $row->rowNumber,
                $person
            );
        }
    }

    private function preflight(
        UploadedFile $file
    ): void {
        $sourcePath =
            $file->getRealPath();

        if ($sourcePath === false) {
            throw new InvalidXlsxImportSource(
                'No fue posible acceder al archivo XLSX.'
            );
        }

        if (! is_file($sourcePath)) {
            throw new InvalidXlsxImportSource(
                'No fue posible acceder al archivo XLSX.'
            );
        }

        $dataRows = 0;
        $columnsChecked = false;

        foreach (
            $this->sourceRows($sourcePath) as $row
        ) {
            if (! $columnsChecked) {
                if (
                    array_keys($row->rawData)
                    !== $this->template->columns()
                ) {
                    throw new InvalidPersonImportTemplate(
                        'Las columnas deben coincidir exactamente con la plantilla PERSONAS v2.'
                    );
                }

                $columnsChecked = true;
            }

            $dataRows++;

            if ($dataRows > self::MAX_DATA_ROWS) {
                throw new InvalidXlsxImportSource(
                    'El archivo XLSX no puede contener mas de 5000 filas de datos.'
                );
            }
        }

        if ($dataRows === 0) {
            throw new InvalidXlsxImportSource(
                'El archivo XLSX debe contener al menos una fila de datos.'
            );
        }
    }
}
