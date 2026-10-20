<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Importing\Personas\PersonImportTemplateV2;
use App\Models\ImportBatch;
use App\Models\ImportRow;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Arr;

final class PersonImportController extends Controller
{
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
            'data' => [
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
            ],
            'rows' => $publicRows,
            'meta' => [
                'current_page' => $rows->currentPage(),
                'per_page' => $rows->perPage(),
                'last_page' => $rows->lastPage(),
                'total' => $rows->total(),
            ],
        ]);
    }
}
