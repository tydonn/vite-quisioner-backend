<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Quisioner\Choice;
use Illuminate\Http\Request;

class ChoiceController extends Controller
{
    /**
     * GET /api/choices
     */
    public function index(Request $request)
    {
        //
        $query = Choice::query()
            ->with('question')
            ->orderBy('SortOrder');

        // filter by aspect
        if ($request->filled('aspect_id')) {
            $query->where('AspectID', $request->aspect_id);
        }

        // active only
        if ($request->filled('active')) {
            $query->where('IsActive', $request->active);
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
        //
        $data = $request->validate([
            'AspectID' => 'required|integer',
            'ChoiceLabel' => 'required|string|max:255',
            'ChoiceValue' => 'nullable|integer',
            'SortOrder' => 'nullable|integer',
            'IsActive' => 'nullable|boolean',
        ]);

        $choice = Choice::create($data);

        return response()->json([
            'success' => true,
            'data' => $choice,
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
        $question = Choice::with(['question'])
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $question,
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
    public function update(Request $request, $id)
    {
        //
        $choice = Choice::findOrFail($id);

        $data = $request->validate([
            'AspectID' => 'sometimes|integer',
            'ChoiceLabel' => 'sometimes|string|max:255',
            'ChoiceValue' => 'nullable|integer',
            'SortOrder' => 'nullable|integer',
            'IsActive' => 'nullable|boolean',
        ]);

        $choice->update($data);

        return response()->json([
            'success' => true,
            'data' => $choice,
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
        $choice = Choice::findOrFail($id);
        $choice->delete();

        return response()->json([
            'success' => true,
            'message' => 'Choice deleted',
        ]);
    }
}
