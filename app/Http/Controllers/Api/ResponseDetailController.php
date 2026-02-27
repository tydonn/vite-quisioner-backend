<?php

namespace App\Http\Controllers\Api;

use App\Exports\ResponseDetailsExport;
use App\Http\Controllers\Controller;
use App\Models\Quisioner\ResponseDetail;
use App\Models\Siakad\MataKuliah;
use Illuminate\Http\Request;
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
        $fileName = 'response-details-' . now()->format('Ymd-His') . '.xlsx';
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

        return Excel::download(
            new ResponseDetailsExport($filters, $chunkSize),
            $fileName
        );
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

}
