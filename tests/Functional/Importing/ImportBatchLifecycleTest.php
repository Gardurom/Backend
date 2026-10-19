<?php

namespace Tests\Functional\Importing;

use App\Actions\Importing\TransitionImportBatch;
use App\Importing\ImportBatchStatus;
use App\Importing\InvalidImportBatchTransition;
use App\Models\ImportBatch;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\Functional\FunctionalTestCase;

class ImportBatchLifecycleTest extends FunctionalTestCase
{
    public function test_batch_status_exposes_approved_transition_graph(): void
    {
        $allowed = [
            ImportBatchStatus::UPLOADED->value => [
                ImportBatchStatus::VALIDATING,
                ImportBatchStatus::CANCELLED,
            ],
            ImportBatchStatus::VALIDATING->value => [
                ImportBatchStatus::READY,
                ImportBatchStatus::FAILED,
                ImportBatchStatus::CANCELLED,
            ],
            ImportBatchStatus::READY->value => [
                ImportBatchStatus::QUEUED,
                ImportBatchStatus::CANCELLED,
            ],
            ImportBatchStatus::QUEUED->value => [
                ImportBatchStatus::PROCESSING,
                ImportBatchStatus::FAILED,
                ImportBatchStatus::CANCELLED,
            ],
            ImportBatchStatus::PROCESSING->value => [
                ImportBatchStatus::COMPLETED,
                ImportBatchStatus::FAILED,
            ],
            ImportBatchStatus::COMPLETED->value => [],
            ImportBatchStatus::FAILED->value => [],
            ImportBatchStatus::CANCELLED->value => [],
        ];

        foreach (ImportBatchStatus::cases() as $from) {
            foreach (ImportBatchStatus::cases() as $to) {
                $expected = in_array(
                    $to,
                    $allowed[$from->value],
                    true
                );

                self::assertSame(
                    $expected,
                    $from->canTransitionTo($to),
                    "{$from->value} -> {$to->value}"
                );
            }
        }
    }

    public function test_transition_action_persists_happy_path_and_timestamps(): void
    {
        $batch = $this->createBatch();
        $action = new TransitionImportBatch;

        $batch->refresh();

        self::assertSame(
            ImportBatchStatus::UPLOADED,
            $batch->status
        );

        self::assertNull($batch->confirmed_at);
        self::assertNull($batch->started_at);
        self::assertNull($batch->finished_at);

        $validating = $action->execute(
            $batch->id,
            ImportBatchStatus::VALIDATING
        );

        self::assertSame(
            ImportBatchStatus::VALIDATING,
            $validating->status
        );

        self::assertNull($validating->confirmed_at);
        self::assertNull($validating->started_at);
        self::assertNull($validating->finished_at);

        $ready = $action->execute(
            $batch->id,
            ImportBatchStatus::READY
        );

        self::assertSame(
            ImportBatchStatus::READY,
            $ready->status
        );

        self::assertNull($ready->confirmed_at);
        self::assertNull($ready->started_at);
        self::assertNull($ready->finished_at);

        $queued = $action->execute(
            $batch->id,
            ImportBatchStatus::QUEUED
        );

        self::assertSame(
            ImportBatchStatus::QUEUED,
            $queued->status
        );

        self::assertInstanceOf(
            CarbonInterface::class,
            $queued->confirmed_at
        );

        self::assertNull($queued->started_at);
        self::assertNull($queued->finished_at);

        $confirmedAt = $queued->confirmed_at->copy();

        $processing = $action->execute(
            $batch->id,
            ImportBatchStatus::PROCESSING
        );

        self::assertSame(
            ImportBatchStatus::PROCESSING,
            $processing->status
        );

        self::assertTrue(
            $processing->confirmed_at->equalTo(
                $confirmedAt
            )
        );

        self::assertInstanceOf(
            CarbonInterface::class,
            $processing->started_at
        );

        self::assertNull($processing->finished_at);

        $startedAt = $processing->started_at->copy();

        $completed = $action->execute(
            $batch->id,
            ImportBatchStatus::COMPLETED
        );

        self::assertSame(
            ImportBatchStatus::COMPLETED,
            $completed->status
        );

        self::assertTrue(
            $completed->confirmed_at->equalTo(
                $confirmedAt
            )
        );

        self::assertTrue(
            $completed->started_at->equalTo(
                $startedAt
            )
        );

        self::assertInstanceOf(
            CarbonInterface::class,
            $completed->finished_at
        );
    }

    public function test_failed_validation_finishes_without_confirmation_or_processing(): void
    {
        $batch = $this->createBatch();
        $action = new TransitionImportBatch;

        $action->execute(
            $batch->id,
            ImportBatchStatus::VALIDATING
        );

        $failed = $action->execute(
            $batch->id,
            ImportBatchStatus::FAILED
        );

        self::assertSame(
            ImportBatchStatus::FAILED,
            $failed->status
        );

        self::assertNull($failed->confirmed_at);
        self::assertNull($failed->started_at);

        self::assertInstanceOf(
            CarbonInterface::class,
            $failed->finished_at
        );
    }

    public function test_ready_batch_can_be_cancelled_as_terminal_state(): void
    {
        $batch = $this->createBatch();
        $action = new TransitionImportBatch;

        $action->execute(
            $batch->id,
            ImportBatchStatus::VALIDATING
        );

        $action->execute(
            $batch->id,
            ImportBatchStatus::READY
        );

        $cancelled = $action->execute(
            $batch->id,
            ImportBatchStatus::CANCELLED
        );

        self::assertSame(
            ImportBatchStatus::CANCELLED,
            $cancelled->status
        );

        self::assertNull($cancelled->confirmed_at);
        self::assertNull($cancelled->started_at);

        self::assertInstanceOf(
            CarbonInterface::class,
            $cancelled->finished_at
        );
    }

    public function test_invalid_transition_is_rejected_without_persisting_changes(): void
    {
        $batch = $this->createBatch();
        $action = new TransitionImportBatch;

        try {
            $action->execute(
                $batch->id,
                ImportBatchStatus::COMPLETED
            );

            self::fail(
                'UPLOADED no debe transicionar directamente a COMPLETED.'
            );
        } catch (InvalidImportBatchTransition) {
        }

        $batch->refresh();

        self::assertSame(
            ImportBatchStatus::UPLOADED,
            $batch->status
        );

        self::assertNull($batch->confirmed_at);
        self::assertNull($batch->started_at);
        self::assertNull($batch->finished_at);
    }

    public function test_same_state_transition_is_rejected(): void
    {
        $batch = $this->createBatch();
        $action = new TransitionImportBatch;

        $this->expectException(
            InvalidImportBatchTransition::class
        );

        $action->execute(
            $batch->id,
            ImportBatchStatus::UPLOADED
        );
    }

    private function createBatch(): ImportBatch
    {
        $userId = (int) $this->db
            ->table('system.users')
            ->insertGetId([
                'name' => 'USUARIO CICLO IMPORTACION',
                'email' => Str::uuid()
                    .'@lifecycle.siga.test',
                'password' => Hash::make(
                    'ClaveSegura123!'
                ),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

        return ImportBatch::query()->create([
            'uuid' => (string) Str::uuid(),
            'import_type' => 'PERSONAS',
            'original_filename' => 'personas.xlsx',
            'storage_disk' => 'local',
            'storage_path' => 'imports/personas.xlsx',
            'file_sha256' => hash(
                'sha256',
                Str::uuid()->toString()
            ),
            'created_by' => $userId,
        ]);
    }
}
