<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Quisioner\ChoiceType;
use Illuminate\Http\Request;

class ChoiceTypeController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        //
        $query = ChoiceType::query()
            ->orderBy('ChoiceTypeID');

        // filter by choice type
        if ($request->filled('choice_type_id')) {
            $query->where('ChoiceTypeID', $request->choice_type_id);
        }

        // active only
        if ($request->filled('active')) {
            $query->where('IsActive', $request->active);
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
        //
        $data = $request->validate([
            'TypeCode' => 'required|string|max:255',
            'TypeName' => 'required|string|max:255',
            'Description' => 'nullable|string',
            'SortOrder' => 'nullable|integer',
            'IsActive' => 'nullable|boolean',
        ]);

        $choiceType = ChoiceType::create($data);

        return response()->json([
            'success' => true,
            'data' => $choiceType,
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
        $choiceType = ChoiceType::findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $choiceType,
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
        $choiceType = ChoiceType::findOrFail($id);

        $data = $request->validate([
            'TypeCode' => 'sometimes|string|max:255',
            'TypeName' => 'sometimes|string|max:255',
            'Description' => 'nullable|string',
            'IsActive' => 'nullable|boolean',
        ]);

        $choiceType->update($data);

        return response()->json([
            'success' => true,
            'data' => $choiceType,
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
        $choiceType = ChoiceType::findOrFail($id);
        $choiceType->delete();

        return response()->json([
            'success' => true,
            'message' => 'Choice type deleted',
        ]);
    }
}
