<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Siakad\MataKuliah;
use App\Support\ProgramScope;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ResponseDetailResultV2Controller extends Controller
{
    use ProgramScope;

    public function index(Request $request)
    {
        $scope = $this->resolveProgramScope();
        if (!$scope['is_administrator'] && !$scope['is_legacy_token'] && empty($scope['program_code'])) {
            return $this->unauthorizedProgramScopeResponse();
        }

        // ── Q1: Main query ────────────────────────────────────────────
        // Tambah MIN(MatakuliahID) → kita dapat referensi prodi
        // tanpa perlu query quisioner lagi di bawah
        $query = DB::connection('quisioner')
            ->table('dk_tbl_response_detail as rd')
            ->join('dk_tbl_response as r', 'r.ResponID', '=', 'rd.ResponID')
            ->leftJoin('dk_tbl_question as q', 'q.AspectID', '=', 'rd.AspectID')
            ->leftJoin('dk_tbl_category as cat', 'cat.CategoryID', '=', 'q.CategoryID')
            ->leftJoin('dk_tbl_choice as c', 'c.ChoiceID', '=', 'rd.ChoiceID')
            ->select([
                'r.DosenID as dosen_login',
                'r.TahunAkademik',
                DB::raw('MIN(r.MatakuliahID) as sample_mk_id'), // ← kunci perubahan
                DB::raw("ROUND(AVG(CASE WHEN cat.CategoryName = 'Assurance' THEN COALESCE(c.ChoiceValue, rd.AnswerNumber) END), 2) as Assurance"),
                DB::raw("ROUND(AVG(CASE WHEN cat.CategoryName = 'Empathy' THEN COALESCE(c.ChoiceValue, rd.AnswerNumber) END), 2) as Empathy"),
                DB::raw("ROUND(AVG(CASE WHEN cat.CategoryName = 'Reliability' THEN COALESCE(c.ChoiceValue, rd.AnswerNumber) END), 2) as Reliability"),
                DB::raw("ROUND(AVG(CASE WHEN cat.CategoryName = 'Responsiveness' THEN COALESCE(c.ChoiceValue, rd.AnswerNumber) END), 2) as Responsiveness"),
                DB::raw("ROUND(AVG(CASE WHEN cat.CategoryName = 'Tangibles' THEN COALESCE(c.ChoiceValue, rd.AnswerNumber) END), 2) as Tangibles"),
                DB::raw("ROUND(AVG(CASE WHEN cat.CategoryName IN ('Assurance', 'Empathy', 'Reliability', 'Responsiveness', 'Tangibles') THEN COALESCE(c.ChoiceValue, rd.AnswerNumber) END), 2) as AverageTotal"),
            ])
            ->where('q.AnswerType', 'CHOICE');

        $this->applyCommonFilters($query, $request, $scope);

        $rows = $query
            ->groupBy('r.DosenID', 'r.TahunAkademik')
            ->orderBy('r.DosenID')
            ->orderBy('r.TahunAkademik')
            ->get();

        // Early return — skip Q2 & Q3 kalau tidak ada data
        if ($rows->isEmpty()) {
            return response()->json(['success' => true, 'data' => []]);
        }

        $dosenLogins = $rows->pluck('dosen_login')->filter()->unique()->values()->all();
        $mkIds       = $rows->pluck('sample_mk_id')->filter()->unique()->values()->all();

        // ── Q2: Nama dosen (siakad) ───────────────────────────────────
        $dosenMap = DB::connection('siakad')
            ->table('dosen')
            ->whereIn('Login', $dosenLogins)
            ->pluck('Nama', 'Login');

        // ── Q3: ProdiID + nama prodi (siakad, Q4+Q5 lama digabung jadi 1) ──
        // ⚠️ Sesuaikan nama tabel 'matakuliah' jika berbeda di DB siakad Anda
        $mkProdiMap = DB::connection('siakad')
            ->table('mk as mk')
            ->join('prodi as p', 'p.ProdiID', '=', 'mk.ProdiID')
            ->whereIn('mk.MKID', $mkIds)
            ->select(['mk.MKID', 'mk.ProdiID', 'p.Nama as prodi_nama'])
            ->get()
            ->keyBy('MKID');

        // Build dosen → prodi map dari hasil Q1 (sample_mk_id) + Q3
        // Tidak perlu loop terpisah — cukup pakai mapWithKeys
        $dosenProdiMap = $rows->mapWithKeys(function ($row) use ($mkProdiMap) {
            $mk = $mkProdiMap->get($row->sample_mk_id);
            return [(string) $row->dosen_login => [
                'ProdiID' => $mk ? (string) $mk->ProdiID : null,
                'Nama'    => $mk?->prodi_nama ?? null,
            ]];
        })->all();

        return response()->json([
            'success' => true,
            'data' => $rows->map(function ($row) use ($dosenMap, $dosenProdiMap) {
                $login = (string) $row->dosen_login;
                return [
                    'TahunAkademik' => $row->TahunAkademik,
                    'prodi'         => $dosenProdiMap[$login] ?? ['ProdiID' => null, 'Nama' => null],
                    'dosen'         => [
                        'Login' => $login,
                        'Nama'  => $dosenMap[$login] ?? null,
                    ],
                    'averagetypequestion' => [
                        'Assurance'      => $row->Assurance      !== null ? (float) $row->Assurance      : null,
                        'Empathy'        => $row->Empathy        !== null ? (float) $row->Empathy        : null,
                        'Reliability'    => $row->Reliability    !== null ? (float) $row->Reliability    : null,
                        'Responsiveness' => $row->Responsiveness !== null ? (float) $row->Responsiveness : null,
                        'Tangibles'      => $row->Tangibles      !== null ? (float) $row->Tangibles      : null,
                        'AverageTotal'   => $row->AverageTotal   !== null ? (float) $row->AverageTotal   : null,
                    ],
                ];
            })->values(),
        ]);
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

    private function resolveMatakuliahIdsByProgramCode(?string $programCode): array
    {
        if (empty($programCode)) return [];

        return MataKuliah::query()
            ->whereIn('ProdiID', $this->normalizeProdiIdCandidates($programCode))
            ->pluck('MKID')
            ->values()
            ->all();
    }

    private function normalizeProdiIdCandidates(string $prodiId): array
    {
        $trimmed = trim($prodiId);
        if ($trimmed === '') return [];
        if (!ctype_digit($trimmed)) return [$trimmed];

        $normalized = ltrim($trimmed, '0') ?: '0';

        return array_values(array_unique([
            $trimmed,
            $normalized,
            str_pad($normalized, 4, '0', STR_PAD_LEFT),
        ]));
    }
}
