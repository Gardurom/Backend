<?php

namespace Tests\Unit\Access;

use App\Access\AccessEligibility;
use App\Models\User;
use Illuminate\Container\Container;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Facade;
use Mockery;
use PHPUnit\Framework\TestCase;

final class AccessEligibilityTest extends TestCase
{
    private Container $previousContainer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->previousContainer = Container::getInstance();
        $container = new Container();

        Container::setInstance($container);
        Facade::setFacadeApplication($container);
        Facade::clearResolvedInstances();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        Facade::clearResolvedInstances();
        Facade::setFacadeApplication($this->previousContainer);
        Container::setInstance($this->previousContainer);

        parent::tearDown();
    }

    public function test_enabled_user_with_active_person_is_eligible(): void
    {
        $user = $this->user('HABILITADO', 'ACTIVO');

        $query = Mockery::mock(\stdClass::class);
        $query->shouldReceive('where')->once()->andReturnSelf();
        $query->shouldReceive('first')->once()->andReturn((object) [
            'access_security_blocked' => false,
            'access_suspended_until' => null,
            'access_valid_from' => null,
            'access_valid_until' => null,
        ]);

        $database = Mockery::mock(\stdClass::class);
        $database->shouldReceive('table')
            ->once()
            ->with('system.users')
            ->andReturn($query);

        Container::getInstance()->instance('db', $database);

        self::assertTrue((new AccessEligibility())->allows($user));
    }

    public function test_pending_user_is_not_eligible(): void
    {
        $user = $this->user('PENDIENTE');

        self::assertFalse((new AccessEligibility())->allows($user));
    }

    public function test_disabled_user_is_not_eligible(): void
    {
        $user = $this->user('DESHABILITADO');

        self::assertFalse((new AccessEligibility())->allows($user));
    }

    public function test_user_without_person_is_not_eligible(): void
    {
        $user = $this->user('HABILITADO');

        self::assertFalse((new AccessEligibility())->allows($user));
    }

    public function test_enabled_user_with_inactive_person_is_not_eligible(): void
    {
        $user = $this->user('HABILITADO', 'INACTIVO');

        self::assertFalse((new AccessEligibility())->allows($user));
    }

    public function test_enabled_user_with_withdrawn_person_is_not_eligible(): void
    {
        $user = $this->user('HABILITADO', 'BAJA');

        self::assertFalse((new AccessEligibility())->allows($user));
    }

    private function user(
        string $status,
        ?string $personStatus = null
    ): User {
        $user = Mockery::mock(User::class)->makePartial();

        $user->setRawAttributes([
            'access_status' => $status,
            'id_persona' => $personStatus === null
                ? null
                : '01a13ae1-546d-71b8-bbbf-18e7bccc1d45',
        ], true);

        if ($status === 'HABILITADO' && $personStatus !== null) {
            $relation = Mockery::mock(BelongsTo::class);

            $relation->shouldReceive('where')
                ->once()
                ->with('estatus', 'ACTIVO')
                ->andReturnSelf();

            $relation->shouldReceive('exists')
                ->once()
                ->andReturn($personStatus === 'ACTIVO');

            $user->shouldReceive('person')
                ->once()
                ->andReturn($relation);
        }

        return $user;
    }
}