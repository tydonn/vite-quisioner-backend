<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Quisioner\Response;
use Illuminate\Http\Request;

class ResponseController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        //
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
            ->with([
                'dosen:Login,Nama',
                'mahasiswa:MhswID,Nama',
            ])
            ->orderBy('ResponID');

        // filter by MahasiswaID
        if ($request->filled('mahasiswa_id')) {
            $query->where('MahasiswaID', $request->mahasiswa_id);
        }

        // filter by DosenID
        if ($request->filled('dosen_id')) {
            $query->where('DosenID', $request->dosen_id);
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
        //
        $response = Response::with([
            'dosen:Login,Nama',
        ])->findOrFail($id);

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
}
