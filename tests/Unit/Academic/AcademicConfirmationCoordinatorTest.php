<?php

declare(strict_types=1);

namespace Tests\Unit\Academic;

use App\Domain\Academic\AcademicConfirmationCoordinator;
use App\Domain\Academic\AcademicConfirmationFingerprint;
use App\Domain\Academic\AcademicConfirmationReplayPolicy;
use App\Domain\Academic\AcademicConfirmationTransactionPort;
use App\Domain\Academic\AcademicEnrollmentSequencePreview;
use DomainException;
use LogicException;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Throwable;
use UnexpectedValueException;

require_once dirname(__DIR__, 3)
    . '/app/Domain/Academic/AcademicConfirmationFingerprint.php';

require_once dirname(__DIR__, 3)
    . '/app/Domain/Academic/AcademicConfirmationReplayPolicy.php';

require_once dirname(__DIR__, 3)
    . '/app/Domain/Academic/AcademicEnrollmentSequencePreview.php';

require_once dirname(__DIR__, 3)
    . '/app/Domain/Academic/AcademicConfirmationTransactionPort.php';

require_once dirname(__DIR__, 3)
    . '/app/Domain/Academic/AcademicConfirmationCoordinator.php';

/**
 * Simulador exclusivo de esta prueba.
 * No conecta ni representa transacciones reales PostgreSQL.
 */
final class InMemoryAcademicConfirmationPort
    implements AcademicConfirmationTransactionPort
{
    public bool $authorized = false;
    public bool $ready = false;
    public bool $batchEligible = false;
    public bool $failAfterFirstIssue = false;

    public array $approvedInitialOrder = [];
    public array $allowedEnrollmentIds = [];

    public array $counters = [];
    public array $operations = [];
    public array $issuances = [];
    public array $events = [];

    public function transaction(callable $work): array
    {
        $before = [
            $this->counters,
            $this->operations,
            $this->issuances,
        ];

        $this->events[] = 'begin';

        try {
            $result = $work();

            $this->events[] = 'commit';

            return $result;
        } catch (Throwable $error) {
            [
                $this->counters,
                $this->operations,
                $this->issuances
            ] = $before;

            $this->events[] = 'rollback';

            throw $error;
        }
    }

    public function assertAuthorized(
        AcademicConfirmationFingerprint $request
    ): void {
        $this->events[] = 'authorize';

        if (!$this->authorized) {
            throw new RuntimeException(
                'SIGA H4: acceso no autorizado.'
            );
        }
    }

    public function lockOperationKey(string $operationId): void
    {
        $this->events[] = 'lock-operation';
    }

    public function lockScope(
        string $cohortId,
        string $participantType
    ): void {
        $this->events[] = 'lock-scope';

        $key = $cohortId.'|'.$participantType;

        $this->counters[$key] ??= [
            'last_issued' => 0,
            'initial_confirmed' => false,
        ];
    }

    public function findOperation(
        string $operationId
    ): ?array {
        $this->events[] = 'find-operation';

        return $this->operations[$operationId] ?? null;
    }

    public function assertIssuanceRulesReady(): void
    {
        $this->events[] = 'ready';

        if (!$this->ready) {
            throw new LogicException(
                'SIGA H4: reglas de emision pendientes.'
            );
        }
    }

    public function assertBatchEligibleAndOrdered(
        AcademicConfirmationFingerprint $request
    ): void {
        $this->events[] = 'validate-batch';

        if (!$this->batchEligible) {
            throw new DomainException(
                'SIGA H4: lote no elegible.'
            );
        }

        foreach ($request->orderedEnrollmentIds as $id) {
            if (!in_array(
                $id,
                $this->allowedEnrollmentIds,
                true
            )) {
                throw new DomainException(
                    'SIGA H4: incorporacion no autorizada.'
                );
            }
        }

        if (
            $request->mode === 'INICIAL'
            && $request->orderedEnrollmentIds
                !== $this->approvedInitialOrder
        ) {
            throw new LogicException(
                'SIGA H4: orden M09 no acreditado.'
            );
        }
    }

    public function scopeState(
        string $cohortId,
        string $participantType
    ): array {
        $this->events[] = 'state';

        return $this->counters[
            $cohortId.'|'.$participantType
        ];
    }

    public function recordConfirmation(
        AcademicConfirmationFingerprint $request,
        array $assignments
    ): void {
        $this->events[] = 'record';

        $scope = $request->cohortId
            .'|'
            .$request->participantType;

        foreach ($assignments as $assignment) {
            $id = $assignment['id_incorporacion'];

            if (isset($this->issuances[$id])) {
                throw new DomainException(
                    'SIGA H4: incorporacion ya emitida.'
                );
            }

            $this->issuances[$id] = $assignment;

            if ($this->failAfterFirstIssue) {
                throw new RuntimeException(
                    'SIGA H4: fallo sintetico de escritura.'
                );
            }
        }

        $this->counters[$scope]['last_issued'] =
            $assignments[
                count($assignments) - 1
            ]['consecutivo'];

        if ($request->mode === 'INICIAL') {
            $this->counters[$scope]['initial_confirmed'] = true;
        }

        $this->operations[$request->operationId] = [
            'id_operacion' => $request->operationId,
            'id_cohorte' => $request->cohortId,
            'tipo_participante' => $request->participantType,
            'modalidad' => $request->mode,
            'huella_solicitud' => $request->fingerprint,
            'resultado' => [
                'cantidad' => count($assignments),
            ],
            'confirmada_at' => '2026-11-11T10:00:00+00:00',
        ];
    }
}

