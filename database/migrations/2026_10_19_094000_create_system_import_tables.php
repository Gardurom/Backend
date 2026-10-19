<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('SET ROLE siga_owner');

        try {
            Schema::create(
                'system.import_batches',
                function (Blueprint $table) {
                    $table->bigIncrements('id');

                    $table->uuid('uuid');

                    $table->string(
                        'import_type',
                        100
                    );

                    $table->string(
                        'status',
                        20
                    )->default('UPLOADED');

                    $table->string(
                        'original_filename',
                        255
                    );

                    $table->string(
                        'storage_disk',
                        50
                    );

                    $table->string(
                        'storage_path',
                        1024
                    );

                    $table->char(
                        'file_sha256',
                        64
                    );

                    $table
                        ->jsonb('options')
                        ->nullable();

                    $table
                        ->unsignedInteger('total_rows')
                        ->default(0);

                    $table
                        ->unsignedInteger('valid_rows')
                        ->default(0);

                    $table
                        ->unsignedInteger('invalid_rows')
                        ->default(0);

                    $table
                        ->unsignedInteger('processed_rows')
                        ->default(0);

                    $table
                        ->unsignedInteger('failed_rows')
                        ->default(0);

                    $table->unsignedBigInteger(
                        'created_by'
                    );

                    $table
                        ->timestampTz('confirmed_at')
                        ->nullable();

                    $table
                        ->timestampTz('started_at')
                        ->nullable();

                    $table
                        ->timestampTz('finished_at')
                        ->nullable();

                    $table->timestampsTz();

                    $table->unique(
                        'uuid',
                        'import_batches_uuid_unique'
                    );

                    $table->foreign(
                        'created_by',
                        'import_batches_created_by_fk'
                    )
                        ->references('id')
                        ->on('system.users')
                        ->restrictOnDelete()
                        ->restrictOnUpdate();

                    $table->index(
                        [
                            'import_type',
                            'status',
                        ],
                        'import_batches_type_status_index'
                    );

                    $table->index(
                        'created_by',
                        'import_batches_created_by_index'
                    );
                }
            );

            DB::statement(
                "ALTER TABLE system.import_batches
                 ADD CONSTRAINT import_batches_status_check
                 CHECK (
                    status IN (
                        'UPLOADED',
                        'VALIDATING',
                        'READY',
                        'QUEUED',
                        'PROCESSING',
                        'COMPLETED',
                        'FAILED',
                        'CANCELLED'
                    )
                 )"
            );

            Schema::create(
                'system.import_rows',
                function (Blueprint $table) {
                    $table->bigIncrements('id');

                    $table->unsignedBigInteger(
                        'import_batch_id'
                    );

                    $table->unsignedInteger(
                        'row_number'
                    );

                    $table->string(
                        'status',
                        20
                    )->default('PENDING');

                    $table->jsonb('raw_data');

                    $table
                        ->jsonb('normalized_data')
                        ->nullable();

                    $table
                        ->jsonb('validation_errors')
                        ->nullable();

                    $table
                        ->jsonb('result_data')
                        ->nullable();

                    $table->timestampsTz();

                    $table->foreign(
                        'import_batch_id',
                        'import_rows_batch_fk'
                    )
                        ->references('id')
                        ->on('system.import_batches')
                        ->restrictOnDelete()
                        ->restrictOnUpdate();

                    $table->unique(
                        [
                            'import_batch_id',
                            'row_number',
                        ],
                        'import_rows_batch_row_unique'
                    );

                    $table->index(
                        [
                            'import_batch_id',
                            'status',
                        ],
                        'import_rows_batch_status_index'
                    );
                }
            );

            DB::statement(
                "ALTER TABLE system.import_rows
                 ADD CONSTRAINT import_rows_status_check
                 CHECK (
                    status IN (
                        'PENDING',
                        'VALID',
                        'INVALID',
                        'PROCESSED',
                        'FAILED',
                        'SKIPPED'
                    )
                 )"
            );

            DB::statement(
                'REVOKE ALL PRIVILEGES
                 ON TABLE
                    system.import_batches,
                    system.import_rows
                 FROM PUBLIC'
            );

            DB::statement(
                'REVOKE ALL PRIVILEGES
                 ON TABLE
                    system.import_batches,
                    system.import_rows
                 FROM siga_app'
            );

            DB::statement(
                'GRANT SELECT, INSERT, UPDATE
                 ON TABLE
                    system.import_batches,
                    system.import_rows
                 TO siga_app'
            );

            DB::statement(
                'REVOKE ALL PRIVILEGES
                 ON SEQUENCE
                    system.import_batches_id_seq,
                    system.import_rows_id_seq
                 FROM PUBLIC'
            );

            DB::statement(
                'REVOKE ALL PRIVILEGES
                 ON SEQUENCE
                    system.import_batches_id_seq,
                    system.import_rows_id_seq
                 FROM siga_app'
            );

            DB::statement(
                'GRANT USAGE
                 ON SEQUENCE
                    system.import_batches_id_seq,
                    system.import_rows_id_seq
                 TO siga_app'
            );
        } finally {
            DB::statement('RESET ROLE');
        }
    }

    public function down(): void
    {
        DB::statement('SET ROLE siga_owner');

        try {
            Schema::dropIfExists(
                'system.import_rows'
            );

            Schema::dropIfExists(
                'system.import_batches'
            );
        } finally {
            DB::statement('RESET ROLE');
        }
    }
};
