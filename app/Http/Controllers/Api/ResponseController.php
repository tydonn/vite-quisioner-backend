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
            ->orderBy('ResponID');

        // filter by MahasiswaID
        if ($request->filled('mahasiswa_id')) {
            $query->where('MahasiswaID', $request->mahasiswa_id);
        }

        // filter by DosenID
        if ($request->filled('dosen_id')) {
            $query->where('DosenID', $request->dosen_id);
        }

        return response()->json([
            'success' => true,
            'data' => $query->get(),
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
        $request->validate([
            'MahasiswaID' => 'required|integer',
            'DosenID' => 'required|integer',
            'MatakuliahID' => 'required|integer',
            'TahunAkademik' => 'required|string|max:10',
            'Semester' => 'required|string|max:10',
        ]);

        $response = Response::created($request->all());

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
        $response = Response::findOrFail($id);

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
            'DosenID' => 'sometimes|integer',
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
