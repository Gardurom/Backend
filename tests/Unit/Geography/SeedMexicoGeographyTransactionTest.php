<?php

namespace Tests\Unit\Geography;

use App\Geography\SeedMexicoGeography;
use Illuminate\Database\ConnectionInterface;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class SeedMexicoGeographyTransactionTest extends TestCase
{
    public function test_rejects_existing_transaction(): void
    {
        $connection = $this->createMock(ConnectionInterface::class);

        $connection->method('selectOne')
            ->willReturn((object) [
                'login_role' => 'siga_migrator',
                'effective_role' => 'siga_migrator',
            ]);

        $connection->method('transactionLevel')->willReturn(1);

        $connection->expects(self::never())->method('beginTransaction');
        $connection->expects(self::never())->method('statement');
        $connection->expects(self::never())->method('insert');
        $connection->expects(self::never())->method('commit');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('transacción previamente abierta');

        (new SeedMexicoGeography($connection))->execute();
    }

    public function test_rolls_back_when_role_activation_fails(): void
    {
        $connection = $this->createMock(ConnectionInterface::class);

        $connection->method('selectOne')
            ->willReturn((object) [
                'login_role' => 'siga_migrator',
                'effective_role' => 'siga_migrator',
            ]);

        $connection->method('transactionLevel')
            ->willReturnOnConsecutiveCalls(0, 1);

        $connection->expects(self::once())
            ->method('beginTransaction');

        $connection->expects(self::once())
            ->method('statement')
            ->with('SET LOCAL ROLE siga_owner')
            ->willThrowException(
                new RuntimeException('Fallo al activar siga_owner')
            );

        $connection->expects(self::once())->method('rollBack');
        $connection->expects(self::never())->method('commit');
        $connection->expects(self::never())->method('insert');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Fallo al activar siga_owner');

        (new SeedMexicoGeography($connection))->execute();
    }

    public function test_acquires_lock_before_reading_geography(): void
    {
        $events = [];

        $connection = $this->createMock(ConnectionInterface::class);

        $connection->method('transactionLevel')
            ->willReturnOnConsecutiveCalls(0, 1);

        $connection->method('selectOne')
            ->willReturnCallback(
                static function (string $query) use (&$events): ?object {
                    if (str_contains($query, 'session_user')) {
                        return (object) [
                            'login_role' => 'siga_migrator',
                            'effective_role' => 'siga_migrator',
                        ];
                    }

                    if (str_contains($query, 'institutional.countries')) {
                        $events[] = 'country';

                        throw new RuntimeException('Detener después del bloqueo');
                    }

                    throw new RuntimeException('Consulta inesperada');
                }
            );

        $connection->method('beginTransaction')
            ->willReturnCallback(
                static function () use (&$events): void {
                    $events[] = 'begin';
                }
            );

        $connection->method('statement')
            ->willReturnCallback(
                static function (string $query) use (&$events): bool {
                    if ($query === 'SET LOCAL ROLE siga_owner') {
                        $events[] = 'role';

                        return true;
                    }

                    if (str_contains($query, 'pg_advisory_xact_lock')) {
                        $events[] = 'lock';

                        return true;
                    }

                    throw new RuntimeException('Sentencia inesperada');
                }
            );

        $connection->expects(self::once())
            ->method('rollBack')
            ->willReturnCallback(
                static function () use (&$events): void {
                    $events[] = 'rollback';
                }
            );

        $connection->expects(self::never())->method('commit');
        $connection->expects(self::never())->method('insert');

        try {
            (new SeedMexicoGeography($connection))->execute();

            self::fail('Se esperaba una excepción.');
        } catch (RuntimeException $error) {
            self::assertSame(
                'Detener después del bloqueo',
                $error->getMessage()
            );
        }

        self::assertSame(
            ['begin', 'role', 'lock', 'country', 'rollback'],
            $events
        );
    }
}