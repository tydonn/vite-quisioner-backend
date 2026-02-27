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
            ->select([
                'AspectID',
                'CategoryID',
                'AspectText',
                'AnswerType',
                'ChoiceTypeID',
                'SortOrder',
                'IsActive',
            ])
            ->with('category:CategoryID,CategoryName,SortOrder,IsActive')
            ->orderBy('SortOrder');

        // filter by category
        if ($request->filled('category_id')) {
            $query->where('CategoryID', $request->category_id);
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

    /**
     * GET /api/questions/{id}
     */
    public function show($id)
    {
        $question = Question::with(['category:CategoryID,CategoryName,SortOrder,IsActive'])
            ->select([
                'AspectID',
                'CategoryID',
                'AspectText',
                'AnswerType',
                'ChoiceTypeID',
                'SortOrder',
                'IsActive',
            ])
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
