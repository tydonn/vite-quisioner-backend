<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Siakad\MataKuliah;
use App\Models\Siakad\Prodi;
use App\Support\ProgramScope;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ResponseDetailResultController extends Controller
{
    use ProgramScope;

    public function index(Request $request)
    {
        $scope = $this->resolveProgramScope();
        if (!$scope['is_administrator'] && !$scope['is_legacy_token'] && empty($scope['program_code'])) {
            return $this->unauthorizedProgramScopeResponse();
        }

        $typeKeys = ['Assurance', 'Empathy', 'Reliability', 'Responsiveness', 'Tangibles'];

        $query = DB::connection('quisioner')
            ->table('dk_tbl_response_detail as rd')
            ->join('dk_tbl_response as r', 'r.ResponID', '=', 'rd.ResponID')
            ->leftJoin('dk_tbl_question as q', 'q.AspectID', '=', 'rd.AspectID')
            ->leftJoin('dk_tbl_category as cat', 'cat.CategoryID', '=', 'q.CategoryID')
            ->leftJoin('dk_tbl_choice as c', 'c.ChoiceID', '=', 'rd.ChoiceID')
            ->select([
                'r.DosenID as dosen_login',
                'r.TahunAkademik as tahun_akademik',
                'cat.CategoryName as category_name',
                DB::raw('AVG(COALESCE(c.ChoiceValue, rd.AnswerNumber, 0)) as avg_value'),
            ])
            ->where('q.AnswerType', 'CHOICE');
        $this->applyCommonFilters($query, $request, $scope);

        $rows = $query
            ->groupBy('r.DosenID', 'r.TahunAkademik', 'cat.CategoryName')
            ->orderBy('r.DosenID')
            ->orderBy('r.TahunAkademik')
            ->get();

        $resolvedProdiId = null;
        if (!$scope['is_administrator'] && !$scope['is_legacy_token']) {
            $resolvedProdiId = $scope['program_code'];
        } elseif ($request->filled('prodi_id')) {
            $resolvedProdiId = (string) $request->prodi_id;
        }

        $resolvedProdiName = null;
        if (!empty($resolvedProdiId)) {
            $resolvedProdiName = Prodi::query()
                ->where('ProdiID', $resolvedProdiId)
                ->value('Nama');
        }

        $dosenProdiMap = $this->resolveDosenProdiMap($request, $scope);

        $dosenLogins = $rows->pluck('dosen_login')->filter()->unique()->values()->all();
        $dosenMap = DB::connection('siakad')
            ->table('dosen')
            ->whereIn('Login', $dosenLogins)
            ->pluck('Nama', 'Login');

        $grouped = [];
        foreach ($rows as $row) {
            $login = (string) $row->dosen_login;
            $tahunAkademik = (string) ($row->tahun_akademik ?? '');
            $groupKey = $login . '|' . $tahunAkademik;

            if (!isset($grouped[$groupKey])) {
                $grouped[$groupKey] = [
                    'TahunAkademik' => $tahunAkademik !== '' ? $tahunAkademik : null,
                    'prodi' => [
                        'ProdiID' => $dosenProdiMap[$login]['ProdiID'] ?? $resolvedProdiId,
                        'Nama' => $dosenProdiMap[$login]['Nama'] ?? $resolvedProdiName,
                    ],
                    'dosen' => [
                        'Login' => $login,
                        'Nama' => $dosenMap[$login] ?? null,
                    ],
                    'averagetypequestion' => array_fill_keys($typeKeys, null),
                ];
            }

            $categoryName = (string) $row->category_name;
            if (array_key_exists($categoryName, $grouped[$groupKey]['averagetypequestion'])) {
                $grouped[$groupKey]['averagetypequestion'][$categoryName] = round((float) $row->avg_value, 2);
            }
        }

        foreach ($grouped as &$item) {
            $values = array_filter(
                $item['averagetypequestion'],
                static fn ($value, $key) => $key !== 'AvarageTotal' && $value !== null,
                ARRAY_FILTER_USE_BOTH
            );

            $item['averagetypequestion']['AvarageTotal'] = empty($values)
                ? null
                : round(array_sum($values) / count($values), 2);
        }
        unset($item);

        return response()->json([
            'success' => true,
            'data' => array_values($grouped),
        ]);
    }

    private function resolveMatakuliahIdsByProgramCode(?string $programCode): array
    {
        if (empty($programCode)) {
            return [];
        }

        return MataKuliah::query()
            ->where('ProdiID', $programCode)
            ->pluck('MKID')
            ->values()
            ->all();
    }

    private function applyCommonFilters($query, Request $request, array $scope): void
    {
        if ($request->filled('tahun_akademik')) {
            $query->where('r.TahunAkademik', $request->tahun_akademik);
        }
        if ($request->filled('matakuliah_id')) {
            $query->where('r.MatakuliahID', $request->matakuliah_id);
        }
        if ($request->filled('dosen_id')) {
            $query->where('r.DosenID', $request->dosen_id);
        }

        if (!$scope['is_administrator'] && !$scope['is_legacy_token']) {
            $query->whereIn('r.MatakuliahID', $this->resolveMatakuliahIdsByProgramCode($scope['program_code']));
        } elseif ($request->filled('prodi_id')) {
            $query->whereIn('r.MatakuliahID', $this->resolveMatakuliahIdsByProgramCode((string) $request->prodi_id));
        }
    }

    private function resolveDosenProdiMap(Request $request, array $scope): array
    {
        $rows = DB::connection('quisioner')
            ->table('dk_tbl_response as r')
            ->select(['r.DosenID', 'r.MatakuliahID'])
            ->distinct();

        $this->applyCommonFilters($rows, $request, $scope);

        $pairs = $rows->get();
        if ($pairs->isEmpty()) {
            return [];
        }

        $mkIds = $pairs->pluck('MatakuliahID')->filter()->unique()->values()->all();
        $mkProdiMap = MataKuliah::query()
            ->whereIn('MKID', $mkIds)
            ->pluck('ProdiID', 'MKID');

        $prodiIds = $mkProdiMap->filter()->unique()->values()->all();
        $prodiNameMap = Prodi::query()
            ->whereIn('ProdiID', $prodiIds)
            ->pluck('Nama', 'ProdiID');

        $result = [];
        foreach ($pairs as $pair) {
            $login = (string) $pair->DosenID;
            if (isset($result[$login])) {
                continue;
            }

            $prodiId = $mkProdiMap[$pair->MatakuliahID] ?? null;
            $result[$login] = [
                'ProdiID' => $prodiId !== null ? (string) $prodiId : null,
                'Nama' => $prodiId !== null ? ($prodiNameMap[$prodiId] ?? null) : null,
            ];
        }

        return $result;
    }
}
