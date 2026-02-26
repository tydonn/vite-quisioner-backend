<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Quisioner\ResponseDetail;
use App\Models\Siakad\Dosen;
use App\Models\Siakad\Mahasiswa;
use App\Models\Siakad\MataKuliah;
use App\Models\Siakad\Prodi;
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
        $query = $this->buildDownloadQuery($request);
        $fileName = 'response-details-' . now()->format('Ymd-His') . '.csv';
        $chunkSize = (int) $request->get('chunk_size', 1000);
        if ($chunkSize < 100) {
            $chunkSize = 100;
        }
        if ($chunkSize > 5000) {
            $chunkSize = 5000;
        }

        return response()->streamDownload(function () use ($query, $chunkSize) {
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

            $query->chunkById($chunkSize, function ($rows) use ($output) {
                $dosenIds = $rows->pluck('DosenID')->filter()->unique()->values()->all();
                $mahasiswaIds = $rows->pluck('MahasiswaID')->filter()->unique()->values()->all();
                $matakuliahIds = $rows->pluck('MatakuliahID')->filter()->unique()->values()->all();

                $dosenMap = Dosen::query()
                    ->whereIn('Login', $dosenIds)
                    ->pluck('Nama', 'Login');

                $mahasiswaMap = Mahasiswa::query()
                    ->whereIn('MhswID', $mahasiswaIds)
                    ->pluck('Nama', 'MhswID');

                $matakuliahRows = MataKuliah::query()
                    ->select(['MKID', 'Nama', 'ProdiID'])
                    ->whereIn('MKID', $matakuliahIds)
                    ->get();

                $prodiIds = $matakuliahRows->pluck('ProdiID')->filter()->unique()->values()->all();
                $prodiMap = Prodi::query()
                    ->whereIn('ProdiID', $prodiIds)
                    ->pluck('Nama', 'ProdiID');

                $matakuliahMap = $matakuliahRows->keyBy('MKID');

                foreach ($rows as $row) {
                    $matakuliah = $matakuliahMap->get($row->MatakuliahID);
                    $prodiId = optional($matakuliah)->ProdiID;

                    fputcsv($output, [
                        $row->DetailID,
                        $row->ResponID,
                        $row->AspectID,
                        $row->ChoiceID,
                        $row->AnswerText,
                        $row->AnswerNumber,
                        $row->TahunAkademik,
                        $row->Semester,
                        $row->DosenID,
                        $dosenMap[$row->DosenID] ?? null,
                        $row->MahasiswaID,
                        $mahasiswaMap[$row->MahasiswaID] ?? null,
                        $row->MatakuliahID,
                        optional($matakuliah)->Nama,
                        $prodiId,
                        $prodiMap[$prodiId] ?? null,
                        $row->AspectText,
                        $row->ChoiceLabel,
                        $row->ChoiceValue,
                    ]);
                }
            }, 'rd.DetailID', 'DetailID');

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

    private function buildDownloadQuery(Request $request)
    {
        $query = ResponseDetail::query()
            ->from('dk_tbl_response_detail as rd')
            ->join('dk_tbl_response as r', 'rd.ResponID', '=', 'r.ResponID')
            ->leftJoin('dk_tbl_question as q', 'rd.AspectID', '=', 'q.AspectID')
            ->leftJoin('dk_tbl_choice as c', 'rd.ChoiceID', '=', 'c.ChoiceID')
            ->select([
                'rd.DetailID as DetailID',
                'rd.ResponID as ResponID',
                'rd.AspectID as AspectID',
                'rd.ChoiceID as ChoiceID',
                'rd.AnswerText as AnswerText',
                'rd.AnswerNumber as AnswerNumber',
                'r.TahunAkademik as TahunAkademik',
                'r.Semester as Semester',
                'r.DosenID as DosenID',
                'r.MahasiswaID as MahasiswaID',
                'r.MatakuliahID as MatakuliahID',
                'q.AspectText as AspectText',
                'c.ChoiceLabel as ChoiceLabel',
                'c.ChoiceValue as ChoiceValue',
            ])
            ->orderBy('rd.DetailID');

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
                $query->whereIn('r.MatakuliahID', $mkIds);
            }
        }

        return $query;
    }
}