final class AcademicConfirmationCoordinatorTest extends TestCase
{
    private const OP =
        '0193a457-61b0-78a1-8f12-111111111111';

    private const OP2 =
        '0193a457-61b0-78a1-8f12-222222222222';

    private const COHORT =
        '0193a457-61b0-78a1-8f12-333333333333';

    private const COHORT2 =
        '0193a457-61b0-78a1-8f12-444444444444';

    private const A =
        '0193a457-61b0-78a1-8f12-555555555555';

    private const B =
        '0193a457-61b0-78a1-8f12-666666666666';

    private const C =
        '0193a457-61b0-78a1-8f12-777777777777';

    private static function request(
        string $operation = self::OP,
        string $cohort = self::COHORT,
        string $type = 'C',
        string $mode = 'INICIAL',
        array $ids = [self::A, self::B]
    ): AcademicConfirmationFingerprint {
        return AcademicConfirmationFingerprint::build(
            $operation,
            $cohort,
            $type,
            $mode,
            $ids
        );
    }

    private static function port():
        InMemoryAcademicConfirmationPort
    {
        $port = new InMemoryAcademicConfirmationPort();

        $port->authorized = true;
        $port->ready = true;
        $port->batchEligible = true;

        $port->approvedInitialOrder = [
            self::A,
            self::B,
        ];

        $port->allowedEnrollmentIds = [
            self::A,
            self::B,
            self::C,
        ];

        return $port;
    }

    private static function confirmInitial(
        InMemoryAcademicConfirmationPort $port
    ): array {
        return (
            new AcademicConfirmationCoordinator($port)
        )->confirm(self::request());
    }

    private static function scopeKey(): string
    {
        return self::COHORT.'|C';
    }

    public function test_initial_confirm_records_one_atomic_batch(): void
    {
        $port = self::port();

        $receipt = self::confirmInitial($port);

        self::assertSame('CONFIRMED', $receipt['status']);
        self::assertSame(2, $receipt['cantidad']);

        self::assertSame(
            2,
            $port->counters[self::scopeKey()]['last_issued']
        );

        self::assertTrue(
            $port->counters[self::scopeKey()]['initial_confirmed']
        );

        self::assertCount(2, $port->issuances);
        self::assertCount(1, $port->operations);
    }

    public function test_identical_retry_does_not_advance_counter(): void
    {
        $port = self::port();

        self::confirmInitial($port);

        $result = self::confirmInitial($port);

        self::assertSame('REPLAY', $result['status']);
        self::assertNull($result['cantidad']);

        self::assertSame(
            2,
            $port->counters[self::scopeKey()]['last_issued']
        );

        self::assertCount(1, $port->operations);
        self::assertCount(2, $port->issuances);
    }

    public function test_same_operation_with_changed_list_is_conflict(): void
    {
        $port = self::port();

        self::confirmInitial($port);

        $this->expectException(DomainException::class);

        (
            new AcademicConfirmationCoordinator($port)
        )->confirm(
            self::request(
                self::OP,
                self::COHORT,
                'C',
                'INICIAL',
                [self::B, self::A]
            )
        );
    }

    public function test_same_operation_in_another_scope_is_conflict(): void
    {
        $port = self::port();

        self::confirmInitial($port);

        $this->expectException(DomainException::class);

        (
            new AcademicConfirmationCoordinator($port)
        )->confirm(
            self::request(
                self::OP,
                self::COHORT2,
                'I'
            )
        );
    }

