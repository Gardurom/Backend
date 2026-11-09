<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

final class AcademicOrganizationController extends Controller
{
    public function programs(Request $request): JsonResponse
    {
        return $this->listing(
            $request,
            'institutional.academic_programs',
            'codigo',
            ['id_programa', 'codigo', 'nombre']
        );
    }

    public function cohorts(Request $request): JsonResponse
    {
        return $this->listing(
            $request,
            'institutional.academic_cohorts',
            'anio',
            ['id_cohorte', 'id_programa', 'tipo', 'numero', 'anio']
        );
    }

    public function sites(Request $request): JsonResponse
    {
        return $this->listing(
            $request,
            'institutional.academic_sites',
            'codigo',
            ['id_sede', 'codigo', 'nombre', 'activa']
        );
    }

    public function groups(Request $request): JsonResponse
    {
        return $this->listing(
            $request,
            'institutional.academic_groups',
            'codigo',
            ['id_grupo', 'id_cohorte', 'codigo', 'especializacion']
        );
    }

    public function siteHistory(Request $request): JsonResponse
    {
        $data = $request->validate([
            'tipo' => ['required', Rule::in(['COHORTE', 'GRUPO'])],
            'id' => ['required', 'uuid'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:50'],
        ]);

        $cohort = $data['tipo'] === 'COHORTE';

        $table = $cohort
            ? 'institutional.academic_cohort_site_history'
            : 'institutional.academic_group_site_history';

        $key = $cohort ? 'id_cohorte' : 'id_grupo';

        $rows = DB::table($table)
            ->where($key, $data['id'])
            ->select([
                'id_movimiento',
                $key,
                'id_sede',
                'vigente_desde',
                'vigente_hasta',
            ])
            ->orderByDesc('vigente_desde')
            ->paginate((int) ($data['per_page'] ?? 20));

        return $this->respond($rows);
    }

    private function listing(
        Request $request,
        string $table,
        string $orderColumn,
        array $columns
    ): JsonResponse {
        $data = $request->validate([
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:50'],
        ]);

        $rows = DB::table($table)
            ->select($columns)
            ->orderBy($orderColumn)
            ->paginate((int) ($data['per_page'] ?? 20));

        return $this->respond($rows);
    }

    private function respond($rows): JsonResponse
    {
        return response()->json([
            'data' => $rows->items(),
            'meta' => [
                'total' => $rows->total(),
                'current_page' => $rows->currentPage(),
                'last_page' => $rows->lastPage(),
            ],
        ]);
    }
}