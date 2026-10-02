<?php

namespace Tests\Functional\Auth;

use Tests\Functional\HttpFunctionalTestCase;

class CsrfCookieTest extends HttpFunctionalTestCase
{
    public function test_sanctum_provides_csrf_cookie(): void
    {
        $response = $this->get('/sanctum/csrf-cookie');

        $response
            ->assertNoContent()
            ->assertCookie('XSRF-TOKEN');
    }
}
