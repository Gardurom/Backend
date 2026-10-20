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
            $this->reader->read(
                $storedPath
            )
        );

        return $this->validateBatch->execute(
            $batch->id
        );
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

        $hasRows = false;
        $columnsChecked = false;

        foreach (
            $this->reader->read(
                $sourcePath
            ) as $row
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

            $hasRows = true;
        }

        if (! $hasRows) {
            throw new InvalidXlsxImportSource(
                'El archivo XLSX debe contener al menos una fila de datos.'
            );
        }
    }
}
