<?php

namespace App\Actions\Importing;

use App\Models\ImportBatch;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

class CreateImportBatch
{
    private const STORAGE_DISK = 'local';

    /**
     * @param  array<string, mixed>  $options
     */
    public function execute(
        int $userId,
        string $importType,
        UploadedFile $file,
        array $options = []
    ): ImportBatch {
        $importType = trim($importType);

        if ($importType === '') {
            throw new InvalidArgumentException(
                'El tipo de importacion es obligatorio.'
            );
        }

        $sourcePath = $file->getRealPath();

        if (
            $sourcePath === false
            || ! is_file($sourcePath)
        ) {
            throw new RuntimeException(
                'No fue posible acceder al archivo de importacion.'
            );
        }

        $fileSha256 = hash_file(
            'sha256',
            $sourcePath
        );

        if ($fileSha256 === false) {
            throw new RuntimeException(
                'No fue posible calcular el hash del archivo de importacion.'
            );
        }

        $uuid = (string) Str::uuid();

        $storagePath =
            "imports/{$uuid}/source";

        $disk = Storage::disk(
            self::STORAGE_DISK
        );

        $stream = fopen(
            $sourcePath,
            'rb'
        );

        if ($stream === false) {
            throw new RuntimeException(
                'No fue posible abrir el archivo de importacion.'
            );
        }

        try {
            $stored = $disk->put(
                $storagePath,
                $stream
            );
        } finally {
            fclose($stream);
        }

        if (! $stored) {
            $disk->delete(
                $storagePath
            );

            throw new RuntimeException(
                'No fue posible almacenar el archivo de importacion.'
            );
        }

        try {
            return DB::connection()->transaction(
                function () use (
                    $userId,
                    $importType,
                    $file,
                    $options,
                    $uuid,
                    $storagePath,
                    $fileSha256
                ): ImportBatch {
                    $originalFilename = basename(
                        str_replace(
                            '\\',
                            '/',
                            $file->getClientOriginalName()
                        )
                    );

                    $batch = ImportBatch::query()->create([
                        'uuid' => $uuid,
                        'import_type' => $importType,
                        'original_filename' => $originalFilename,
                        'storage_disk' => self::STORAGE_DISK,
                        'storage_path' => $storagePath,
                        'file_sha256' => $fileSha256,
                        'options' => $options === []
                            ? null
                            : $options,
                        'created_by' => $userId,
                    ]);

                    return $batch->refresh();
                }
            );
        } catch (Throwable $error) {
            $disk->delete(
                $storagePath
            );

            throw $error;
        }
    }
}
