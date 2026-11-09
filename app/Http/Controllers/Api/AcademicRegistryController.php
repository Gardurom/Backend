<?php

namespace App\Http\Controllers\Api;

use App\Actions\Academic\RegisterAcademicIdentity;
use App\Http\Controllers\Controller;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AcademicRegistryController extends Controller
{
    public function participants(Request $request): JsonResponse
    {
        return $this->listing($request, false);
    }

    public function trainers(Request $request): JsonResponse
    {
        return $this->listing($request, true);
    }

    public function participant(string $id): JsonResponse
    {
        return $this->detail($id, false);
    }

    public function trainer(string $id): JsonResponse
    {
        return $this->detail($id, true);
    }

    public function storeParticipant(
        Request $request,
        RegisterAcademicIdentity $register
    ): JsonResponse {
        return $this->register($request, $register, 'PARTICIPANTE');
    }

    public function storeTrainer(
        Request $request,
        RegisterAcademicIdentity $register
    ): JsonResponse {
        return $this->register($request, $register, 'FORMADOR');
    }

    private function register(
        Request $request,
        RegisterAcademicIdentity $register,
        string $type
    ): JsonResponse {
        $data = $request->validate([
            'id_persona' => ['required', 'uuid'],
        ]);

        $record = $register->execute(
            $request->user(),
            $type,
            $data['id_persona']
        );

        return response()->json(['data' => $record], 201);
    }

    private function query(bool $trainer): Builder
    {
        $table = $trainer
            ? 'institutional.academic_trainers'
            : 'institutional.academic_participants';

        $key = $trainer ? 'id_formador' : 'id_participante';

        return \Illuminate\Support\Facades\DB::table($table.' as a')
            ->join('institutional.persons as p', 'p.id_persona', '=', 'a.id_persona')
            ->select([
                'a.'.$key.' as id_registro',
                'a.id_persona',
                'a.created_at',
                'p.num_expediente',
                'p.nombres',
                'p.apellido_paterno',
                'p.apellido_materno',
                'p.estatus as estatus_persona',
            ]);
    }

    private function listing(Request $request, bool $trainer): JsonResponse
    {
        $data = $request->validate([
            'q' => ['nullable', 'string', 'max:150'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:50'],
        ]);

        $query = $this->query($trainer);
        $search = trim($data['q'] ?? '');

        if ($search !== '') {
            // Búsqueda literal; no interpreta comodines proporcionados por el usuario.
            $pattern = '%'.str_replace(
                ['\\', '%', '_'],
                ['\\\\', '\\%', '\\_'],
                $search
            ).'%';

            $query->where(function (Builder $filter) use ($pattern) {
                $filter->where('p.num_expediente', 'ilike', $pattern)
                    ->orWhereRaw(
                        "concat_ws(' ', p.nombres, p.apellido_paterno, p.apellido_materno) ILIKE ?",
                        [$pattern]
                    );
            });
        }

        $rows = $query->orderBy('p.num_expediente')
            ->orderBy('a.id_persona')
            ->paginate((int) ($data['per_page'] ?? 20));

        return response()->json([
            'data' => $rows->items(),
            'meta' => [
                'current_page' => $rows->currentPage(),
                'per_page' => $rows->perPage(),
                'last_page' => $rows->lastPage(),
                'total' => $rows->total(),
            ],
        ]);
    }

    private function detail(string $id, bool $trainer): JsonResponse
    {
        $key = $trainer ? 'id_formador' : 'id_participante';

        $record = $this->query($trainer)
            ->where('a.'.$key, $id)
            ->first();

        abort_if($record === null, 404);

        return response()->json(['data' => $record]);
    }
}
