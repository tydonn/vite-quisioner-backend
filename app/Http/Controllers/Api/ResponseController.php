<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Quisioner\Response;
use App\Models\Siakad\MataKuliah;
use App\Models\Siakad\Prodi;
use Illuminate\Http\Request;
use App\Support\ProgramScope;

class ResponseController extends Controller
{
    use ProgramScope;

    public function countRespondents(Request $request)
    {
        $scope = $this->resolveProgramScope();
        if (!$scope['is_administrator'] && !$scope['is_legacy_token'] && empty($scope['program_code'])) {
            return $this->unauthorizedProgramScopeResponse();
        }

        $query = Response::query();
        $this->applyProgramScopeToResponseQuery($query, $scope['program_code'], $scope['is_administrator'], $scope['is_legacy_token']);

        if ($request->filled('respon_id')) {
            $query->where('ResponID', $request->respon_id);
        }
        if ($request->filled('mahasiswa_id')) {
            $query->where('MahasiswaID', $request->mahasiswa_id);
        }
        if ($request->filled('dosen_id')) {
            $query->where('DosenID', $request->dosen_id);
        }
        if ($request->filled('matakuliah_id')) {
            $query->where('MatakuliahID', $request->matakuliah_id);
        }
        if ($request->filled('prodi_id') || $request->filled('nama_prodi')) {
            $mkQuery = MataKuliah::query()->select(['MKID']);

            if ($request->filled('prodi_id')) {
                $mkQuery->where('ProdiID', $request->prodi_id);
            }

            if ($request->filled('nama_prodi')) {
                $mkQuery->whereHas('prodi', function ($subQuery) use ($request) {
                    $subQuery->where('Nama', 'like', '%' . $request->nama_prodi . '%');
                });
            }

            $mkIds = $mkQuery->pluck('MKID');

            if ($mkIds->isEmpty()) {
                return response()->json([
                    'success' => true,
                    'data' => [
                        'respondents' => 0,
                    ],
                ]);
            }

            $query->whereIn('MatakuliahID', $mkIds);
        }
        if ($request->filled('tahun_akademik')) {
            $query->where('TahunAkademik', $request->tahun_akademik);
        }
        if ($request->filled('semester')) {
            $query->where('Semester', $request->semester);
        }
        if ($request->filled('created_at')) {
            $query->whereDate('CreatedAt', $request->created_at);
        }
        if ($request->filled('created_at_from')) {
            $query->whereDate('CreatedAt', '>=', $request->created_at_from);
        }
        if ($request->filled('created_at_to')) {
            $query->whereDate('CreatedAt', '<=', $request->created_at_to);
        }

        $count = $query->whereNotNull('MahasiswaID')
            ->distinct()
            ->count('MahasiswaID');

        return response()->json([
            'success' => true,
            'data' => [
                'respondents' => $count,
            ],
        ]);
    }
    public function prodiOptions(Request $request)
    {
        $scope = $this->resolveProgramScope();
        if (!$scope['is_administrator'] && !$scope['is_legacy_token'] && empty($scope['program_code'])) {
            return $this->unauthorizedProgramScopeResponse();
        }

        $matakuliahIds = Response::query()
            ->when(!$scope['is_administrator'] && !$scope['is_legacy_token'], function ($query) use ($scope) {
                $this->applyProgramScopeToResponseQuery($query, $scope['program_code'], false, false);
            })
            ->when($request->filled('tahun_akademik'), function ($query) use ($request) {
                $query->where('TahunAkademik', $request->tahun_akademik);
            })
            ->when($request->filled('semester'), function ($query) use ($request) {
                $query->where('Semester', $request->semester);
            })
            ->distinct()
            ->pluck('MatakuliahID');

        if ($matakuliahIds->isEmpty()) {
            return response()->json([
                'success' => true,
                'data' => [],
            ]);
        }

        $prodiIds = MataKuliah::query()
            ->whereIn('MKID', $matakuliahIds)
            ->whereNotNull('ProdiID')
            ->distinct()
            ->pluck('ProdiID');

        $prodiQuery = Prodi::query()
            ->select(['ProdiID', 'Nama'])
            ->whereIn('ProdiID', $prodiIds)
            ->orderBy('Nama');

        if ($request->filled('q')) {
            $prodiQuery->where('Nama', 'like', '%' . $request->q . '%');
        }

        return response()->json([
            'success' => true,
            'data' => $prodiQuery->get(),
        ]);
    }

