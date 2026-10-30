<?php

declare(strict_types=1);

namespace Tests\Unit\Access;

use App\Http\Middleware\EnsureAccessEligibility;
use App\Models\User;
use Illuminate\Container\Container;
use Illuminate\Contracts\Routing\ResponseFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Session\Store;
use Illuminate\Support\Facades\Facade;
use Mockery;
use PHPUnit\Framework\TestCase;

final class LastEnabledAdminResponseTest extends TestCase
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
        $responses = Mockery::mock(ResponseFactory::class);
        $responses->shouldReceive('json')->zeroOrMoreTimes()
            ->andReturnUsing(fn ($data, $status = 200) => new JsonResponse($data, $status));
        $container->instance(ResponseFactory::class, $responses);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        Facade::clearResolvedInstances();
        Facade::setFacadeApplication($this->previousContainer);
        Container::setInstance($this->previousContainer);
        parent::tearDown();
    }

    private function request(): Request
    {
        $user = Mockery::mock(User::class)->makePartial();
        $user->setRawAttributes([
            'access_status' => 'HABILITADO',
            'access_version' => 9,
            'id_persona' => '00000000-0000-0000-0000-000000000001',
        ], true);
        $user->shouldReceive('fresh')->once()->andReturnSelf();
        $relation = Mockery::mock(BelongsTo::class);
        $relation->shouldReceive('where')->once()->with('estatus', 'ACTIVO')->andReturnSelf();
        $relation->shouldReceive('exists')->once()->andReturn(true);
        $user->shouldReceive('person')->once()->andReturn($relation);
        $session = new Store('siga-conflicto-unitario', new ArraySessionHandler(120));
        $session->start();
        $session->put('siga_access_version', '9');
        $request = Request::create('/api/usuarios/1/deshabilitar', 'POST');
        $request->setLaravelSession($session);
        $request->setUserResolver(fn () => $user);
        return $request;
    }

    private function error(string $message, string $sqlstate): QueryException
    {
        $cause = new \PDOException($message);
        $cause->errorInfo = [$sqlstate, 7, $message];
        return new QueryException('pgsql', 'UPDATE system.users SET access_status = ?', [], $cause);
    }

    public function test_proteccion_ultimo_administrador_devuelve_409(): void
    {
        $request = $this->request();
        $exception = $this->error('SIGA_LAST_ADMIN', '23514');
        $response = (new EnsureAccessEligibility())->handle(
            $request,
            fn () => throw $exception
        );
        self::assertSame(409, $response->getStatusCode());
        self::assertSame('LAST_ENABLED_ADMIN', $response->getData(true)['code']);
    }

    public function test_otros_errores_sql_no_se_ocultan_como_conflicto(): void
    {
        $request = $this->request();
        $exception = $this->error('OTRA_RESTRICCION', '23514');
        $this->expectException(QueryException::class);
        (new EnsureAccessEligibility())->handle($request, fn () => throw $exception);
    }
}