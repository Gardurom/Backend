<?php

declare(strict_types=1);

namespace Tests\Unit\Authorization;

use App\Authorization\PermissionDecision;
use App\Authorization\PermissionResolver;
use App\Models\User;
use Illuminate\Container\Container;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Facade;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;

final class PermissionResolverIsolatedTest extends TestCase
{
    private Container $previousContainer;
    private MockInterface $database;

    protected function setUp(): void
    {
        parent::setUp();

        $this->previousContainer = Container::getInstance();

        $container = new Container();

        Container::setInstance($container);
        Facade::setFacadeApplication($container);
        Facade::clearResolvedInstances();

        $this->database = Mockery::mock();

        $container->instance('db', $this->database);
    }

    protected function tearDown(): void
    {
        try {
            Mockery::close();
        } finally {
            Facade::clearResolvedInstances();
            Facade::setFacadeApplication($this->previousContainer);
            Container::setInstance($this->previousContainer);

            parent::tearDown();
        }
    }

    public function test_allow_is_granted(): void
    {
        $this->expectResolve(['ALLOW']);

        self::assertSame(
            PermissionDecision::ALLOW,
            $this->resolver()->resolve(
                $this->user(),
                'usuarios.habilitar'
            )
        );
    }

    public function test_explicit_deny_is_returned(): void
    {
        $this->expectResolve(['DENY']);

        self::assertSame(
            PermissionDecision::DENY,
            $this->resolver()->resolve(
                $this->user(),
                'usuarios.habilitar'
            )
        );
    }

    public function test_deny_overrides_allow_regardless_of_order(): void
    {
        $first = $this->resolveQuery(['ALLOW', 'DENY']);
        $second = $this->resolveQuery(['DENY', 'ALLOW']);

        $this->database
            ->shouldReceive('table')
            ->twice()
            ->with('system.user_roles as ur')
            ->andReturn($first, $second);

        self::assertSame(
            PermissionDecision::DENY,
            $this->resolver()->resolve(
                $this->user(),
                'usuarios.habilitar'
            )
        );

        self::assertSame(
            PermissionDecision::DENY,
            $this->resolver()->resolve(
                $this->user(),
                'usuarios.habilitar'
            )
        );
    }

    public function test_no_rule_when_user_has_no_matching_effects(): void
    {
        $this->expectResolve([]);

        self::assertSame(
            PermissionDecision::NO_RULE,
            $this->resolver()->resolve(
                $this->user(),
                'usuarios.habilitar'
            )
        );
    }

    public function test_unknown_effect_does_not_grant_access(): void
    {
        $this->expectResolve(['PENDING']);

        self::assertSame(
            PermissionDecision::NO_RULE,
            $this->resolver()->resolve(
                $this->user(),
                'usuarios.habilitar'
            )
        );
    }

    public function test_allows_is_true_only_for_allow(): void
    {
        $this->expectResolve(['ALLOW']);

        self::assertTrue(
            $this->resolver()->allows(
                $this->user(),
                'usuarios.habilitar'
            )
        );
    }

    public function test_allows_is_false_for_deny(): void
    {
        $this->expectResolve(['ALLOW', 'DENY']);

        self::assertFalse(
            $this->resolver()->allows(
                $this->user(),
                'usuarios.habilitar'
            )
        );
    }

    public function test_allows_is_false_for_no_rule(): void
    {
        $this->expectResolve([]);

        self::assertFalse(
            $this->resolver()->allows(
                $this->user(),
                'usuarios.habilitar'
            )
        );
    }

    public function test_rechecks_after_simulated_role_removal(): void
    {
        $first = $this->resolveQuery(['ALLOW']);
        $second = $this->resolveQuery([]);

        $this->database
            ->shouldReceive('table')
            ->twice()
            ->with('system.user_roles as ur')
            ->andReturn($first, $second);

        $resolver = $this->resolver();
        $user = $this->user();

        self::assertTrue(
            $resolver->allows($user, 'usuarios.habilitar')
        );

        self::assertFalse(
            $resolver->allows($user, 'usuarios.habilitar')
        );
    }

