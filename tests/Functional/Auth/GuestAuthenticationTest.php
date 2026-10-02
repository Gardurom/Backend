<?php

namespace Tests\Functional\Auth;

use Tests\Functional\HttpFunctionalTestCase;

class GuestAuthenticationTest extends HttpFunctionalTestCase
{
    public function test_guest_cannot_access_authenticated_user_endpoint(): void
    {
        $response = $this->getJson('/api/user');

        $response->assertUnauthorized();
    }
}
