<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Siakad\MataKuliah;
use App\Models\Siakad\Prodi;
use App\Support\ProgramScope;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ResponseDetailResultPrecentageController extends Controller
{
    use ProgramScope;

    public function index(Request $request)
    {
        $scope = $this->resolveProgramScope();
        if (!$scope['is_administrator'] && !$scope['is_legacy_token'] && empty($scope['program_code'])) {
            return $this->unauthorizedProgramScopeResponse();
        }

        $query = DB::connection('quisioner')
            ->table('dk_tbl_response_detail as rd')
            ->join('dk_tbl_response as r', 'r.ResponID', '=', 'rd.ResponID')
            ->leftJoin('dk_tbl_choice as c', 'c.ChoiceID', '=', 'rd.ChoiceID')
            ->select([
                'r.TahunAkademik',
                'r.DosenID',
                'r.MatakuliahID',
                DB::raw('COALESCE(c.ChoiceValue, rd.AnswerNumber) as choice_value'),
                DB::raw('COUNT(*) as total'),
            ])
            ->whereIn(DB::raw('COALESCE(c.ChoiceValue, rd.AnswerNumber)'), [1, 2, 3, 4]);

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
            ->groupBy('r.TahunAkademik', 'r.DosenID', 'r.MatakuliahID', DB::raw('COALESCE(c.ChoiceValue, rd.AnswerNumber)'))
            ->orderBy('r.TahunAkademik', 'desc')
            ->orderBy('r.DosenID')
            ->orderBy('r.MatakuliahID')
            ->get();

        $mkIds = $rows->pluck('MatakuliahID')->filter()->unique()->values()->all();
        $dosenIds = $rows->pluck('DosenID')->filter()->unique()->values()->all();

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
            ->keyBy('ProdiID');

        $dosenMap = DB::connection('siakad')
            ->table('dosen')
            ->whereIn('Login', $dosenIds)
            ->pluck('Nama', 'Login');

        $grouped = [];
        foreach ($rows as $row) {
            $groupKey = $row->TahunAkademik . '|' . $row->DosenID . '|' . $row->MatakuliahID;
            $mk = $matakuliahMap->get($row->MatakuliahID);
            $prodi = $mk ? $prodiMap->get($mk->ProdiID) : null;

            if (!isset($grouped[$groupKey])) {
                $grouped[$groupKey] = [
                    'TahunAkademik' => $row->TahunAkademik,
                    'prodi' => [
                        'ProdiID' => $prodi?->ProdiID,
                        'Nama' => $prodi?->Nama,
                    ],
                    'dosen' => [
                        'Login' => $row->DosenID,
                        'Nama' => $dosenMap[$row->DosenID] ?? null,
                    ],
                    'matakuliah' => [
                        'MKID' => $row->MatakuliahID,
                        'Nama' => $mk?->Nama,
                    ],
                    'precentageofchoicevalue' => [
                        '1' => 0,
                        '2' => 0,
                        '3' => 0,
                        '4' => 0,
                    ],
                    '_total_answers' => 0,
                ];
            }

            $choiceKey = (string) ((int) $row->choice_value);
            if (array_key_exists($choiceKey, $grouped[$groupKey]['precentageofchoicevalue'])) {
                $grouped[$groupKey]['precentageofchoicevalue'][$choiceKey] = (int) $row->total;
                $grouped[$groupKey]['_total_answers'] += (int) $row->total;
            }
        }

        foreach ($grouped as &$item) {
            $total = $item['_total_answers'];
            foreach (['1', '2', '3', '4'] as $choiceKey) {
                $count = $item['precentageofchoicevalue'][$choiceKey];
                $item['precentageofchoicevalue'][$choiceKey] = $total > 0
                    ? round(($count / $total) * 100, 2)
                    : 0;
            }
            unset($item['_total_answers']);
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
}
