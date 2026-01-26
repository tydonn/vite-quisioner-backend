<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Quisioner\Question;
use Illuminate\Http\Request;

class QuestionController extends Controller
{
    //
    /**
     * GET /api/questions
     */
    public function index(Request $request)
    {
        $query = Question::query()
            ->with('category')
            ->orderBy('SortOrder');

        // filter by category
        if ($request->filled('category_id')) {
            $query->where('CategoryID', $request->category_id);
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
     * GET /api/questions/{id}
     */
    public function show($id)
    {
        $question = Question::with(['category', 'choiceType'])
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $question,
        ]);
    }

    /**
     * POST /api/questions
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'CategoryID' => 'required|integer',
            'AspectText' => 'required|string|max:255',
            'AnswerType' => 'nullable|in:CHOICE,TEXT,NUMBER',
            'ChoiceTypeID' => 'nullable|integer',
            'SortOrder' => 'nullable|integer',
            'IsActive' => 'nullable|boolean',
        ]);

        $question = Question::create($data);

        return response()->json([
            'success' => true,
            'data' => $question,
        ], 201);
    }

    /**
     * PUT /api/questions/{id}
     */
    public function update(Request $request, $id)
    {
        $question = Question::findOrFail($id);

        $data = $request->validate([
            'CategoryID' => 'sometimes|integer',
            'AspectText' => 'sometimes|string|max:255',
            'AnswerType' => 'sometimes|in:CHOICE,TEXT,NUMBER',
            'ChoiceTypeID' => 'nullable|integer',
            'SortOrder' => 'nullable|integer',
            'IsActive' => 'nullable|boolean',
        ]);

        $question->update($data);

        return response()->json([
            'success' => true,
            'data' => $question,
        ]);
    }

    /**
     * DELETE /api/questions/{id}
     */
    public function destroy($id)
    {
        $question = Question::findOrFail($id);
        $question->delete();

        return response()->json([
            'success' => true,
            'message' => 'Question deleted',
        ]);
    }
}