    public function matakuliahOptions(Request $request)
    {
        $scope = $this->resolveProgramScope();
        if (!$scope['is_administrator'] && !$scope['is_legacy_token'] && empty($scope['program_code'])) {
            return $this->unauthorizedProgramScopeResponse();
        }

        $matakuliahIds = Response::query()
            ->when(!$scope['is_administrator'] && !$scope['is_legacy_token'], function ($query) use ($scope) {
                $this->applyProgramScopeToResponseQuery($query, $scope['program_code'], false, false);
            })
            ->when($request->filled('tahun_akademik'), function ($query) use ($request) {
                $query->where('TahunAkademik', $request->tahun_akademik);
            })
            ->when($request->filled('semester'), function ($query) use ($request) {
                $query->where('Semester', $request->semester);
            })
            ->distinct()
            ->pluck('MatakuliahID');

        if ($matakuliahIds->isEmpty()) {
            return response()->json([
                'success' => true,
                'data' => [],
            ]);
        }

        $matakuliahQuery = MataKuliah::query()
            ->select(['MKID', 'MKKode', 'Nama', 'ProdiID'])
            ->with(['prodi:ProdiID,Nama'])
            ->whereIn('MKID', $matakuliahIds)
            ->orderBy('Nama');

        if ($request->filled('prodi_id')) {
            $matakuliahQuery->where('ProdiID', $request->prodi_id);
        }

        if ($request->filled('q')) {
            $matakuliahQuery->where('Nama', 'like', '%' . $request->q . '%');
        }

        $limit = (int) $request->get('limit', 200);
        if ($limit < 1) {
            $limit = 200;
        }
        if ($limit > 1000) {
            $limit = 1000;
        }

        return response()->json([
            'success' => true,
            'data' => $matakuliahQuery->limit($limit)->get(),
        ]);
    }

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
        $query = Response::query()
            ->select([
                'ResponID',
                'MahasiswaID',
                'DosenID',
                'MatakuliahID',
                'TahunAkademik',
                'Semester',
                'CreatedAt',
            ])
            ->orderBy('ResponID');
        $this->applyProgramScopeToResponseQuery($query, $scope['program_code'], $scope['is_administrator'], $scope['is_legacy_token']);

        if ($withRelations) {
            $query->with([
                'dosen:Login,Nama',
                'matakuliah:MKID,MKKode,Nama,ProdiID',
                'matakuliah.prodi:ProdiID,Nama',
            ]);
        }

        // filter by ResponID
        if ($request->filled('respon_id')) {
            $query->where('ResponID', $request->respon_id);
        }

        // filter by MahasiswaID
        if ($request->filled('mahasiswa_id')) {
            $query->where('MahasiswaID', $request->mahasiswa_id);
        }

        // filter by DosenID
        if ($request->filled('dosen_id')) {
            $query->where('DosenID', $request->dosen_id);
        }

        // filter by MatakuliahID
        if ($request->filled('matakuliah_id')) {
            $query->where('MatakuliahID', $request->matakuliah_id);
        }

        // filter by ProdiID / nama prodi via mata kuliah (cross-database safe)
        if ($request->filled('prodi_id') || $request->filled('nama_prodi')) {
            $mkQuery = MataKuliah::query()->select(['MKID']);

            if ($request->filled('prodi_id')) {
                $mkQuery->where('ProdiID', $request->prodi_id);
            }

            if ($request->filled('nama_prodi')) {
                $mkQuery->whereHas('prodi', function ($subQuery) use ($request) {
                    $subQuery->where('Nama', 'like', '%' . $request->nama_prodi . '%');
                });
            }

            $mkIds = $mkQuery->pluck('MKID');

            if ($mkIds->isEmpty()) {
                $query->whereRaw('1 = 0');
            } else {
                $query->whereIn('MatakuliahID', $mkIds);
            }
        }

        // filter by TahunAkademik
        if ($request->filled('tahun_akademik')) {
            $query->where('TahunAkademik', $request->tahun_akademik);
        }

        // filter by Semester
        if ($request->filled('semester')) {
            $query->where('Semester', $request->semester);
        }

        // filter by CreatedAt (exact date)
        if ($request->filled('created_at')) {
            $query->whereDate('CreatedAt', $request->created_at);
        }

        // filter by CreatedAt range
        if ($request->filled('created_at_from')) {
            $query->whereDate('CreatedAt', '>=', $request->created_at_from);
        }
        if ($request->filled('created_at_to')) {
            $query->whereDate('CreatedAt', '<=', $request->created_at_to);
        }

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
        //validate request
        $data = $request->validate([
            'MahasiswaID' => 'required|integer',
            'DosenID' => 'required|string',
            'MatakuliahID' => 'required|integer',
            'TahunAkademik' => 'required|string|max:10',
            'Semester' => 'required|string|max:10',
        ]);

        $response = Response::create($data);

        return response()->json([
            'success' => true,
            'data' => $response,
        ], 201);
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

        $query = Response::with([
            'dosen:Login,Nama',
            'mahasiswa:MhswID,Nama',
            'matakuliah:MKID,MKKode,Nama,ProdiID',
            'matakuliah.prodi:ProdiID,Nama',
        ]);
        $this->applyProgramScopeToResponseQuery($query, $scope['program_code'], $scope['is_administrator'], $scope['is_legacy_token']);

        $response = $query->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $response,
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
        $response = Response::findOrFail($id);

        $data = $request->validate([
            'MahasiswaID' => 'sometimes|integer',
            'DosenID' => 'sometimes|string',
            'MatakuliahID' => 'sometimes|integer',
            'TahunAkademik' => 'sometimes|string|max:10',
            'Semester' => 'sometimes|string|max:10',
        ]);

        $response->update($data);

        return response()->json([
            'success' => true,
            'data' => $response,
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
        $response = Response::findOrFail($id);
        $response->delete();

        return response()->json([
            'success' => true,
            'message' => 'Response deleted',
        ]);
    }

    private function applyProgramScopeToResponseQuery($query, ?string $programCode, bool $isAdministrator, bool $isLegacyToken): void
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

        $query->whereIn('MatakuliahID', $mkIds);
    }
}
