<?php

namespace App\Http\Controllers\Api;

use App\Exports\ResponseDetailsExport;
use App\Http\Controllers\Controller;
use App\Jobs\ExportResponseDetailsJob;
use App\Models\ExportTask;
use App\Models\Quisioner\ResponseDetail;
use App\Models\Siakad\MataKuliah;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;

class ResponseDetailController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $withRelations = $request->boolean('with_relations', true);
        $query = $this->buildFilteredQuery($request, $withRelations);

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
        $filters = $request->only([
            'response_id',
            'aspect_id',
            'choice_id',
            'tahun_akademik',
            'nama_prodi',
            'matakuliah_id',
            'nama_matakuliah',
        ]);
        $fileName = $this->buildExportFileName($filters, 'response-details');
        $chunkSize = (int) $request->get('chunk_size', 1000);
        if ($chunkSize < 100) {
            $chunkSize = 100;
        }
        if ($chunkSize > 5000) {
            $chunkSize = 5000;
        }

        $singleQuery = $request->boolean('single_query', false);

        return Excel::download(
            new ResponseDetailsExport($filters, $chunkSize, $singleQuery),
            $fileName
        );
    }

    public function requestExport(Request $request)
    {
        $chunkSize = (int) $request->get('chunk_size', 1000);
        if ($chunkSize < 100) {
            $chunkSize = 100;
        }
        if ($chunkSize > 5000) {
            $chunkSize = 5000;
        }

        $filters = $request->only([
            'response_id',
            'aspect_id',
            'choice_id',
            'tahun_akademik',
            'nama_prodi',
            'matakuliah_id',
            'nama_matakuliah',
        ]);

        $task = ExportTask::query()->create([
            'user_id' => $request->user()?->id,
            'type' => 'response-details',
            'status' => 'queued',
            'filters' => $filters,
            'file_disk' => 'local',
        ]);

        ExportResponseDetailsJob::dispatch(
            $task->id,
            $filters,
            $chunkSize,
            $request->boolean('single_query', false)
        );

        return response()->json([
            'success' => true,
            'data' => [
                'task_id' => $task->id,
                'status' => $task->status,
            ],
        ], 202);
    }

    public function exportStatus(string $id)
    {
        $task = ExportTask::query()->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => [
                'task_id' => $task->id,
                'status' => $task->status,
                'error' => $task->error,
                'file_path' => $task->status === 'completed' ? $task->file_path : null,
                'started_at' => $task->started_at,
                'completed_at' => $task->completed_at,
            ],
        ]);
    }

    public function exportDownload(string $id)
    {
        $task = ExportTask::query()->findOrFail($id);

        if ($task->status !== 'completed' || !$task->file_path) {
            return response()->json([
                'success' => false,
                'message' => 'Export belum selesai',
            ], 409);
        }

        $disk = $task->file_disk ?: 'local';
        if (!Storage::disk($disk)->exists($task->file_path)) {
            return response()->json([
                'success' => false,
                'message' => 'File export tidak ditemukan',
            ], 404);
        }

        $downloadName = $this->buildExportFileName($task->filters ?? [], 'response-details');
        if ($disk === 'local') {
            return response()->download(
                Storage::disk($disk)->path($task->file_path),
                $downloadName
            );
        }

        return response()->json([
            'success' => false,
            'message' => 'Download hanya tersedia untuk disk local',
        ], 400);
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
        //
        $responseDetail = ResponseDetail::with([
            'response:ResponID,MahasiswaID,DosenID,MatakuliahID,TahunAkademik,Semester',
            'response.dosen:Login,Nama',
            'response.mahasiswa:MhswID,Nama',
            'response.matakuliah:MKID,Nama,ProdiID',
            'response.matakuliah.prodi:ProdiID,Nama',
            'question:AspectID,CategoryID,AspectText,AnswerType',
            'choice:ChoiceID,ChoiceLabel,ChoiceValue',
        ])->findOrFail($id);

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

    private function buildFilteredQuery(Request $request, bool $withRelations = true)
    {
        $query = ResponseDetail::query()
            ->select([
                'DetailID',
                'ResponID',
                'AspectID',
                'ChoiceID',
                'AnswerText',
                'AnswerNumber',
            ])
            ->orderBy('DetailID');

        if ($withRelations) {
            $query->with([
                'response:ResponID,MahasiswaID,DosenID,MatakuliahID,TahunAkademik,Semester',
                'response.dosen:Login,Nama',
                'response.mahasiswa:MhswID,Nama',
                'response.matakuliah:MKID,Nama,ProdiID',
                'response.matakuliah.prodi:ProdiID,Nama',
                'question:AspectID,CategoryID,AspectText,AnswerType',
                'choice:ChoiceID,ChoiceLabel,ChoiceValue',
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

        if ($request->filled('nama_prodi')) {
            $mkIds = MataKuliah::query()
                ->select(['MKID'])
                ->whereHas('prodi', function ($subQuery) use ($request) {
                    $subQuery->where('Nama', 'like', '%' . $request->nama_prodi . '%');
                })
                ->pluck('MKID');

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

    private function buildExportFileName(array $filters, string $prefix): string
    {
        $parts = [];

        if (!empty($filters['tahun_akademik'])) {
            $parts[] = 'TA-' . $filters['tahun_akademik'];
        }
        if (!empty($filters['semester'])) {
            $parts[] = 'SEM-' . $filters['semester'];
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
