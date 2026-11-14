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
    public function index(Request $request): JsonResponse
    {
        $batches = ImportBatch::query()
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
                'created_at',
            ])
            ->where('import_type', 'PERSONAS')
            ->where(
                'created_by',
                (int) $request->user()->getAuthIdentifier()
            )
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(15);

        $data = $batches->getCollection()
            ->map(fn (ImportBatch $batch): array => [
                ...$this->publicBatchData($batch),
                'created_at' => $batch->created_at?->toISOString(),
            ])
            ->all();

        return response()->json([
            'data' => $data,
            'meta' => [
                'current_page' => $batches->currentPage(),
                'per_page' => $batches->perPage(),
                'last_page' => $batches->lastPage(),
                'total' => $batches->total(),
            ],
        ]);
    }
    public function store(
        Request $request,
        CreateAndValidatePersonImportBatch $createAndValidate
    ): JsonResponse {
        // SIGA_PERSONAS_PREVIEW_ENDPOINT_V1
        // Consulta previa: prohibido crear lotes o Personas.
        if ($request->input('modo') === 'PREVISUALIZAR') {
            $input = $request->validate([
                'archivo' => [
                    'required', 'file', 'extensions:xlsx',
                    'mimes:xlsx', 'max:5120',
                ],
                'modo' => ['required', 'in:PREVISUALIZAR'],
                'confirmacion' => ['prohibited'],
            ]);

            abort_unless($request->user() !== null, 401);

            $filePath = $input['archivo']->getRealPath();

            if (!is_string($filePath) || !is_file($filePath)) {
                throw ValidationException::withMessages([
                    'archivo' => 'No fue posible leer el archivo Excel.',
                ]);
            }

            return \Illuminate\Support\Facades\DB::connection()
                ->transaction(function () use (
                    $filePath,
                    $createAndValidate
                ): JsonResponse {
                    $db = \Illuminate\Support\Facades\DB::connection();
                    $db->statement('SET TRANSACTION READ ONLY');

                    $database = $db->selectOne(
                        'SELECT current_database() AS nombre'
                    );

                    if (($database->nombre ?? null) !== 'siga') {
                        throw new \RuntimeException(
                            'La previsualizacion requiere la base siga.'
                        );
                    }

                    $pipeline = app(
                        \App\Importing\Personas\PersonImportRowValidationPipeline::class
                    );

                    $rows = [];
                    $frequencies = [
                        'rfc' => [],
                        'curp' => [],
                        'correo_institucional' => [],
                    ];

                    $total = 0;

                    try {
                        foreach (
                            $createAndValidate->previewSourceRows($filePath)
                            as $source
                        ) {
                            if (++$total > 5000) {
                                throw ValidationException::withMessages([
                                    'archivo' => 'El archivo supera 5000 filas.',
                                ]);
                            }

                            $validated = $pipeline->validate(
                                $source->rawData
                            );

                            $normalized = $validated->normalizedData;

                            $identifiers = [];

                            foreach (
                                array_keys($frequencies) as $field
                            ) {
                                $value = trim(
                                    (string) ($normalized[$field] ?? '')
                                );

                                $identifiers[$field] = $value;

                                if ($value !== '') {
                                    $frequencies[$field][$value] =
                                        ($frequencies[$field][$value] ?? 0) + 1;
                                }
                            }

                            $rows[] = [
                                'fila_excel' => $source->rowNumber,
                                'valid' => $validated->isValid(),
                                'error_fields' => array_keys(
                                    $validated->errors
                                ),
                                'identifiers' => $identifiers,
                            ];
                        }
                    } catch (
                        \App\Importing\InvalidXlsxImportSource|
                        \App\Importing\Personas\InvalidPersonImportTemplate
                        $error
                    ) {
                        throw ValidationException::withMessages([
                            'archivo' => $error->getMessage(),
                        ]);
                    }

                    if ($total === 0) {
                        throw ValidationException::withMessages([
                            'archivo' => 'El Excel no contiene Personas.',
                        ]);
                    }

                    // Consultas agrupadas, evitando una consulta por fila.
                    $existing = [
                        'rfc' => [],
                        'curp' => [],
                        'correo_institucional' => [],
                    ];

                    foreach ($frequencies as $field => $values) {
                        foreach (
                            array_chunk(array_keys($values), 300)
                            as $chunk
                        ) {
                            if ($field === 'correo_institucional') {
                                $matches = $db->table(
                                    'institutional.persons'
                                )
                                    ->selectRaw(
                                        'lower(correo_institucional) AS valor'
                                    )
                                    ->whereIn(
                                        \Illuminate\Support\Facades\DB::raw(
                                            'lower(correo_institucional)'
                                        ),
                                        $chunk
                                    )
                                    ->get()
                                    ->pluck('valor');
                            } else {
                                $matches = $db->table(
                                    'institutional.persons'
                                )
                                    ->whereIn($field, $chunk)
                                    ->pluck($field);
                            }

                            foreach ($matches as $value) {
                                $existing[$field][trim((string) $value)] = true;
                            }
                        }
                    }

                    $counts = [
                        'total' => $total,
                        'validas' => 0,
                        'invalidas' => 0,
                        'candidatas_nuevas' => 0,
                        'existentes_por_identificador' => 0,
                        'duplicadas_en_excel' => 0,
                        'sin_rfc_ni_curp' => 0,
                    ];

                    $publicRows = [];

                    foreach ($rows as $row) {
                        if (!$row['valid']) {
                            $status = 'INVALIDA';
                            $counts['invalidas']++;
                        } else {
                            $counts['validas']++;

                            $ids = $row['identifiers'];

                            $duplicate = false;
                            $foundExisting = false;

                            foreach ($ids as $field => $value) {
                                if ($value === '') {
                                    continue;
                                }

                                if (
                                    ($frequencies[$field][$value] ?? 0) > 1
                                ) {
                                    $duplicate = true;
                                }

                                if (
                                    isset($existing[$field][$value])
                                ) {
                                    $foundExisting = true;
                                }
                            }

                            if (
                                $ids['rfc'] === '' &&
                                $ids['curp'] === ''
                            ) {
                                $status = 'SIN_RFC_NI_CURP';
                                $counts['sin_rfc_ni_curp']++;
                            } elseif ($duplicate) {
                                $status = 'DUPLICADA_EN_EXCEL';
                                $counts['duplicadas_en_excel']++;
                            } elseif ($foundExisting) {
                                $status = 'REVISAR_PERSONA_EXISTENTE';
                                $counts['existentes_por_identificador']++;
                            } else {
                                $status = 'CANDIDATA_NUEVA';
                                $counts['candidatas_nuevas']++;
                            }
                        }

                        // No se exponen RFC, CURP, correos ni nombres.
                        $publicRows[] = [
                            'fila_excel' => $row['fila_excel'],
                            'estado' => $status,
                            'campos_con_error' => $row['error_fields'],
                        ];
                    }

                    return response()->json([
                        'data' => [
                            'estado' => 'PERSONAS_PREVISUALIZADAS',
                            'resumen' => $counts,
                            'filas' => $publicRows,
                            'persistido' => false,
                            'confirmacion_habilitada' => false,
                        ],
                    ]);
                });
        }

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
        Request $request,
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
            ->where(
                'created_by',
                (int) $request->user()->getAuthIdentifier()
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
        Request $request,
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
            ->where(
                'created_by',
                (int) $request->user()->getAuthIdentifier()
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
