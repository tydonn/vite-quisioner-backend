<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Siakad\MataKuliah;
use App\Models\Siakad\Prodi;
use App\Support\ProgramScope;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ResponseDetailResultPercentageDetailController extends Controller
{
    use ProgramScope;

    public function index(Request $request)
    {
        $scope = $this->resolveProgramScope();
        if (!$scope['is_administrator'] && !$scope['is_legacy_token'] && empty($scope['program_code'])) {
            return $this->unauthorizedProgramScopeResponse();
        }

        $perPage = min(max((int) $request->input('per_page', 25), 1), 100);
        $page = max((int) $request->input('page', 1), 1);
        $choiceValue = $request->filled('choice_value') ? (string) $request->input('choice_value') : null;

        if ($choiceValue !== null && !in_array($choiceValue, ['1', '2', '3', '4'], true)) {
            return response()->json([
                'success' => false,
                'message' => 'choice_value must be one of: 1, 2, 3, 4.',
            ], 422);
        }

        $choiceValues = $choiceValue ? [(int) $choiceValue] : [1, 2, 3, 4];

        $groupQuery = DB::connection('quisioner')
            ->table('dk_tbl_response as r')
            ->select(['r.TahunAkademik', 'r.DosenID', 'r.MatakuliahID'])
            ->distinct();

        $this->applyCommonFilters($groupQuery, $request, $scope);

        $totalGroups = DB::connection('quisioner')
            ->query()
            ->fromSub(clone $groupQuery, 'groups')
            ->count();

        $groups = $groupQuery
            ->orderBy('r.TahunAkademik', 'desc')
            ->orderBy('r.DosenID')
            ->orderBy('r.MatakuliahID')
            ->offset(($page - 1) * $perPage)
            ->limit($perPage)
            ->get();

        if ($groups->isEmpty()) {
            return response()->json([
                'success' => true,
                'data' => [],
                'pagination' => [
                    'current_page' => $page,
                    'per_page' => $perPage,
                    'total' => $totalGroups,
                    'last_page' => (int) ceil($totalGroups / $perPage),
                ],
            ]);
        }

        $query = DB::connection('quisioner')
            ->table('dk_tbl_response_detail as rd')
            ->join('dk_tbl_response as r', 'r.ResponID', '=', 'rd.ResponID')
            ->leftJoin('dk_tbl_choice as c', 'c.ChoiceID', '=', 'rd.ChoiceID')
            ->leftJoin('dk_tbl_question as q', 'q.AspectID', '=', 'rd.AspectID')
            ->select([
                'r.TahunAkademik',
                'r.DosenID',
                'r.MatakuliahID',
                'rd.AspectID',
                'q.AspectText',
                DB::raw('COALESCE(c.ChoiceValue, rd.AnswerNumber) as choice_value'),
                DB::raw('COUNT(*) as total'),
            ])
            ->whereIn(DB::raw('COALESCE(c.ChoiceValue, rd.AnswerNumber)'), $choiceValues)
            ->where(function ($query) {
                $query->whereNull('c.ChoiceID')
                    ->orWhere('c.IsActive', 1);
            })
            ->where(function ($query) use ($groups) {
                foreach ($groups as $group) {
                    $query->orWhere(function ($query) use ($group) {
                        $query->where('r.TahunAkademik', $group->TahunAkademik)
                            ->where('r.DosenID', $group->DosenID)
                            ->where('r.MatakuliahID', $group->MatakuliahID);
                    });
                }
            });

        $rows = $query
            ->groupBy(
                'r.TahunAkademik',
                'r.DosenID',
                'r.MatakuliahID',
                'rd.AspectID',
                'q.AspectText',
                DB::raw('COALESCE(c.ChoiceValue, rd.AnswerNumber)')
            )
            ->orderBy('r.TahunAkademik', 'desc')
            ->orderBy('r.DosenID')
            ->orderBy('r.MatakuliahID')
            ->orderBy('rd.AspectID')
            ->get();

        $mkIds = $groups->pluck('MatakuliahID')->filter()->unique()->values()->all();
        $dosenIds = $groups->pluck('DosenID')->filter()->unique()->values()->all();

        $matakuliahMap = MataKuliah::query()
            ->select(['MKID', 'Nama', 'ProdiID'])
            ->whereIn('MKID', $mkIds)
            ->get()
            ->keyBy('MKID');

        $prodiIds = $matakuliahMap->pluck('ProdiID')->filter()->unique()->values()->all();
        $prodiMap = Prodi::query()
            ->select(['ProdiID', 'Nama'])
            ->whereIn('ProdiID', $prodiIds)
            ->get()
            ->mapWithKeys(function ($item) {
                $normalized = $this->normalizeProdiIdForOutput((string) $item->ProdiID);
                return [$normalized => $item];
            });

        $dosenMap = DB::connection('siakad')
            ->table('dosen')
            ->whereIn('Login', $dosenIds)
            ->pluck('Nama', 'Login');

        $grouped = [];
        foreach ($groups as $group) {
            $groupKey = $group->TahunAkademik . '|' . $group->DosenID . '|' . $group->MatakuliahID;
            $mk = $matakuliahMap->get($group->MatakuliahID);
            $prodiKey = $mk ? $this->normalizeProdiIdForOutput((string) $mk->ProdiID) : null;
            $prodi = $prodiKey ? $prodiMap->get($prodiKey) : null;

            $grouped[$groupKey] = [
                'TahunAkademik' => $group->TahunAkademik,
                'prodi' => [
                    'ProdiID' => $prodiKey,
                    'Nama' => $prodi?->Nama,
                ],
                'dosen' => [
                    'Login' => $group->DosenID,
                    'Nama' => $dosenMap[$group->DosenID] ?? null,
                ],
                'matakuliah' => [
                    'MKID' => $group->MatakuliahID,
                    'Nama' => $mk?->Nama,
                ],
                'precentageofchoicevalue' => $this->makeChoiceBuckets($choiceValues),
                '_total_answers' => 0,
            ];
        }

        foreach ($rows as $row) {
            $groupKey = $row->TahunAkademik . '|' . $row->DosenID . '|' . $row->MatakuliahID;
            $choiceKey = (string) ((int) $row->choice_value);

            if (!isset($grouped[$groupKey]['precentageofchoicevalue'][$choiceKey])) {
                continue;
            }

            $grouped[$groupKey]['precentageofchoicevalue'][$choiceKey]['count'] += (int) $row->total;
            $grouped[$groupKey]['precentageofchoicevalue'][$choiceKey]['questions'][] = [
                'AspectID' => $row->AspectID,
                'AspectText' => $row->AspectText,
                'count' => (int) $row->total,
            ];
            $grouped[$groupKey]['_total_answers'] += (int) $row->total;
        }

        foreach ($grouped as &$item) {
            $total = $item['_total_answers'];
            foreach (array_map('strval', $choiceValues) as $choiceKey) {
                $count = $item['precentageofchoicevalue'][$choiceKey]['count'];
                $item['precentageofchoicevalue'][$choiceKey]['percentage'] = $total > 0
                    ? round(($count / $total) * 100, 2)
                    : 0;
            }
            unset($item['_total_answers']);
        }
        unset($item);

        return response()->json([
            'success' => true,
            'data' => array_values($grouped),
            'pagination' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $totalGroups,
                'last_page' => (int) ceil($totalGroups / $perPage),
            ],
            'filters' => [
                'choice_value' => $choiceValue,
            ],
        ]);

        // ── Q1: Main query ────────────────────────────────────────────
        // Tambah rd.AspectID + q.AspectText untuk detail per pertanyaan
        $query = DB::connection('quisioner')
            ->table('dk_tbl_response_detail as rd')
            ->join('dk_tbl_response as r', 'r.ResponID', '=', 'rd.ResponID')
            ->leftJoin('dk_tbl_choice as c', 'c.ChoiceID', '=', 'rd.ChoiceID')
            ->leftJoin('dk_tbl_question as q', 'q.AspectID', '=', 'rd.AspectID')
            ->select([
                'r.TahunAkademik',
                'r.DosenID',
                'r.MatakuliahID',
                'rd.AspectID',
                'q.AspectText',
                DB::raw('COALESCE(c.ChoiceValue, rd.AnswerNumber) as choice_value'),
                DB::raw('COUNT(*) as total'),
            ])
            ->whereIn(DB::raw('COALESCE(c.ChoiceValue, rd.AnswerNumber)'), [1, 2, 3, 4])
            ->where(function ($query) {
                $query->whereNull('c.ChoiceID')
                    ->orWhere('c.IsActive', 1);
            });

        if ($request->filled('tahun_akademik')) {
            $query->where('r.TahunAkademik', $request->tahun_akademik);
        }
        if ($request->filled('dosen_id')) {
            $query->where('r.DosenID', $request->dosen_id);
        }
        if ($request->filled('matakuliah_id')) {
            $query->where('r.MatakuliahID', $request->matakuliah_id);
        }

        if (!$scope['is_administrator'] && !$scope['is_legacy_token']) {
            $query->whereIn('r.MatakuliahID', $this->resolveMatakuliahIdsByProgramCode($scope['program_code']));
        } elseif ($request->filled('prodi_id')) {
            $query->whereIn('r.MatakuliahID', $this->resolveMatakuliahIdsByProgramCode((string) $request->prodi_id));
        }

        $rows = $query
            ->groupBy(
                'r.TahunAkademik',
                'r.DosenID',
                'r.MatakuliahID',
                'rd.AspectID',
                'q.AspectText',
                DB::raw('COALESCE(c.ChoiceValue, rd.AnswerNumber)')
            )
            ->orderBy('r.TahunAkademik', 'desc')
            ->orderBy('r.DosenID')
            ->orderBy('r.MatakuliahID')
            ->orderBy('rd.AspectID')
            ->get();

        if ($rows->isEmpty()) {
            return response()->json(['success' => true, 'data' => []]);
        }

        // ── Q2 & Q3: Data pendukung ───────────────────────────────────
        $mkIds    = $rows->pluck('MatakuliahID')->filter()->unique()->values()->all();
        $dosenIds = $rows->pluck('DosenID')->filter()->unique()->values()->all();

        $matakuliahMap = MataKuliah::query()
            ->select(['MKID', 'Nama', 'ProdiID'])
            ->whereIn('MKID', $mkIds)
            ->get()
            ->keyBy('MKID');

        $prodiIds  = $matakuliahMap->pluck('ProdiID')->filter()->unique()->values()->all();
        $prodiMap  = Prodi::query()
            ->select(['ProdiID', 'Nama'])
            ->whereIn('ProdiID', $prodiIds)
            ->get()
            ->mapWithKeys(function ($item) {
                $normalized = $this->normalizeProdiIdForOutput((string) $item->ProdiID);
                return [$normalized => $item];
            });

        $dosenMap = DB::connection('siakad')
            ->table('dosen')
            ->whereIn('Login', $dosenIds)
            ->pluck('Nama', 'Login');

        // ── Grouping di PHP ───────────────────────────────────────────
        $grouped = [];
        foreach ($rows as $row) {
            $groupKey  = $row->TahunAkademik . '|' . $row->DosenID . '|' . $row->MatakuliahID;
            $choiceKey = (string) ((int) $row->choice_value);
            $mk        = $matakuliahMap->get($row->MatakuliahID);
            $prodiKey  = $mk ? $this->normalizeProdiIdForOutput((string) $mk->ProdiID) : null;
            $prodi     = $prodiKey ? $prodiMap->get($prodiKey) : null;

            if (!isset($grouped[$groupKey])) {
                $grouped[$groupKey] = [
                    'TahunAkademik' => $row->TahunAkademik,
                    'prodi'         => [
                        'ProdiID' => $prodiKey,
                        'Nama'    => $prodi?->Nama,
                    ],
                    'dosen'         => [
                        'Login' => $row->DosenID,
                        'Nama'  => $dosenMap[$row->DosenID] ?? null,
                    ],
                    'matakuliah'    => [
                        'MKID' => $row->MatakuliahID,
                        'Nama' => $mk?->Nama,
                    ],
                    'precentageofchoicevalue' => [
                        '1' => ['percentage' => 0, 'count' => 0, 'questions' => []],
                        '2' => ['percentage' => 0, 'count' => 0, 'questions' => []],
                        '3' => ['percentage' => 0, 'count' => 0, 'questions' => []],
                        '4' => ['percentage' => 0, 'count' => 0, 'questions' => []],
                    ],
                    '_total_answers' => 0,
                ];
            }

            if (array_key_exists($choiceKey, $grouped[$groupKey]['precentageofchoicevalue'])) {
                $grouped[$groupKey]['precentageofchoicevalue'][$choiceKey]['count'] += (int) $row->total;
                $grouped[$groupKey]['precentageofchoicevalue'][$choiceKey]['questions'][] = [
                    'AspectID'   => $row->AspectID,
                    'AspectText' => $row->AspectText,
                    'count'      => (int) $row->total,
                ];
                $grouped[$groupKey]['_total_answers'] += (int) $row->total;
            }
        }

        // ── Hitung persentase ─────────────────────────────────────────
        foreach ($grouped as &$item) {
            $total = $item['_total_answers'];
            foreach (['1', '2', '3', '4'] as $choiceKey) {
                $count = $item['precentageofchoicevalue'][$choiceKey]['count'];
                $item['precentageofchoicevalue'][$choiceKey]['percentage'] = $total > 0
                    ? round(($count / $total) * 100, 2)
                    : 0;
            }
            unset($item['_total_answers']);
        }
        unset($item);

        return response()->json([
            'success' => true,
            'data'    => array_values($grouped),
        ]);
    }

    private function makeChoiceBuckets(array $choiceValues): array
    {
        $buckets = [];

        foreach ($choiceValues as $choiceValue) {
            $buckets[(string) $choiceValue] = [
                'percentage' => 0,
                'count' => 0,
                'questions' => [],
            ];
        }

        return $buckets;
    }

    private function applyCommonFilters($query, Request $request, array $scope): void
    {
        if ($request->filled('tahun_akademik')) {
            $query->where('r.TahunAkademik', $request->tahun_akademik);
        }

        if ($request->filled('dosen_id')) {
            $query->where('r.DosenID', $request->dosen_id);
        }

        if ($request->filled('matakuliah_id')) {
            $query->where('r.MatakuliahID', $request->matakuliah_id);
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

    private function normalizeProdiIdForOutput(string $prodiId): string
    {
        $trimmed = trim($prodiId);
        if ($trimmed === '' || !ctype_digit($trimmed)) return $trimmed;

        $normalized = ltrim($trimmed, '0') ?: '0';

        return str_pad($normalized, 4, '0', STR_PAD_LEFT);
    }
}
