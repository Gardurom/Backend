<?php

namespace App\Actions\User;

use App\Models\Profile;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ManageUserProfiles
{
    public function assign(int $userId, int $profileId, int $actorId): bool
    {
        return DB::transaction(function () use ($userId, $profileId, $actorId): bool {
            $user = User::query()->lockForUpdate()->findOrFail($userId);
            $profile = Profile::query()->findOrFail($profileId);

            $existing = DB::table('system.user_profiles')
                ->where('user_id', $userId)
                ->get(['profile_id', 'is_default']);

            if ($existing->contains('profile_id', $profileId)) {
                return false;
            }

            $isDefault = !$existing->contains(
                fn ($item): bool => (bool) $item->is_default
            );

            DB::table('system.user_profiles')->insert([
                'user_id' => $userId,
                'profile_id' => $profileId,
                'is_default' => $isDefault,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $this->record(
                $actorId,
                $userId,
                $profileId,
                (string) $profile->code,
                null,
                ['asignado' => true, 'predeterminado' => $isDefault]
            );

            return true;
        });
    }

    public function remove(int $userId, int $profileId, int $actorId): bool
    {
        return DB::transaction(function () use ($userId, $profileId, $actorId): bool {
            $user = User::query()->lockForUpdate()->findOrFail($userId);
            $profile = Profile::query()->findOrFail($profileId);

            $assignment = DB::table('system.user_profiles')
                ->where('user_id', $userId)
                ->where('profile_id', $profileId)
                ->first(['is_default']);

            if ($assignment === null) {
                return false;
            }

            $assignedCount = DB::table('system.user_profiles')
                ->where('user_id', $userId)
                ->count();

            if ((bool) $assignment->is_default && $assignedCount > 1) {
                throw ValidationException::withMessages([
                    'profile_id' => [
                        'Selecciona primero otro perfil predeterminado antes de retirar este.',
                    ],
                ]);
            }

            DB::table('system.user_profiles')
                ->where('user_id', $userId)
                ->where('profile_id', $profileId)
                ->delete();

            $this->record(
                $actorId,
                $userId,
                $profileId,
                (string) $profile->code,
                ['asignado' => true, 'predeterminado' => (bool) $assignment->is_default],
                null
            );

            return true;
        });
    }

    private function record(
        int $actorId,
        int $userId,
        int $profileId,
        string $profileCode,
        ?array $previous,
        ?array $current
    ): void {
        $metadata = [
            'profile_id' => $profileId,
            'profile_code' => $profileCode,
        ];

        DB::table('system.activities')->insert([
            'id_usuario' => $actorId,
            'entidad' => 'USUARIO_PERFIL',
            'id_entidad' => (string) $userId,
            'accion' => 'ACTUALIZACION',
            'datos_anteriores' => json_encode(
                $previous === null ? null : array_merge($metadata, $previous),
                JSON_THROW_ON_ERROR
            ),
            'datos_nuevos' => json_encode(
                $current === null ? null : array_merge($metadata, $current),
                JSON_THROW_ON_ERROR
            ),
            'campos_modificados' => json_encode(
                ['profile_id', 'is_default'],
                JSON_THROW_ON_ERROR
            ),
        ]);
    }
}
