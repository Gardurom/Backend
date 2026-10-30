<?php

namespace Tests\Unit\Access;

use App\Http\Middleware\EnsureAccessEligibility;
use App\Models\User;
use Illuminate\Container\Container;
use Illuminate\Http\Request;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Session\Store;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Auth;
use Mockery;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Response;

final class EnsureAccessEligibilityTest extends TestCase
{
    private ?Container $previousContainer = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->previousContainer = Container::getInstance();

        $container = new Container();
        Container::setInstance($container);
        Facade::setFacadeApplication($container);
        Facade::clearResolvedInstances();

        $responses = Mockery::mock(
            \Illuminate\Contracts\Routing\ResponseFactory::class
        );

        $responses->shouldReceive('json')
            ->zeroOrMoreTimes()
            ->andReturnUsing(
                fn ($data, $status = 200) =>
                    new \Illuminate\Http\JsonResponse($data, $status)
            );

        $container->instance(
            \Illuminate\Contracts\Routing\ResponseFactory::class,
            $responses
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();

        Facade::clearResolvedInstances();
        Facade::setFacadeApplication($this->previousContainer);
        Container::setInstance($this->previousContainer);

        parent::tearDown();
    }

    public function test_outdated_access_version_revokes_session(): void
    {
        [$request, $session] = $this->request(
            'HABILITADO',
            'ACTIVO',
            '8',
            '9'
        );

        $this->expectLogout();

        $response = (new EnsureAccessEligibility())->handle(
            $request,
            fn () => new Response('OK', 200)
        );

        self::assertSame(401, $response->getStatusCode());
        self::assertSame(
            'ACCESS_REVOKED',
            $response->getData(true)['code']
        );
        self::assertNull($session->get('siga_access_version'));
    }

    public function test_disabled_user_revokes_session(): void
    {
        [$request, $session] = $this->request(
            'DESHABILITADO',
            null,
            '9',
            '9'
        );

        $this->expectLogout();

        $response = (new EnsureAccessEligibility())->handle(
            $request,
            fn () => new Response('OK', 200)
        );

        self::assertSame(401, $response->getStatusCode());
        self::assertSame(
            'ACCESS_REVOKED',
            $response->getData(true)['code']
        );
        self::assertNull($session->get('siga_access_version'));
    }

    public function test_missing_session_version_is_rejected(): void
    {
        [$request] = $this->request(
            'HABILITADO',
            'ACTIVO',
            null,
            '9'
        );

        $this->expectLogout();

        $response = (new EnsureAccessEligibility())->handle(
            $request,
            fn () => new Response('OK', 200)
        );

        self::assertSame(401, $response->getStatusCode());
        self::assertSame(
            'ACCESS_REVOKED',
            $response->getData(true)['code']
        );
    }

    public function test_valid_access_version_continues_request(): void
    {
        [$request] = $this->request(
            'HABILITADO',
            'ACTIVO',
            '9',
            '9'
        );

        $response = (new EnsureAccessEligibility())->handle(
            $request,
            fn () => new Response('OK', 200)
        );

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('OK', $response->getContent());
    }

    private function request(
        string $status,
        ?string $personStatus,
        ?string $sessionVersion,
        string $currentVersion
    ): array {
        $user = Mockery::mock(User::class)->makePartial();

        $user->setRawAttributes([
            'access_status' => $status,
            'access_version' => $currentVersion,
            'id_persona' => $personStatus === null
                ? null
                : '00000000-0000-0000-0000-000000000001',
        ], true);

        $user->shouldReceive('fresh')
            ->once()
            ->andReturnSelf();

        if ($status === 'HABILITADO' && $personStatus !== null) {
            $relation = Mockery::mock(
                \Illuminate\Database\Eloquent\Relations\BelongsTo::class
            );

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

        $session = new Store(
            'siga-unit-test',
            new ArraySessionHandler(120)
        );

        $session->start();

        if ($sessionVersion !== null) {
            $session->put(
                'siga_access_version',
                $sessionVersion
            );
        }

        $request = Request::create('/api/user', 'GET');
        $request->setLaravelSession($session);
        $request->setUserResolver(fn () => $user);

        return [$request, $session];
    }

    private function expectLogout(): void
    {
        $guard = Mockery::mock();
        $guard->shouldReceive('logout')->once();

        $auth = Mockery::mock();
        $auth->shouldReceive('guard')
            ->once()
            ->with('web')
            ->andReturn($guard);

        Container::getInstance()->instance('auth', $auth);
    }
}