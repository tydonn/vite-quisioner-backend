<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Quisioner\ResponseDetail;
use App\Models\Siakad\MataKuliah;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ResponseDetailController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = $this->buildFilteredQuery($request);

        $perPage = (int) $request->get('per_page', 100);
        if ($perPage < 1) {
            $perPage = 100;
        }
        if ($perPage > 500) {
            $perPage = 500;
        }

        $result = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $result->items(),
            'pagination' => [
                'current_page' => $result->currentPage(),
                'per_page' => $result->perPage(),
                'total' => $result->total(),
                'last_page' => $result->lastPage(),
            ],
        ]);
    }

    public function download(Request $request): StreamedResponse
    {
        $query = $this->buildFilteredQuery($request);
        $fileName = 'response-details-' . now()->format('Ymd-His') . '.csv';

        return response()->streamDownload(function () use ($query) {
            $output = fopen('php://output', 'w');

            fputcsv($output, [
                'DetailID',
                'ResponID',
                'AspectID',
                'ChoiceID',
                'AnswerText',
                'AnswerNumber',
                'TahunAkademik',
                'Semester',
                'DosenLogin',
                'DosenNama',
                'MahasiswaID',
                'MahasiswaNama',
                'MatakuliahID',
                'MatakuliahNama',
                'ProdiID',
                'ProdiNama',
                'AspectText',
                'ChoiceLabel',
                'ChoiceValue',
            ]);

            $query->chunkById(500, function ($rows) use ($output) {
                foreach ($rows as $row) {
                    fputcsv($output, [
                        $row->DetailID,
                        $row->ResponID,
                        $row->AspectID,
                        $row->ChoiceID,
                        $row->AnswerText,
                        $row->AnswerNumber,
                        optional($row->response)->TahunAkademik,
                        optional($row->response)->Semester,
                        optional(optional($row->response)->dosen)->Login,
                        optional(optional($row->response)->dosen)->Nama,
                        optional($row->response)->MahasiswaID,
                        optional(optional($row->response)->mahasiswa)->Nama,
                        optional($row->response)->MatakuliahID,
                        optional(optional($row->response)->matakuliah)->Nama,
                        optional(optional($row->response)->matakuliah)->ProdiID,
                        optional(optional(optional($row->response)->matakuliah)->prodi)->Nama,
                        optional($row->question)->AspectText,
                        optional($row->choice)->ChoiceLabel,
                        optional($row->choice)->ChoiceValue,
                    ]);
                }
            }, 'DetailID', 'DetailID');

            fclose($output);
        }, $fileName, [
            'Content-Type' => 'text/csv',
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

    private function buildFilteredQuery(Request $request)
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
            ->with([
                'response:ResponID,MahasiswaID,DosenID,MatakuliahID,TahunAkademik,Semester',
                'response.dosen:Login,Nama',
                'response.mahasiswa:MhswID,Nama',
                'response.matakuliah:MKID,Nama,ProdiID',
                'response.matakuliah.prodi:ProdiID,Nama',
                'question:AspectID,CategoryID,AspectText,AnswerType',
                'choice:ChoiceID,ChoiceLabel,ChoiceValue',
            ])
            ->orderBy('DetailID');

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

        return $query;
    }
}
