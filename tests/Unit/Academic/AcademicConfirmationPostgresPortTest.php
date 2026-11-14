<?php

declare(strict_types=1);

namespace Tests\Unit\Academic;

use App\Domain\Academic\AcademicConfirmationFingerprint;
use App\Domain\Academic\AcademicConfirmationTransactionPort;
use App\Infrastructure\Academic\AcademicConfirmationPostgresPort;
use Illuminate\Database\Connection;
use InvalidArgumentException;
use LogicException;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use UnexpectedValueException;

require_once dirname(__DIR__, 3)
    . '/app/Domain/Academic/AcademicConfirmationFingerprint.php';

require_once dirname(__DIR__, 3)
    . '/app/Domain/Academic/AcademicConfirmationTransactionPort.php';

require_once dirname(__DIR__, 3)
    . '/app/Infrastructure/Academic/'
    . 'AcademicConfirmationPostgresPort.php';

final class AcademicConfirmationPostgresPortTest extends TestCase
{
    private const OP =
        '0193a457-61b0-78a1-8f12-111111111111';

    private const COHORT =
        '0193a457-61b0-78a1-8f12-333333333333';

    private const ENROLLMENT =
        '0193a457-61b0-78a1-8f12-555555555555';

    private static function request(): AcademicConfirmationFingerprint
    {
        return AcademicConfirmationFingerprint::build(
            self::OP,
            self::COHORT,
            'C',
            'POSTERIOR',
            [self::ENROLLMENT]
        );
    }

    public function test_implements_transaction_port(): void
    {
        $db = $this->createStub(Connection::class);

        self::assertInstanceOf(
            AcademicConfirmationTransactionPort::class,
            new AcademicConfirmationPostgresPort($db)
        );
    }

    public function test_transaction_entry_fails_closed(): void
    {
        $db = $this->createMock(Connection::class);

        $db->expects(self::never())
            ->method('transaction');

        $port = new AcademicConfirmationPostgresPort($db);

        $this->expectException(LogicException::class);

        $port->transaction(
            static fn (): array => ['unexpected' => true]
        );
    }

    public function test_authorization_is_disabled(): void
    {
        $db = $this->createStub(Connection::class);
        $port = new AcademicConfirmationPostgresPort($db);

        $this->expectException(LogicException::class);

        $port->assertAuthorized(self::request());
    }

    public function test_issuance_rules_fail_closed(): void
    {
        $db = $this->createStub(Connection::class);
        $port = new AcademicConfirmationPostgresPort($db);

        $this->expectException(LogicException::class);

        $port->assertIssuanceRulesReady();
    }

    public function test_batch_validation_fails_closed(): void
    {
        $db = $this->createStub(Connection::class);
        $port = new AcademicConfirmationPostgresPort($db);

        $this->expectException(LogicException::class);

        $port->assertBatchEligibleAndOrdered(self::request());
    }

    public function test_recording_fails_closed(): void
    {
        $db = $this->createStub(Connection::class);
        $port = new AcademicConfirmationPostgresPort($db);

        $this->expectException(LogicException::class);

        $port->recordConfirmation(self::request(), []);
    }

    public function test_operation_lock_uses_transaction_advisory_lock(): void
    {
        $db = $this->createMock(Connection::class);

        $db->method('transactionLevel')->willReturn(1);

        $db->expects(self::once())
            ->method('selectOne')
            ->willReturnCallback(
                function (string $sql, array $bindings): object {
                    self::assertStringContainsString(
                        'pg_advisory_xact_lock',
                        $sql
                    );

                    self::assertStringContainsString(
                        'hashtextextended',
                        $sql
                    );

                    self::assertSame(
                        ['SIGA:H4:CONFIRMACION:'.self::OP],
                        $bindings
                    );

                    return (object) ['lock_token' => null];
                }
            );

        (new AcademicConfirmationPostgresPort($db))
            ->lockOperationKey(self::OP);
    }