    public function test_cannot_confirm_initial_twice_with_new_operation(): void
    {
        $port = self::port();

        self::confirmInitial($port);

        $this->expectException(LogicException::class);

        (
            new AcademicConfirmationCoordinator($port)
        )->confirm(self::request(self::OP2));
    }

    public function test_posterior_requires_initial_confirmation(): void
    {
        $port = self::port();

        $this->expectException(LogicException::class);

        (
            new AcademicConfirmationCoordinator($port)
        )->confirm(
            self::request(
                self::OP2,
                self::COHORT,
                'C',
                'POSTERIOR',
                [self::C]
            )
        );
    }

    public function test_posterior_uses_next_number_after_initial(): void
    {
        $port = self::port();

        self::confirmInitial($port);

        $result = (
            new AcademicConfirmationCoordinator($port)
        )->confirm(
            self::request(
                self::OP2,
                self::COHORT,
                'C',
                'POSTERIOR',
                [self::C]
            )
        );

        self::assertSame('CONFIRMED', $result['status']);

        self::assertSame(
            3,
            $port->issuances[self::C]['consecutivo']
        );

        self::assertSame(
            3,
            $port->counters[self::scopeKey()]['last_issued']
        );
    }

    public function test_unauthorized_user_cannot_start_operation(): void
    {
        $port = self::port();
        $port->authorized = false;

        $this->expectException(RuntimeException::class);

        self::confirmInitial($port);
    }

    public function test_revoked_user_cannot_replay(): void
    {
        $port = self::port();

        self::confirmInitial($port);

        $port->authorized = false;

        $this->expectException(RuntimeException::class);

        self::confirmInitial($port);
    }

    public function test_unratified_issuance_policy_fails_closed(): void
    {
        $port = self::port();
        $port->ready = false;

        $this->expectException(LogicException::class);

        self::confirmInitial($port);
    }

    public function test_initial_requires_server_validated_order(): void
    {
        $port = self::port();

        $port->approvedInitialOrder = [
            self::B,
            self::A,
        ];

        $this->expectException(LogicException::class);

        self::confirmInitial($port);
    }

    public function test_batch_with_unknown_enrollment_is_rejected(): void
    {
        $port = self::port();
        $port->allowedEnrollmentIds = [self::B];

        $this->expectException(DomainException::class);

        self::confirmInitial($port);
    }

    public function test_partial_simulated_write_is_rolled_back(): void
    {
        $port = self::port();
        $port->failAfterFirstIssue = true;

        try {
            self::confirmInitial($port);

            self::fail(
                'Debio fallar la escritura sintetica.'
            );
        } catch (RuntimeException $error) {
            self::assertSame([], $port->counters);
            self::assertSame([], $port->operations);
            self::assertSame([], $port->issuances);

            self::assertContains(
                'rollback',
                $port->events
            );
        }
    }

    public function test_same_enrollment_cannot_be_issued_twice(): void
    {
        $port = self::port();

        self::confirmInitial($port);

        try {
            (
                new AcademicConfirmationCoordinator($port)
            )->confirm(
                self::request(
                    self::OP2,
                    self::COHORT,
                    'C',
                    'POSTERIOR',
                    [self::A]
                )
            );

            self::fail(
                'Debio rechazar la incorporacion duplicada.'
            );
        } catch (DomainException $error) {
            self::assertSame(
                2,
                $port->counters[self::scopeKey()]['last_issued']
            );

            self::assertCount(1, $port->operations);
        }
    }

    public function test_lock_order_is_operation_then_scope(): void
    {
        $port = self::port();

        self::confirmInitial($port);

        self::assertSame(
            [
                'begin',
                'authorize',
                'lock-operation',
                'lock-scope',
                'find-operation',
            ],
            array_slice($port->events, 0, 5)
        );
    }

    public function test_inconsistent_counter_state_fails_closed(): void
    {
        $port = self::port();

        $port->counters[self::scopeKey()] = [
            'last_issued' => 5,
            'initial_confirmed' => false,
        ];

        $this->expectException(
            UnexpectedValueException::class
        );

        self::confirmInitial($port);
    }

    public function test_failed_operation_can_be_retried_without_consumption(): void
    {
        $port = self::port();
        $port->failAfterFirstIssue = true;

        try {
            self::confirmInitial($port);
        } catch (RuntimeException $error) {
            // Fallo sintetico controlado.
        }

        $port->failAfterFirstIssue = false;

        $receipt = self::confirmInitial($port);

        self::assertSame(
            'CONFIRMED',
            $receipt['status']
        );

        self::assertSame(
            2,
            $port->counters[self::scopeKey()]['last_issued']
        );

        self::assertCount(2, $port->issuances);
    }
}
