<?php

declare(strict_types=1);

namespace Tests\Unit\Academic;

use App\Domain\Academic\AcademicConfirmationAuthorizationGate;
use App\Domain\Academic\AcademicConfirmationFingerprint;
use App\Domain\Academic\AcademicConfirmationWriteBatch;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 3)
    . '/app/Domain/Academic/AcademicConfirmationFingerprint.php';

require_once dirname(__DIR__, 3)
    . '/app/Domain/Academic/AcademicConfirmationAuthorizationGate.php';

require_once dirname(__DIR__, 3)
    . '/app/Domain/Academic/AcademicConfirmationWriteBatch.php';

final class AcademicConfirmationSafetyContractTest extends TestCase
{
    private const OP =
        '0193a457-61b0-78a1-8f12-111111111111';

    private const COHORT =
        '0193a457-61b0-78a1-8f12-333333333333';

    private const A =
        '0193a457-61b0-78a1-8f12-555555555555';

    private const B =
        '0193a457-61b0-78a1-8f12-666666666666';

    private static function request(
        string $mode = 'INICIAL',
        array $ids = [self::A, self::B]
    ): AcademicConfirmationFingerprint {
        return AcademicConfirmationFingerprint::build(
            self::OP,
            self::COHORT,
            'C',
            $mode,
            $ids
        );
    }

    private static function user(): User
    {
        $user = new User();

        $user->forceFill([
            'id' => 17,
            'id_persona' => self::COHORT,
        ]);

        $user->exists = true;

        return $user;
    }

    private static function gate(
        bool $eligible = true,
        bool $permitted = true,
        bool $scope = true,
        bool $recent = true
    ): AcademicConfirmationAuthorizationGate {
        return new AcademicConfirmationAuthorizationGate(
            static fn (User $user): bool => $eligible,
            static fn (User $user): bool => $permitted,
            static fn (
                User $user,
                AcademicConfirmationFingerprint $request
            ): bool => $scope,
            static fn (User $user): bool => $recent
        );
    }

    public function test_uses_distinct_confirmation_permission(): void
    {
        self::assertSame(
            'academico.incorporaciones.confirmar',
            AcademicConfirmationAuthorizationGate::PERMISSION
        );
    }

    public function test_verified_facts_pass_gate(): void
    {
        self::gate()->assertAuthorized(
            self::user(),
            self::request()
        );

        self::assertTrue(true);
    }

    public function test_user_without_person_is_denied(): void
    {
        $user = self::user();
        $user->id_persona = null;

        $this->expectException(
            AuthorizationException::class
        );

        self::gate()->assertAuthorized(
            $user,
            self::request()
        );
    }

    public function test_unsaved_user_is_denied(): void
    {
        $user = self::user();
        $user->exists = false;

        $this->expectException(
            AuthorizationException::class
        );

        self::gate()->assertAuthorized(
            $user,
            self::request()
        );
    }

    public function test_ineligible_user_is_denied(): void
    {
        $this->expectException(
            AuthorizationException::class
        );

        self::gate(eligible: false)->assertAuthorized(
            self::user(),
            self::request()
        );
    }

    public function test_missing_permission_is_denied(): void
    {
        $this->expectException(
            AuthorizationException::class
        );

        self::gate(permitted: false)->assertAuthorized(
            self::user(),
            self::request()
        );
    }

    public function test_unauthorized_scope_is_denied(): void
    {
        $this->expectException(
            AuthorizationException::class
        );

        self::gate(scope: false)->assertAuthorized(
            self::user(),
            self::request()
        );
    }

    public function test_missing_reauthentication_is_denied(): void
    {
        $this->expectException(
            AuthorizationException::class
        );

        self::gate(recent: false)->assertAuthorized(
            self::user(),
            self::request()
        );
    }

