<?php

namespace Tests\Unit\Access;

use App\Access\AccessEligibility;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

final class AccessRestrictionsEligibilityTest extends TestCase
{
    private function eligibility(): AccessEligibility
    {
        return new AccessEligibility();
    }

    public function test_security_block_denies_access(): void
    {
        self::assertFalse($this->eligibility()->allowsRestrictions(
            true, null, null, null,
            CarbonImmutable::parse('2026-11-01T12:00:00Z')
        ));
    }

    public function test_active_suspension_denies_access(): void
    {
        self::assertFalse($this->eligibility()->allowsRestrictions(
            false, '2026-11-02T12:00:00Z', null, null,
            CarbonImmutable::parse('2026-11-01T12:00:00Z')
        ));
    }

    public function test_expired_suspension_does_not_deny_access(): void
    {
        self::assertTrue($this->eligibility()->allowsRestrictions(
            false, '2026-11-01T11:00:00Z', null, null,
            CarbonImmutable::parse('2026-11-01T12:00:00Z')
        ));
    }

    public function test_future_administrative_validity_denies_access(): void
    {
        self::assertFalse($this->eligibility()->allowsRestrictions(
            false, null, '2026-11-02T12:00:00Z', null,
            CarbonImmutable::parse('2026-11-01T12:00:00Z')
        ));
    }

    public function test_expired_administrative_validity_denies_access(): void
    {
        self::assertFalse($this->eligibility()->allowsRestrictions(
            false, null, null, '2026-11-01T12:00:00Z',
            CarbonImmutable::parse('2026-11-01T12:00:00Z')
        ));
    }

    public function test_valid_account_without_restrictions_is_allowed(): void
    {
        self::assertTrue($this->eligibility()->allowsRestrictions(
            false, null, null, null,
            CarbonImmutable::parse('2026-11-01T12:00:00Z')
        ));
    }
}
