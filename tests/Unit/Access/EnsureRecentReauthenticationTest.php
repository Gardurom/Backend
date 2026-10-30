<?php

declare(strict_types=1);

namespace Tests\Unit\Access;

use App\Http\Middleware\EnsureRecentReauthentication;
use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use Illuminate\Contracts\Routing\ResponseFactory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Session\Store;
use Illuminate\Support\Facades\Facade;
use Mockery;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Response;

final class EnsureRecentReauthenticationTest extends TestCase
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
        $container->instance('config', new Repository([
            'auth' => ['reauthentication_timeout' => 300],
        ]));
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

    private function check(mixed $value, bool $withSession, int $expected): void
    {
        $request = Request::create('/api/usuarios/25/habilitar', 'POST');
        if ($withSession) {
            $session = new Store('siga-reautenticacion-unitaria', new ArraySessionHandler(120));
            $session->start();
            if ($value !== null) {
                $session->put('siga_reauthenticated_at', $value);
            }
            $request->setLaravelSession($session);
        }
        $continued = false;
        $response = (new EnsureRecentReauthentication())->handle(
            $request,
            function () use (&$continued): Response {
                $continued = true;
                return new Response('OK', 200);
            }
        );
        self::assertSame($expected, $response->getStatusCode());
        self::assertSame($expected === 200, $continued);
        if ($expected === 423) {
            self::assertSame('Reauthentication required.', $response->getData(true)['message']);
        }
    }

    public function test_sin_sesion_bloquea(): void { $this->check(null, false, 423); }
    public function test_sin_fecha_bloquea(): void { $this->check(null, true, 423); }
    public function test_fecha_como_texto_bloquea(): void { $this->check((string) now()->timestamp, true, 423); }
    public function test_fecha_futura_bloquea(): void { $this->check(now()->timestamp + 60, true, 423); }
    public function test_fecha_caducada_bloquea(): void { $this->check(now()->timestamp - 301, true, 423); }
    public function test_limite_exacto_bloquea(): void { $this->check(now()->timestamp - 300, true, 423); }
    public function test_reautenticacion_reciente_continua(): void { $this->check(now()->timestamp - 60, true, 200); }
    public function test_reautenticacion_actual_continua(): void { $this->check(now()->timestamp, true, 200); }
}