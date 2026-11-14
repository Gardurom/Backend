<?php

declare(strict_types=1);

namespace Tests\Unit\Academic;

use App\Domain\Academic\AcademicEnrollmentConfirmationPolicy;
use DateTimeImmutable;
use InvalidArgumentException;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

require_once __DIR__
    . '/../../../app/Domain/Academic/'
    . 'AcademicEnrollmentConfirmationPolicy.php';

final class AcademicEnrollmentConfirmationPolicyTest
    extends TestCase
{
    private static function date(): DateTimeImmutable
    {
        return new DateTimeImmutable('2026-11-10T10:00:00+00:00');
    }

    public function test_new_confirmation_returns_emit(): void
    {
        self::assertSame(
            AcademicEnrollmentConfirmationPolicy::EMIT,
            AcademicEnrollmentConfirmationPolicy::decide(
                null,
                null,
                'MATRICULA-DE-PRUEBA-A'
            )
        );
    }

    public function test_identical_confirmation_returns_replay(): void
    {
        self::assertSame(
            AcademicEnrollmentConfirmationPolicy::REPLAY,
            AcademicEnrollmentConfirmationPolicy::decide(
                'MATRICULA-DE-PRUEBA-A',
                self::date(),
                'MATRICULA-DE-PRUEBA-A'
            )
        );
    }

    public function test_rejects_replacement(): void
    {
        $this->expectException(LogicException::class);

        AcademicEnrollmentConfirmationPolicy::decide(
            'MATRICULA-DE-PRUEBA-A',
            self::date(),
            'MATRICULA-DE-PRUEBA-B'
        );
    }

    public function test_rejects_case_change(): void
    {
        $this->expectException(LogicException::class);

        AcademicEnrollmentConfirmationPolicy::decide(
            'MATRICULA-DE-PRUEBA-A',
            self::date(),
            'matricula-de-prueba-a'
        );
    }

    public static function inconsistentStates(): array
    {
        return [
            'matricula sin fecha' => [
                'MATRICULA-DE-PRUEBA-A',
                null,
            ],
            'fecha sin matricula' => [
                null,
                new DateTimeImmutable('2026-11-10T10:00:00+00:00'),
            ],
        ];
    }

    #[DataProvider('inconsistentStates')]
    public function test_rejects_inconsistent_state(
        ?string $matricula,
        ?DateTimeImmutable $date
    ): void {
        $this->expectException(LogicException::class);

        AcademicEnrollmentConfirmationPolicy::decide(
            $matricula,
            $date,
            'MATRICULA-DE-PRUEBA-A'
        );
    }

    public static function invalidCandidates(): array
    {
        return [
            'cadena vacia' => [''],
            'espacios exteriores' => [' MATRICULA-DE-PRUEBA-A '],
            'salto de linea real' => [
                'MATRICULA-DE-PRUEBA-A' . chr(10)
            ],
            'tabulacion real' => [
                'MATRICULA-DE-PRUEBA-A' . chr(9)
            ],
            'caracter nulo real' => [
                'MATRICULA-DE-PRUEBA-A' . chr(0)
            ],
            'caracter DEL' => [
                'MATRICULA-DE-PRUEBA-A' . chr(127)
            ],
        ];
    }

    #[DataProvider('invalidCandidates')]
    public function test_rejects_invalid_candidate(
        string $candidate
    ): void {
        $this->expectException(InvalidArgumentException::class);

        AcademicEnrollmentConfirmationPolicy::decide(
            null,
            null,
            $candidate
        );
    }

    public function test_preserves_historical_literal(): void
    {
        $historical = 'DMPG2026_HISTORICA_PRUEBA';

        self::assertSame(
            AcademicEnrollmentConfirmationPolicy::REPLAY,
            AcademicEnrollmentConfirmationPolicy::decide(
                $historical,
                self::date(),
                $historical
            )
        );
    }
}
