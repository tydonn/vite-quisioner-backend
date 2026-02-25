<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Quisioner\ResponseDetail;
use App\Models\Siakad\MataKuliah;
use Illuminate\Http\Request;

class ResponseDetailController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        //
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

        // filter by response
        if ($request->filled('response_id')) {
            $query->where('ResponID', $request->response_id);
        }

        //filter by question
        if ($request->filled('aspect_id')) {
            $query->where('AspectID', $request->aspect_id);
        }

        // filter by choice
        if ($request->filled('choice_id')) {
            $query->where('ChoiceID', $request->choice_id);
        }

        // filter by tahun akademik (response)
        if ($request->filled('tahun_akademik')) {
            $query->whereHas('response', function ($q) use ($request) {
                $q->where('TahunAkademik', $request->tahun_akademik);
            });
        }

        // filter by nama prodi (siakad -> mk -> prodi)
        if ($request->filled('nama_prodi')) {
            $mkIds = MataKuliah::query()
                ->select(['MKID'])
                ->whereHas('prodi', function ($q) use ($request) {
                    $q->where('Nama', 'like', '%' . $request->nama_prodi . '%');
                })
                ->pluck('MKID');

            if ($mkIds->isEmpty()) {
                $query->whereRaw('1 = 0');
            } else {
                $query->whereHas('response', function ($q) use ($mkIds) {
                    $q->whereIn('MatakuliahID', $mkIds);
                });
            }
        }

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
}
