<?php

namespace App\Actions\Academic;

use App\Authorization\PermissionResolver;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class RegisterAcademicSitesGroups
{
    public function site(
        User $actor,
        string $code,
        string $name
    ): array {
        $this->authorize($actor);

        $result = DB::selectOne(
            'SELECT institutional.register_academic_site(
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

    public function group(
        User $actor,
        string $cohortId,
        string $code,
        ?string $specialization
    ): array {
        $this->authorize($actor);

        $result = DB::selectOne(
            'SELECT institutional.register_academic_group(
                CAST(? AS bigint),
                CAST(? AS uuid),
                CAST(? AS text),
                CAST(? AS text)
            ) AS result',
            [
                (int) $actor->getKey(),
                $cohortId,
                $code,
                $specialization,
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
                'SIGA: registro sin confirmacion institucional.'
            );
        }

        $data = is_array($result->result)
            ? $result->result
            : json_decode(
                (string) $result->result,
                true,
                512,
                JSON_THROW_ON_ERROR
            );

        if (
            !is_array($data)
            || !isset($data['id'])
            || !is_string($data['id'])
        ) {
            throw new RuntimeException(
                'SIGA: respuesta institucional invalida.'
            );
        }

        return $data;
    }
}