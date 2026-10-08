<?php

namespace Tests\Functional\Auth;

use App\Providers\AppServiceProvider;
use RuntimeException;
use Tests\Functional\HttpFunctionalTestCase;

class SessionSecurityConfigurationTest extends HttpFunctionalTestCase
{
    public function test_idle_session_timeout_is_thirty_minutes(): void
    {
        $this->assertSame(
            30,
            config('session.lifetime'),
            'La sesión de SIGA debe expirar después de 30 minutos de inactividad.'
        );
    }

    public function test_session_cookie_is_http_only(): void
    {
        $this->assertTrue(
            config('session.http_only'),
            'La cookie de sesión de SIGA debe ser HttpOnly.'
        );
    }

    public function test_session_cookie_uses_lax_same_site_policy(): void
    {
        $this->assertSame(
            'lax',
            config('session.same_site'),
            'La cookie de sesión de SIGA debe utilizar SameSite=Lax.'
        );
    }

    public function test_session_data_is_encrypted(): void
    {
        $this->assertTrue(
            config('session.encrypt'),
            'Los datos de sesión de SIGA deben almacenarse cifrados.'
        );
    }

    public function test_production_rejects_session_cookie_without_secure_flag(): void
    {
        $this->app->detectEnvironment(
            fn (): string => 'production'
        );

        config()->set('session.secure', false);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('SESSION_SECURE_COOKIE=true');

        (new AppServiceProvider($this->app))->boot();
    }

    public function test_production_accepts_secure_session_cookie(): void
    {
        $this->app->detectEnvironment(
            fn (): string => 'production'
        );

        config()->set('session.secure', true);

        (new AppServiceProvider($this->app))->boot();

        $this->assertTrue(
            config('session.secure')
        );
    }
}
