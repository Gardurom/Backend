<?php

namespace App\Http\Controllers\Api;

use App\Actions\Importing\Personas\ConfirmPersonImportBatch;
use App\Actions\Importing\Personas\CreateAndValidatePersonImportBatch;
use App\Http\Controllers\Controller;
use App\Importing\InvalidImportBatchConfirmation;
use App\Importing\InvalidXlsxImportSource;
use App\Importing\Personas\InvalidPersonImportTemplate;
use App\Importing\Personas\PersonImportTemplateV2;
use App\Models\ImportBatch;
use App\Models\ImportRow;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;

final class PersonImportController extends Controller
{
    public function store(
        Request $request,
        CreateAndValidatePersonImportBatch $createAndValidate
    ): JsonResponse {
        $validated =
            $request->validate([
                'archivo' => [
                    'required',
                    'file',
                    'extensions:xlsx',
                    'max:5120',
                ],
            ]);

        try {
            $batch =
                $createAndValidate->execute(
                    (int) $request
                        ->user()
                        ->getAuthIdentifier(),
                    $validated['archivo']
                );
        } catch (
            InvalidXlsxImportSource|
            InvalidPersonImportTemplate $exception
        ) {
            throw ValidationException::withMessages([
                'archivo' => [
                    $exception->getMessage(),
                ],
            ]);
        }

        return response()->json(
            [
                'data' => $this->publicBatchData(
                    $batch
                ),
            ],
            201
        );
    }

    public function confirm(
        string $batchUuid,
        ConfirmPersonImportBatch $confirmBatch
    ): JsonResponse {
        $batch = ImportBatch::query()
            ->where(
                'uuid',
                $batchUuid
            )
            ->where(
                'import_type',
                'PERSONAS'
            )
            ->firstOrFail();

        try {
            $confirmed =
                $confirmBatch->execute(
                    $batch->id
                );
        } catch (
            InvalidImportBatchConfirmation $exception
        ) {
            return response()->json(
                [
                    'message' => $exception->getMessage(),
                ],
                409
            );
        }

        return response()->json(
            [
                'data' => $this->publicBatchData(
                    $confirmed
                ),
            ],
            202
        );
    }

    public function show(
        string $batchUuid,
        PersonImportTemplateV2 $template
    ): JsonResponse {
        $batch = ImportBatch::query()
            ->select([
                'id',
                'uuid',
                'original_filename',
                'status',
                'total_rows',
                'valid_rows',
                'invalid_rows',
                'processed_rows',
                'failed_rows',
                'confirmed_at',
                'started_at',
                'finished_at',
            ])
            ->where(
                'uuid',
                $batchUuid
            )
            ->where(
                'import_type',
                'PERSONAS'
            )
            ->firstOrFail();

        $rows = $batch
            ->rows()
            ->select([
                'row_number',
                'status',
                'raw_data',
                'normalized_data',
                'validation_errors',
                'result_data',
            ])
            ->orderBy(
                'row_number'
            )
            ->paginate(
                50
            );

        $publicRows = array_map(
            static function (
                ImportRow $row
            ) use ($template): array {
                $normalizedData =
                    $row->normalized_data;

                return [
                    'row_number' => $row->row_number,
                    'status' => $row->status->value,
                    'raw_data' => $row->raw_data,
                    'normalized_data' => $normalizedData === null
                            ? null
                            : Arr::only(
                                $normalizedData,
                                $template->columns()
                            ),
                    'validation_errors' => $row->validation_errors,
                    'result_data' => $row->result_data,
                ];
            },
            $rows->items()
        );

        return response()->json([
            'data' => $this->publicBatchData(
                $batch
            ),
            'rows' => $publicRows,
            'meta' => [
                'current_page' => $rows->currentPage(),
                'per_page' => $rows->perPage(),
                'last_page' => $rows->lastPage(),
                'total' => $rows->total(),
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function publicBatchData(
        ImportBatch $batch
    ): array {
        return [
            'uuid' => $batch->uuid,
            'original_filename' => $batch->original_filename,
            'status' => $batch->status->value,
            'total_rows' => $batch->total_rows,
            'valid_rows' => $batch->valid_rows,
            'invalid_rows' => $batch->invalid_rows,
            'processed_rows' => $batch->processed_rows,
            'failed_rows' => $batch->failed_rows,
            'confirmed_at' => $batch->confirmed_at?->toISOString(),
            'started_at' => $batch->started_at?->toISOString(),
            'finished_at' => $batch->finished_at?->toISOString(),
        ];
    }
}
