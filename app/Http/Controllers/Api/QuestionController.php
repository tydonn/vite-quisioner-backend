<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Quisioner\Question;
use App\Support\ActivityLogger;
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
            'data' => $this->appendLatestActivityLog($result->items()),
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
     * GET /api/questions/count
     */
    public function count(Request $request)
    {
        $query = Question::query();

        if ($request->filled('category_id')) {
            $query->where('CategoryID', $request->category_id);
        }
        if ($request->filled('active')) {
            $query->where('IsActive', $request->active);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'total' => $query->count(),
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
            'data' => $this->appendLatestActivityLog([$question])[0] ?? $question,
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
        ActivityLogger::log(
            $request,
            'question',
            'create',
            Question::class,
            $question->AspectID,
            auth('jwt')->user(),
            [
                'new_data' => $question->only([
                    'AspectID',
                    'CategoryID',
                    'AspectText',
                    'AnswerType',
                    'ChoiceTypeID',
                    'SortOrder',
                    'IsActive',
                ]),
            ]
        );

        return response()->json([
            'success' => true,
            'data' => $this->appendLatestActivityLog([$question])[0] ?? $question,
        ], 201);
    }

    /**
     * PUT /api/questions/{id}
     */
    public function update(Request $request, $id)
    {
        $question = Question::findOrFail($id);
        $oldData = $question->only([
            'AspectID',
            'CategoryID',
            'AspectText',
            'AnswerType',
            'ChoiceTypeID',
            'SortOrder',
            'IsActive',
        ]);

        $data = $request->validate([
            'CategoryID' => 'sometimes|integer',
            'AspectText' => 'sometimes|string|max:255',
            'AnswerType' => 'sometimes|in:CHOICE,TEXT,NUMBER',
            'ChoiceTypeID' => 'nullable|integer',
            'SortOrder' => 'nullable|integer',
            'IsActive' => 'nullable|boolean',
        ]);

        $question->update($data);
        $question->refresh();
        ActivityLogger::log(
            $request,
            'question',
            'update',
            Question::class,
            $question->AspectID,
            auth('jwt')->user(),
            [
                'old_data' => $oldData,
                'new_data' => $question->only([
                    'AspectID',
                    'CategoryID',
                    'AspectText',
                    'AnswerType',
                    'ChoiceTypeID',
                    'SortOrder',
                    'IsActive',
                ]),
            ]
        );

        return response()->json([
            'success' => true,
            'data' => $this->appendLatestActivityLog([$question])[0] ?? $question,
        ]);
    }

    /**
     * DELETE /api/questions/{id}
     */
    public function destroy($id)
    {
        $question = Question::findOrFail($id);
        $oldData = $question->only([
            'AspectID',
            'CategoryID',
            'AspectText',
            'AnswerType',
            'ChoiceTypeID',
            'SortOrder',
            'IsActive',
        ]);
        $question->delete();
        ActivityLogger::log(
            request(),
            'question',
            'delete',
            Question::class,
            $id,
            auth('jwt')->user(),
            [
                'old_data' => $oldData,
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'Question deleted',
        ]);
    }

    private function appendLatestActivityLog(array $questions): array
    {
        $aspectIds = collect($questions)
            ->pluck('AspectID')
            ->filter()
            ->map(fn ($id) => (string) $id)
            ->unique()
            ->values()
            ->all();

        if (empty($aspectIds)) {
            return $questions;
        }

        $logs = ActivityLog::query()
            ->where('module', 'question')
            ->where('entity_type', Question::class)
            ->whereIn('entity_id', $aspectIds)
            ->orderByDesc('id')
            ->get();

        $activityMap = [];
        foreach ($logs as $log) {
            if (isset($activityMap[$log->entity_id])) {
                continue;
            }

            $activityMap[$log->entity_id] = [
                'id' => $log->id,
                'module' => $log->module,
                'action' => $log->action,
                'entity_type' => $log->entity_type,
                'entity_id' => $log->entity_id,
                'actor_id' => $log->actor_id,
                'actor_name' => $log->actor_name,
                'actor_email' => $log->actor_email,
                'meta' => $log->meta,
                'created_at' => $log->created_at,
                'updated_at' => $log->updated_at,
            ];
        }

        foreach ($questions as $question) {
            $key = (string) ($question->AspectID ?? '');
            $question->setAttribute('activity_log', $activityMap[$key] ?? null);
        }

        return $questions;
    }
}