    public function test_rechecks_after_simulated_deny(): void
    {
        $first = $this->resolveQuery(['ALLOW']);
        $second = $this->resolveQuery(['ALLOW', 'DENY']);

        $this->database
            ->shouldReceive('table')
            ->twice()
            ->with('system.user_roles as ur')
            ->andReturn($first, $second);

        $resolver = $this->resolver();
        $user = $this->user();

        self::assertTrue(
            $resolver->allows($user, 'usuarios.habilitar')
        );

        self::assertFalse(
            $resolver->allows($user, 'usuarios.habilitar')
        );
    }

    public function test_rechecks_after_simulated_grant(): void
    {
        $first = $this->resolveQuery([]);
        $second = $this->resolveQuery(['ALLOW']);

        $this->database
            ->shouldReceive('table')
            ->twice()
            ->with('system.user_roles as ur')
            ->andReturn($first, $second);

        $resolver = $this->resolver();
        $user = $this->user();

        self::assertFalse(
            $resolver->allows($user, 'usuarios.habilitar')
        );

        self::assertTrue(
            $resolver->allows($user, 'usuarios.habilitar')
        );
    }

    public function test_effective_permissions_exclude_any_denied_code(): void
    {
        $rows = [
            ['code' => 'personas.crear', 'effect' => 'ALLOW'],
            ['code' => 'personas.ver', 'effect' => 'ALLOW'],
            ['code' => 'personas.ver', 'effect' => 'DENY'],
            ['code' => 'usuarios.habilitar', 'effect' => 'ALLOW'],
            ['code' => 'usuarios.habilitar', 'effect' => 'ALLOW'],
            ['code' => 'usuarios.deshabilitar', 'effect' => 'DENY'],
        ];

        $this->expectEffectivePermissions($rows);

        self::assertSame(
            ['personas.crear', 'usuarios.habilitar'],
            $this->resolver()->effectivePermissions($this->user())
        );
    }

    public function test_effective_permissions_are_empty_when_unassigned(): void
    {
        $this->expectEffectivePermissions([]);

        self::assertSame(
            [],
            $this->resolver()->effectivePermissions($this->user())
        );
    }

    private function user(): User
    {
        $user = new User();

        $user->setRawAttributes(['id' => 1093], true);

        return $user;
    }

    private function resolver(): PermissionResolver
    {
        return new PermissionResolver();
    }

    private function baseQuery(): MockInterface
    {
        $builder = Mockery::mock();

        $builder->shouldReceive('join')->once()->with(
            'system.role_permissions as rp',
            'rp.role_id',
            '=',
            'ur.role_id'
        )->andReturnSelf();

        $builder->shouldReceive('join')->once()->with(
            'system.permissions as p',
            'p.id',
            '=',
            'rp.permission_id'
        )->andReturnSelf();

        $builder->shouldReceive('where')->once()->with(
            'ur.user_id',
            1093
        )->andReturnSelf();

        return $builder;
    }

    private function resolveQuery(array $effects): MockInterface
    {
        $builder = $this->baseQuery();

        $builder->shouldReceive('where')->once()->with(
            'p.code',
            'usuarios.habilitar'
        )->andReturnSelf();

        $builder->shouldReceive('pluck')->once()
            ->with('rp.effect')
            ->andReturn(new Collection($effects));

        return $builder;
    }

    private function expectResolve(array $effects): void
    {
        $builder = $this->resolveQuery($effects);

        $this->database->shouldReceive('table')
            ->once()
            ->with('system.user_roles as ur')
            ->andReturn($builder);
    }

    private function expectEffectivePermissions(array $rows): void
    {
        $builder = $this->baseQuery();

        $builder->shouldReceive('orderBy')
            ->once()
            ->with('p.code')
            ->andReturnSelf();

        $items = array_map(
            static fn (array $row): object => (object) $row,
            $rows
        );

        $builder->shouldReceive('get')->once()
            ->with(['p.code', 'rp.effect'])
            ->andReturn(new Collection($items));

        $this->database->shouldReceive('table')
            ->once()
            ->with('system.user_roles as ur')
            ->andReturn($builder);
    }
}