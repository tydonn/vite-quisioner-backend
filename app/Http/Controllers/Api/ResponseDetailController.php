<?php

namespace App\Http\Controllers\Api;

use App\Exports\ResponseDetailExportAggregationV2;
use App\Exports\ResponseDetailsExport;
use App\Exports\ResponseDetailExportAgretion;
use App\Http\Controllers\Controller;
use App\Jobs\ExportResponseDetailsJob;
use App\Models\ExportTask;
use App\Models\Quisioner\ResponseDetail;
use App\Models\Siakad\MataKuliah;
use App\Support\ProgramScope;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\DB;

class ResponseDetailController extends Controller
{
    use ProgramScope;

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $scope = $this->resolveProgramScope();
        if (!$scope['is_administrator'] && !$scope['is_legacy_token'] && empty($scope['program_code'])) {
            return $this->unauthorizedProgramScopeResponse();
        }

        $withRelations = $request->boolean('with_relations', true);
        $query = $this->buildFilteredQuery($request, $withRelations, $scope['program_code'], $scope['is_administrator'], $scope['is_legacy_token']);

        $perPage = (int) $request->get('per_page', 100);
        if ($perPage < 1) {
            $perPage = 100;
        }
        if ($perPage > 500) {
            $perPage = 500;
        }

        $includeTotal = $request->boolean('include_total', false);
        $result = $includeTotal
            ? $query->paginate($perPage)
            : $query->simplePaginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $result->items(),
            'pagination' => [
                'current_page' => $result->currentPage(),
                'per_page' => $result->perPage(),
                'total' => $includeTotal ? $result->total() : null,
                'last_page' => $includeTotal ? $result->lastPage() : null,
                'has_more' => $result->hasMorePages(),
            ],
        ]);
    }

    public function download(Request $request)
    {
        $scope = $this->resolveProgramScope();
        if (!$scope['is_administrator'] && !$scope['is_legacy_token'] && empty($scope['program_code'])) {
            return $this->unauthorizedProgramScopeResponse();
        }

        $filters = $request->only([
            'response_id',
            'aspect_id',
            'choice_id',
            'tahun_akademik',
            'tahun_id',
            'prodi_id',
            'nama_prodi',
            'prodi',
            'matakuliah_id',
            'nama_matakuliah',
        ]);

        // Alias compatibility for existing frontend params.
        if (empty($filters['nama_prodi']) && !empty($filters['prodi'])) {
            $filters['nama_prodi'] = $filters['prodi'];
        }
        if (empty($filters['tahun_akademik']) && !empty($filters['tahun_id'])) {
            $filters['tahun_akademik'] = $filters['tahun_id'];
        }
        if (!$scope['is_administrator'] && !$scope['is_legacy_token']) {
            $filters['program_code'] = $scope['program_code'];
            $filters['prodi_id'] = $scope['program_code'];
        }

        $fileName = $this->buildExportFileName($filters, 'response-details');
        return Excel::download(
            new ResponseDetailExportAggregationV2($filters),
            $fileName
        );
    }

    /**
     * GET /api/response-details/satisfaction-labels
     */
    public function satisfactionLabels(Request $request)
    {
        $scope = $this->resolveProgramScope();
        if (!$scope['is_administrator'] && !$scope['is_legacy_token'] && empty($scope['program_code'])) {
            return $this->unauthorizedProgramScopeResponse();
        }

        $labels = ['Sangat Puas', 'Puas'];

        $query = ResponseDetail::query()
            ->from('dk_tbl_response_detail as rd')
            ->join('dk_tbl_response as r', 'rd.ResponID', '=', 'r.ResponID')
            ->join('dk_tbl_choice as c', 'rd.ChoiceID', '=', 'c.ChoiceID');
        $this->applyProgramScopeToJoinedResponseQuery($query, $scope['program_code'], $scope['is_administrator'], $scope['is_legacy_token']);

        if ($request->filled('response_id')) {
            $query->where('rd.ResponID', $request->response_id);
        }
        if ($request->filled('aspect_id')) {
            $query->where('rd.AspectID', $request->aspect_id);
        }
        if ($request->filled('choice_id')) {
            $query->where('rd.ChoiceID', $request->choice_id);
        }
        if ($request->filled('tahun_akademik')) {
            $query->where('r.TahunAkademik', $request->tahun_akademik);
        }
        if ($request->filled('matakuliah_id')) {
            $query->where('r.MatakuliahID', $request->matakuliah_id);
        }
        if ($request->filled('nama_matakuliah')) {
            $mkIds = MataKuliah::query()
                ->select(['MKID'])
                ->where('Nama', 'like', '%' . $request->nama_matakuliah . '%')
                ->pluck('MKID');

            if ($mkIds->isEmpty()) {
                return response()->json([
                    'success' => true,
                    'data' => [
                        'percent' => 0,
                        'total_answers' => 0,
                        'positive_answers' => 0,
                        'labels' => $labels,
                    ],
                ]);
            }

            $query->whereIn('r.MatakuliahID', $mkIds);
        }
        if ($request->filled('prodi_id') || $request->filled('nama_prodi')) {
            $mkIds = $this->resolveMatakuliahIdsByProdi(
                $request->input('prodi_id'),
                $request->input('nama_prodi')
            );

            if ($mkIds->isEmpty()) {
                return response()->json([
                    'success' => true,
                    'data' => [
                        'percent' => 0,
                        'total_answers' => 0,
                        'positive_answers' => 0,
                        'labels' => $labels,
                    ],
                ]);
            }

            $query->whereIn('r.MatakuliahID', $mkIds);
        }

        $total = (clone $query)->count();
        $positive = (clone $query)->whereIn('c.ChoiceLabel', $labels)->count();

        $percent = $total > 0 ? round(($positive / $total) * 100, 2) : 0;

        return response()->json([
            'success' => true,
            'data' => [
                'percent' => $percent,
                'total_answers' => $total,
                'positive_answers' => $positive,
                'labels' => $labels,
            ],
        ]);
    }

    /**
     * GET /api/response-details/label-counts
     */
    public function labelCounts(Request $request)
    {
        $scope = $this->resolveProgramScope();
        if (!$scope['is_administrator'] && !$scope['is_legacy_token'] && empty($scope['program_code'])) {
            return $this->unauthorizedProgramScopeResponse();
        }

        $labels = [
            'Sangat Puas',
            'Puas',
            'Kurang Puas',
            'Tidak Puas',
        ];

        $query = ResponseDetail::query()
            ->from('dk_tbl_response_detail as rd')
            ->join('dk_tbl_response as r', 'rd.ResponID', '=', 'r.ResponID')
            ->join('dk_tbl_choice as c', 'rd.ChoiceID', '=', 'c.ChoiceID');
        $this->applyProgramScopeToJoinedResponseQuery($query, $scope['program_code'], $scope['is_administrator'], $scope['is_legacy_token']);

        if ($request->filled('response_id')) {
            $query->where('rd.ResponID', $request->response_id);
        }
        if ($request->filled('aspect_id')) {
            $query->where('rd.AspectID', $request->aspect_id);
        }
        if ($request->filled('choice_id')) {
            $query->where('rd.ChoiceID', $request->choice_id);
        }
        if ($request->filled('tahun_akademik')) {
            $query->where('r.TahunAkademik', $request->tahun_akademik);
        }
        if ($request->filled('matakuliah_id')) {
            $query->where('r.MatakuliahID', $request->matakuliah_id);
        }
        if ($request->filled('nama_matakuliah')) {
            $mkIds = MataKuliah::query()
                ->select(['MKID'])
                ->where('Nama', 'like', '%' . $request->nama_matakuliah . '%')
                ->pluck('MKID');

            if ($mkIds->isEmpty()) {
                return response()->json([
                    'success' => true,
                    'data' => array_fill_keys($labels, 0),
                ]);
            }

            $query->whereIn('r.MatakuliahID', $mkIds);
        }
        if ($request->filled('prodi_id') || $request->filled('nama_prodi')) {
            $mkIds = $this->resolveMatakuliahIdsByProdi(
                $request->input('prodi_id'),
                $request->input('nama_prodi')
            );

            if ($mkIds->isEmpty()) {
                return response()->json([
                    'success' => true,
                    'data' => array_fill_keys($labels, 0),
                ]);
            }

            $query->whereIn('r.MatakuliahID', $mkIds);
        }

        $rows = (clone $query)
            ->whereIn('c.ChoiceLabel', $labels)
            ->select([
                'c.ChoiceLabel as label',
                DB::raw('COUNT(*) as total'),
            ])
            ->groupBy('c.ChoiceLabel')
            ->get();

        $data = array_fill_keys($labels, 0);
        foreach ($rows as $row) {
            $data[$row->label] = (int) $row->total;
        }

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        // validate request
        $data = $request->validate([
            'ResponID' => 'required|integer',
            'AspectID' => 'required|integer',
            'ChoiceID' => 'required|integer',
            'AnswerText' => 'nullable|string|max:255',
            'AnswerNumber' => 'nullable|numeric',
        ]);

        $responseDetail = ResponseDetail::create($data);

        return response()->json([
            'success' => true,
            'data' => $responseDetail
        ]);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $scope = $this->resolveProgramScope();
        if (!$scope['is_administrator'] && !$scope['is_legacy_token'] && empty($scope['program_code'])) {
            return $this->unauthorizedProgramScopeResponse();
        }

        $query = ResponseDetail::with([
            'response:ResponID,MahasiswaID,DosenID,MatakuliahID,TahunAkademik,Semester',
            'response.dosen:Login,Nama',
            'response.mahasiswa:MhswID,Nama',
            'response.matakuliah:MKID,Nama,ProdiID',
            'response.matakuliah.prodi:ProdiID,Nama',
            'question:AspectID,CategoryID,AspectText,AnswerType',
            'choice:ChoiceID,ChoiceLabel,ChoiceValue',
        ]);
        $this->applyProgramScopeToDetailQuery($query, $scope['program_code'], $scope['is_administrator'], $scope['is_legacy_token']);

        $responseDetail = $query->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $responseDetail,
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
        $responseDetail = ResponseDetail::findOrFail($id);

        $data = $request->validate([
            'ResponID' => 'sometimes|integer',
            'AspectID' => 'sometimes|integer',
            'ChoiceID' => 'sometimes|integer',
            'AnswerText' => 'nullable|string|max:255',
            'AnswerNumber' => 'nullable|numeric',
        ]);

        $responseDetail->update($data);

        return response()->json([
            'success' => true,
            'data' => $responseDetail,
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
        $responseDetail = ResponseDetail::findOrFail($id);
        $responseDetail->delete();

        return response()->json([
            'success' => true,
            'message' => 'Response detail deleted',
        ]);
    }

    private function buildFilteredQuery(
        Request $request,
        bool $withRelations = true,
        ?string $programCode = null,
        bool $isAdministrator = false,
        bool $isLegacyToken = false
    )
    {
        $query = ResponseDetail::query()
            ->select([
                'DetailID',
                'ResponID',
                'AspectID',
                'ChoiceID',
            ])
            ->orderBy('DetailID');
        $this->applyProgramScopeToDetailQuery($query, $programCode, $isAdministrator, $isLegacyToken);

        if ($withRelations) {
            $query->with([
                'response:ResponID,MahasiswaID,DosenID,MatakuliahID,TahunAkademik,Semester',
                // 'response.dosen:Login,Nama',
                // 'response.mahasiswa:MhswID,Nama',
                'response.matakuliah:MKID,Nama,ProdiID',
                'response.matakuliah.prodi:ProdiID,Nama',
                'question:AspectID,AspectText,AnswerType',
                'choice:ChoiceID,ChoiceValue',
            ]);
        }

        if ($request->filled('response_id')) {
            $query->where('ResponID', $request->response_id);
        }

        if ($request->filled('aspect_id')) {
            $query->where('AspectID', $request->aspect_id);
        }

        if ($request->filled('choice_id')) {
            $query->where('ChoiceID', $request->choice_id);
        }

        if ($request->filled('tahun_akademik')) {
            $query->whereHas('response', function ($subQuery) use ($request) {
                $subQuery->where('TahunAkademik', $request->tahun_akademik);
            });
        }

        if ($request->filled('prodi_id') || $request->filled('nama_prodi')) {
            $mkIds = $this->resolveMatakuliahIdsByProdi(
                $request->input('prodi_id'),
                $request->input('nama_prodi')
            );

            if ($mkIds->isEmpty()) {
                $query->whereRaw('1 = 0');
            } else {
                $query->whereHas('response', function ($subQuery) use ($mkIds) {
                    $subQuery->whereIn('MatakuliahID', $mkIds);
                });
            }
        }

        if ($request->filled('matakuliah_id')) {
            $query->whereHas('response', function ($subQuery) use ($request) {
                $subQuery->where('MatakuliahID', $request->matakuliah_id);
            });
        }

        if ($request->filled('nama_matakuliah')) {
            $mkIds = MataKuliah::query()
                ->select(['MKID'])
                ->where('Nama', 'like', '%' . $request->nama_matakuliah . '%')
                ->pluck('MKID');

            if ($mkIds->isEmpty()) {
                $query->whereRaw('1 = 0');
            } else {
                $query->whereHas('response', function ($subQuery) use ($mkIds) {
                    $subQuery->whereIn('MatakuliahID', $mkIds);
                });
            }
        }

        return $query;
    }

    private function applyProgramScopeToDetailQuery($query, ?string $programCode, bool $isAdministrator, bool $isLegacyToken): void
    {
        if ($isAdministrator || $isLegacyToken || empty($programCode)) {
            return;
        }

        $mkIds = MataKuliah::query()
            ->select(['MKID'])
            ->where('ProdiID', $programCode)
            ->pluck('MKID');

        if ($mkIds->isEmpty()) {
            $query->whereRaw('1 = 0');
            return;
        }

        $query->whereHas('response', function ($subQuery) use ($mkIds) {
            $subQuery->whereIn('MatakuliahID', $mkIds);
        });
    }

    private function applyProgramScopeToJoinedResponseQuery($query, ?string $programCode, bool $isAdministrator, bool $isLegacyToken): void
    {
        if ($isAdministrator || $isLegacyToken || empty($programCode)) {
            return;
        }

        $mkIds = MataKuliah::query()
            ->select(['MKID'])
            ->where('ProdiID', $programCode)
            ->pluck('MKID');

        if ($mkIds->isEmpty()) {
            $query->whereRaw('1 = 0');
            return;
        }

        $query->whereIn('r.MatakuliahID', $mkIds);
    }

    private function resolveMatakuliahIdsByProdi(mixed $prodiId, mixed $namaProdi): Collection
    {
        $query = MataKuliah::query()->select(['MKID']);

        if (!empty($prodiId)) {
            $query->where('ProdiID', $prodiId);
        } elseif (!empty($namaProdi)) {
            $query->whereHas('prodi', function ($subQuery) use ($namaProdi) {
                $subQuery->where('Nama', 'like', '%' . $namaProdi . '%');
            });
        }

        return $query->pluck('MKID');
    }

    private function buildExportFileName(array $filters, string $prefix): string
    {
        $parts = [];

        if (!empty($filters['tahun_akademik'])) {
            $parts[] = 'TA-' . $filters['tahun_akademik'];
        }
        if (!empty($filters['tahun_id'])) {
            $parts[] = 'THN-' . $filters['tahun_id'];
        }
        if (!empty($filters['semester'])) {
            $parts[] = 'SEM-' . $filters['semester'];
        }
        if (!empty($filters['prodi_id'])) {
            $parts[] = 'PRODIID-' . $filters['prodi_id'];
        }
        if (!empty($filters['nama_prodi'])) {
            $parts[] = 'PRODI-' . $filters['nama_prodi'];
        }
        if (!empty($filters['nama_matakuliah'])) {
            $parts[] = 'MK-' . $filters['nama_matakuliah'];
        }
        if (!empty($filters['matakuliah_id'])) {
            $parts[] = 'MKID-' . $filters['matakuliah_id'];
        }
        if (!empty($filters['response_id'])) {
            $parts[] = 'RESP-' . $filters['response_id'];
        }
        if (!empty($filters['aspect_id'])) {
            $parts[] = 'ASPECT-' . $filters['aspect_id'];
        }
        if (!empty($filters['choice_id'])) {
            $parts[] = 'CHOICE-' . $filters['choice_id'];
        }

        $base = $prefix . '-' . now()->format('Ymd-His');
        if (!empty($parts)) {
            $base .= '-' . implode('_', $parts);
        }

        $base = preg_replace('/[^A-Za-z0-9._-]/', '_', $base);
        $base = trim($base, '_');
        if (strlen($base) > 120) {
            $base = substr($base, 0, 120);
        }

        return $base . '.xlsx';
    }
}
