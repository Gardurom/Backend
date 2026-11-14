<?php

declare(strict_types=1);

namespace Tests\Unit\Academic;

use App\Domain\Academic\AcademicEnrollmentDraftInput;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

require_once __DIR__
    . '/../../../app/Domain/Academic/AcademicEnrollmentDraftInput.php';

final class AcademicEnrollmentDraftInputTest extends TestCase
{
    private const PERSON =
        '0193a457-61b0-78a1-8f12-123456789abc';

    private const COHORT =
        '0193a457-61b0-78a1-8f12-abcdef123456';

    private static function valid(): array
    {
        return [
            'id_participante' => self::PERSON,
            'id_cohorte' => self::COHORT,
        ];
    }

    public function test_accepts_valid_input(): void
    {
        $input = AcademicEnrollmentDraftInput::fromArray(
            self::valid()
        );

        self::assertSame(self::PERSON, $input->participantId);
        self::assertSame(self::COHORT, $input->cohortId);
    }

    public function test_normalizes_uuid_case(): void
    {
        $input = AcademicEnrollmentDraftInput::fromArray([
            'id_participante' => strtoupper(self::PERSON),
            'id_cohorte' => strtoupper(self::COHORT),
        ]);

        self::assertSame(self::PERSON, $input->participantId);
        self::assertSame(self::COHORT, $input->cohortId);
    }

    public static function invalidPayloads(): array
    {
        $valid = self::valid();

        return [
            'sin participante' => [[
                'id_cohorte' => self::COHORT,
            ]],
            'sin cohorte' => [[
                'id_participante' => self::PERSON,
            ]],
            'participante invalido' => [[
                'id_participante' => 'invalido',
                'id_cohorte' => self::COHORT,
            ]],
            'cohorte invalida' => [[
                'id_participante' => self::PERSON,
                'id_cohorte' => '',
            ]],
            'persona enviada' => [
                array_merge($valid, ['id_persona' => self::PERSON])
            ],
            'matricula enviada' => [
                array_merge($valid, ['matricula' => 'NO-AUTORIZADA'])
            ],
            'estado enviado' => [
                array_merge($valid, ['estado_documental' => 'ACTIVO'])
            ],
            'actor enviado' => [
                array_merge($valid, ['user_id' => 1])
            ],
        ];
    }

    #[DataProvider('invalidPayloads')]
    public function test_rejects_invalid_input(array $data): void
    {
        $this->expectException(InvalidArgumentException::class);

        AcademicEnrollmentDraftInput::fromArray($data);
    }
}
