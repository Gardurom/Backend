<?php

namespace App\Actions\Academic;

use App\Authorization\PermissionResolver;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RegisterAcademicIdentity
{
    public function execute(User $actor, string $type, string $personId): object
    {
        [$table, $key, $permission] = match ($type) {
            'PARTICIPANTE' => [
                'institutional.academic_participants',
                'id_participante',
                'academico.participantes.registrar',
            ],
            'FORMADOR' => [
                'institutional.academic_trainers',
                'id_formador',
                'academico.formadores.registrar',
            ],
            default => throw new \InvalidArgumentException('Tipo académico inválido.'),
        };

        abort_unless(
            app(PermissionResolver::class)->allows($actor, $permission),
            403
        );

        return DB::transaction(function () use ($actor, $type, $personId, $table, $key) {
            // Serializa registros de la misma Persona y sus cambios de estado.
            $person = DB::table('institutional.persons')
                ->where('id_persona', $personId)
                ->lockForUpdate()
                ->first(['id_persona', 'estatus']);

            if ($person === null) {
                throw ValidationException::withMessages([
                    'id_persona' => 'La Persona seleccionada no existe.',
                ]);
            }

            if ($person->estatus !== 'ACTIVO') {
                throw ValidationException::withMessages([
                    'id_persona' => 'El registro académico inicial requiere una Persona ACTIVA.',
                ]);
            }

            if (DB::table($table)->where('id_persona', $personId)->exists()) {
                throw ValidationException::withMessages([
                    'id_persona' => 'La Persona ya tiene este registro académico.',
                ]);
            }

            $id = DB::table($table)->insertGetId(
                ['id_persona' => $personId],
                $key
            );

            DB::table('system.activities')->insert([
                'id_usuario' => (int) $actor->getKey(),
                'entidad' => 'ACADEMICO_'.$type,
                'id_entidad' => (string) $id,
                'accion' => 'CREACION',
                'datos_anteriores' => null,
                'datos_nuevos' => json_encode([
                    'id_persona' => $personId,
                    'tipo_registro' => $type,
                ], JSON_THROW_ON_ERROR),
                'campos_modificados' => json_encode(
                    ['id_persona'],
                    JSON_THROW_ON_ERROR
                ),
            ]);

            return DB::table($table)
                ->where($key, $id)
                ->firstOrFail();
        });
    }
}
