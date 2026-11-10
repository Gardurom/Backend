<?php

namespace App\Actions\Academic;

use App\Authorization\PermissionResolver;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class AssignAcademicSites
{
    public function cohort(
        User $actor,
        string $cohortId,
        string $siteId
    ): array {
        $this->authorize($actor);

        return $this->execute(
            'institutional.assign_academic_cohort_site',
            (int) $actor->getKey(),
            $cohortId,
            $siteId
        );
    }

    public function group(
        User $actor,
        string $groupId,
        string $siteId
    ): array {
        $this->authorize($actor);

        return $this->execute(
            'institutional.assign_academic_group_site',
            (int) $actor->getKey(),
            $groupId,
            $siteId
        );
    }

    private function authorize(User $actor): void
    {
        abort_unless(
            app(PermissionResolver::class)->allows(
                $actor,
                'academico.organizacion.trasladar'
            ),
            403
        );
    }

    private function execute(
        string $function,
        int $actorId,
        string $entityId,
        string $siteId
    ): array {
        // Solo se admiten los nombres fijos definidos en esta accion.
        if (!in_array($function, [
            'institutional.assign_academic_cohort_site',
            'institutional.assign_academic_group_site',
        ], true)) {
            throw new RuntimeException(
                'SIGA: funcion academica no autorizada.'
            );
        }

        $result = DB::selectOne(
            'SELECT '.$function.'(
                CAST(? AS bigint),
                CAST(? AS uuid),
                CAST(? AS uuid)
            ) AS result',
            [$actorId, $entityId, $siteId]
        );

        $data = is_array($result?->result)
            ? $result->result
            : json_decode(
                (string) ($result?->result ?? ''),
                true,
                512,
                JSON_THROW_ON_ERROR
            );

        if (
            !is_array($data)
            || !isset($data['id_movimiento'])
            || !is_string($data['id_movimiento'])
        ) {
            throw new RuntimeException(
                'SIGA: movimiento institucional sin confirmacion.'
            );
        }

        return $data;
    }
}