    public function test_gate_can_be_applied_again_for_replay(): void
    {
        $calls = 0;

        $gate = new AcademicConfirmationAuthorizationGate(
            static function (User $user) use (&$calls): bool {
                $calls++;
                return true;
            },
            static fn (User $user): bool => true,
            static fn (
                User $user,
                AcademicConfirmationFingerprint $request
            ): bool => true,
            static fn (User $user): bool => true
        );

        $gate->assertAuthorized(self::user(), self::request());
        $gate->assertAuthorized(self::user(), self::request());

        self::assertSame(2, $calls);
    }

    public function test_valid_initial_batch_preserves_order(): void
    {
        $result = AcademicConfirmationWriteBatch::validate(
            self::request(),
            [
                [
                    'id_incorporacion' => self::A,
                    'consecutivo' => 1,
                ],
                [
                    'id_incorporacion' => self::B,
                    'consecutivo' => 2,
                ],
            ]
        );

        self::assertCount(2, $result);
        self::assertSame(1, $result[0]['consecutivo']);
        self::assertSame(2, $result[1]['consecutivo']);
    }

    public function test_accepts_transition_from_999_to_1000(): void
    {
        $result = AcademicConfirmationWriteBatch::validate(
            self::request(),
            [
                [
                    'id_incorporacion' => self::A,
                    'consecutivo' => 999,
                ],
                [
                    'id_incorporacion' => self::B,
                    'consecutivo' => 1000,
                ],
            ]
        );

        self::assertSame(1000, $result[1]['consecutivo']);
    }

    public function test_reordered_incorporations_are_rejected(): void
    {
        $this->expectException(
            InvalidArgumentException::class
        );

        AcademicConfirmationWriteBatch::validate(
            self::request(),
            [
                [
                    'id_incorporacion' => self::B,
                    'consecutivo' => 1,
                ],
                [
                    'id_incorporacion' => self::A,
                    'consecutivo' => 2,
                ],
            ]
        );
    }

    public function test_sequence_gaps_are_rejected(): void
    {
        $this->expectException(
            InvalidArgumentException::class
        );

        AcademicConfirmationWriteBatch::validate(
            self::request(),
            [
                [
                    'id_incorporacion' => self::A,
                    'consecutivo' => 5,
                ],
                [
                    'id_incorporacion' => self::B,
                    'consecutivo' => 7,
                ],
            ]
        );
    }

    public function test_missing_assignment_is_rejected(): void
    {
        $this->expectException(
            InvalidArgumentException::class
        );

        AcademicConfirmationWriteBatch::validate(
            self::request(),
            [
                [
                    'id_incorporacion' => self::A,
                    'consecutivo' => 1,
                ],
            ]
        );
    }

    public function test_posterior_batch_must_be_unitary(): void
    {
        $this->expectException(
            InvalidArgumentException::class
        );

        AcademicConfirmationWriteBatch::validate(
            self::request('POSTERIOR'),
            [
                [
                    'id_incorporacion' => self::A,
                    'consecutivo' => 3,
                ],
                [
                    'id_incorporacion' => self::B,
                    'consecutivo' => 4,
                ],
            ]
        );
    }

    public function test_zero_sequence_is_rejected(): void
    {
        $this->expectException(
            InvalidArgumentException::class
        );

        AcademicConfirmationWriteBatch::validate(
            self::request('POSTERIOR', [self::A]),
            [
                [
                    'id_incorporacion' => self::A,
                    'consecutivo' => 0,
                ],
            ]
        );
    }

    public function test_string_sequence_is_rejected(): void
    {
        $this->expectException(
            InvalidArgumentException::class
        );

        AcademicConfirmationWriteBatch::validate(
            self::request('POSTERIOR', [self::A]),
            [
                [
                    'id_incorporacion' => self::A,
                    'consecutivo' => '1',
                ],
            ]
        );
    }

    public function test_empty_assignments_are_rejected(): void
    {
        $this->expectException(
            InvalidArgumentException::class
        );

        AcademicConfirmationWriteBatch::validate(
            self::request(),
            []
        );
    }
}
