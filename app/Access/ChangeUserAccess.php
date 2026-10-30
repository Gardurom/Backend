<?php

namespace App\Access;

use App\Authorization\PermissionResolver;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ChangeUserAccess
{
    public function execute(User $actor, int $userId, bool $enable, bool $reviewed): User
    {
        return DB::transaction(function () use ($actor, $userId, $enable, $reviewed) {
            // Serializa las transiciones que pueden afectar al último administrador.
            DB::select('SELECT pg_advisory_xact_lock(73421, 101)');
            $actor = User::query()->lockForUpdate()->findOrFail($actor->getKey());
            $permission = $enable ? 'usuarios.habilitar' : 'usuarios.deshabilitar';
            abort_unless(app(AccessEligibility::class)->allows($actor), 403);
            abort_unless(app(PermissionResolver::class)->allows($actor, $permission), 403);
            $target = User::query()->lockForUpdate()->findOrFail($userId);
            if ($enable && (!$reviewed || !$target->person()->where('estatus', 'ACTIVO')->exists())) {
                throw ValidationException::withMessages([
                    'usuario' => 'Se requiere Persona ACTIVA y revisión expresa de roles.',
                ]);
            }
            $next = $enable ? 'HABILITADO' : 'DESHABILITADO';
            if ($target->access_status === $next) {
                return $target;
            }
            DB::select("SELECT set_config('siga.actor_id', ?, true)", [(string) $actor->getKey()]);
            // El trigger incrementa la versión, revoca sesiones y registra auditoría.
            $target->access_status = $next;
            $target->save();
            return $target->refresh();
        });
    }
}
