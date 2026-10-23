<?php

namespace Tests\Unit\Geography;

use App\Geography\MexicoGeographyLoader;
use App\Geography\SeedMexicoGeography;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\DatabaseManager;
use ReflectionClass;
use Tests\TestCase;

final class MexicoGeographyServiceProviderTest extends TestCase
{
    public function test_container_resolves_loader_with_default_connection(): void
    {
        $connection = $this->createStub(ConnectionInterface::class);
        $manager = $this->createMock(DatabaseManager::class);

        $manager->expects(self::once())
            ->method('connection')
            ->with(null)
            ->willReturn($connection);

        $this->app->instance('db', $manager);

        $loader = $this->app->make(MexicoGeographyLoader::class);

        self::assertInstanceOf(SeedMexicoGeography::class, $loader);

        $reflection = new ReflectionClass($loader);
        $constructor = $reflection->getConstructor();

        self::assertNotNull($constructor);

        $property = $reflection->getProperty('connection');

        self::assertSame(
            $connection,
            $property->getValue($loader)
        );
    }
}