    public function test_counter_creation_and_row_lock(): void
    {
        $db = $this->createMock(Connection::class);

        $db->method('transactionLevel')->willReturn(1);

        $db->expects(self::once())
            ->method('statement')
            ->willReturnCallback(
                function (string $sql, array $bindings): bool {
                    self::assertStringContainsString(
                        'ON CONFLICT (id_cohorte, tipo_participante) DO NOTHING',
                        $sql
                    );

                    self::assertSame(
                        [self::COHORT, 'C'],
                        $bindings
                    );

                    return true;
                }
            );

        $db->expects(self::once())
            ->method('selectOne')
            ->willReturnCallback(
                function (string $sql, array $bindings): object {
                    self::assertStringContainsString(
                        'FOR UPDATE',
                        $sql
                    );

                    self::assertSame(
                        [self::COHORT, 'C'],
                        $bindings
                    );

                    return (object) [
                        'id_cohorte' => self::COHORT,
                        'tipo_participante' => 'C',
                    ];
                }
            );

        (new AcademicConfirmationPostgresPort($db))
            ->lockScope(self::COHORT, 'C');
    }

    public function test_lock_requires_active_transaction(): void
    {
        $db = $this->createMock(Connection::class);

        $db->method('transactionLevel')->willReturn(0);

        $db->expects(self::never())
            ->method('statement');

        $this->expectException(LogicException::class);

        (new AcademicConfirmationPostgresPort($db))
            ->lockScope(self::COHORT, 'C');
    }

    public function test_invalid_type_does_not_execute_sql(): void
    {
        $db = $this->createMock(Connection::class);

        $db->method('transactionLevel')->willReturn(1);

        $db->expects(self::never())
            ->method('statement');

        $this->expectException(InvalidArgumentException::class);

        (new AcademicConfirmationPostgresPort($db))
            ->lockScope(self::COHORT, 'G');
    }

    public function test_operation_lookup_is_parameterized(): void
    {
        $db = $this->createMock(Connection::class);

        $db->method('transactionLevel')->willReturn(1);

        $db->expects(self::once())
            ->method('selectOne')
            ->willReturnCallback(
                function (string $sql, array $bindings): object {
                    self::assertStringContainsString(
                        'CAST(? AS uuid)',
                        $sql
                    );

                    self::assertSame([self::OP], $bindings);

                    return (object) [
                        'id_operacion' => self::OP,
                        'id_cohorte' => self::COHORT,
                        'tipo_participante' => 'C',
                        'modalidad' => 'POSTERIOR',
                        'huella_solicitud' => str_repeat('a', 64),
                        'resultado' => '{}',
                        'confirmada_at' => '2026-11-11 10:00:00+00',
                    ];
                }
            );

        $recorded = (
            new AcademicConfirmationPostgresPort($db)
        )->findOperation(self::OP);

        self::assertSame(
            self::OP,
            $recorded['id_operacion']
        );

        self::assertSame(
            'C',
            $recorded['tipo_participante']
        );
    }

    public function test_scope_state_parses_postgres_values(): void
    {
        $db = $this->createMock(Connection::class);

        $db->method('transactionLevel')->willReturn(1);

        $db->expects(self::once())
            ->method('selectOne')
            ->willReturnCallback(
                function (string $sql): object {
                    self::assertStringContainsString(
                        'FOR UPDATE',
                        $sql
                    );

                    return (object) [
                        'ultimo_consecutivo' => '1000',
                        'inicial_confirmada' => 't',
                    ];
                }
            );

        $state = (
            new AcademicConfirmationPostgresPort($db)
        )->scopeState(self::COHORT, 'C');

        self::assertSame(1000, $state['last_issued']);
        self::assertTrue($state['initial_confirmed']);
    }

    public function test_missing_counter_is_rejected(): void
    {
        $db = $this->createStub(Connection::class);

        $db->method('transactionLevel')->willReturn(1);

        $db->method('selectOne')->willReturn(null);

        $this->expectException(
            UnexpectedValueException::class
        );

        (new AcademicConfirmationPostgresPort($db))
            ->scopeState(self::COHORT, 'C');
    }

    public function test_missing_scope_lock_is_rejected(): void
    {
        $db = $this->createStub(Connection::class);

        $db->method('transactionLevel')->willReturn(1);
        $db->method('statement')->willReturn(true);
        $db->method('selectOne')->willReturn(null);

        $this->expectException(RuntimeException::class);

        (new AcademicConfirmationPostgresPort($db))
            ->lockScope(self::COHORT, 'C');
    }
}
