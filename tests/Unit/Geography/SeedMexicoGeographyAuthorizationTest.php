<?php

namespace Tests\Unit\Geography;

use App\Geography\SeedMexicoGeography;
use Illuminate\Database\ConnectionInterface;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class SeedMexicoGeographyAuthorizationTest extends TestCase
{
    public function test_rejects_application_connection_before_writing(): void
    {
        $connection = $this->createMock(ConnectionInterface::class);

        $connection->expects(self::once())
            ->method('selectOne')
            ->with(self::stringContains('session_user'))
            ->willReturn((object) [
                'login_role' => 'siga_app',
                'effective_role' => 'siga_app',
            ]);

        $connection->expects(self::never())
            ->method('beginTransaction');

        $connection->expects(self::never())
            ->method('statement');

        $connection->expects(self::never())
            ->method('insert');

        $connection->expects(self::never())
            ->method('commit');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('siga_migrator');

        (new SeedMexicoGeography($connection))->execute();
    }

    public function test_rejects_changed_effective_role(): void
    {
        $connection = $this->createMock(ConnectionInterface::class);

        $connection->expects(self::once())
            ->method('selectOne')
            ->with(self::stringContains('session_user'))
            ->willReturn((object) [
                'login_role' => 'siga_migrator',
                'effective_role' => 'siga_owner',
            ]);

        $connection->expects(self::never())
            ->method('beginTransaction');

        $connection->expects(self::never())
            ->method('statement');

        $connection->expects(self::never())
            ->method('insert');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('siga_migrator');

        (new SeedMexicoGeography($connection))->execute();
    }

    public function test_rejects_missing_administrative_identity(): void
    {
        $connection = $this->createMock(ConnectionInterface::class);

        $connection->expects(self::once())
            ->method('selectOne')
            ->willReturn(null);

        $connection->expects(self::never())
            ->method('beginTransaction');

        $connection->expects(self::never())
            ->method('insert');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('siga_migrator');

        (new SeedMexicoGeography($connection))->execute();
    }
}