<?php

namespace App\Actions\Academic;

use App\Authorization\PermissionResolver;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class RegisterAcademicOrganization
{
    public function program(
        User $actor,
        string $code,
        string $name
    ): array {
        $this->authorize($actor);

        $result = DB::selectOne(
            'SELECT institutional.register_academic_program(
                CAST(? AS bigint),
                CAST(? AS text),
                CAST(? AS text)
            ) AS result',
            [
                (int) $actor->getKey(),
                $code,
                $name,
            ]
        );

        return $this->decode($result);
    }

    public function cohort(
        User $actor,
        string $programId,
        string $type,
        int $number,
        int $year
    ): array {
        $this->authorize($actor);

        $result = DB::selectOne(
            'SELECT institutional.register_academic_cohort(
                CAST(? AS bigint),
                CAST(? AS uuid),
                CAST(? AS text),
                CAST(? AS integer),
                CAST(? AS integer)
            ) AS result',
            [
                (int) $actor->getKey(),
                $programId,
                $type,
                $number,
                $year,
            ]
        );

        return $this->decode($result);
    }

    private function authorize(User $actor): void
    {
        abort_unless(
            app(PermissionResolver::class)->allows(
                $actor,
                'academico.organizacion.registrar'
            ),
            403
        );
    }

    private function decode(?object $result): array
    {
        if ($result === null || !isset($result->result)) {
            throw new RuntimeException(
                'SIGA: registro academico sin confirmacion.'
            );
        }

        $decoded = is_array($result->result)
            ? $result->result
            : json_decode(
                (string) $result->result,
                true,
                512,
                JSON_THROW_ON_ERROR
            );

        if (
            !is_array($decoded)
            || !isset($decoded['id'])
            || !is_string($decoded['id'])
        ) {
            throw new RuntimeException(
                'SIGA: respuesta institucional no valida.'
            );
        }

        return $decoded;
    }
}