<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Quisioner\Question;
use App\Models\Quisioner\QuestionProdi;
use App\Models\Siakad\Prodi;
use App\Support\ActivityLogger;
use App\Support\ProgramScope;
use Illuminate\Http\Request;

class QuestionController extends Controller
{
    use ProgramScope;

    /**
     * GET /api/questions
     */
    public function index(Request $request)
    {
        $scope = $this->resolveProgramScope();
        if (!$scope['is_administrator'] && empty($scope['program_code'])) {
            return $this->unauthorizedProgramScopeResponse();
        }

        $query = Question::query()
            ->select([
                'AspectID',
                'CategoryID',
                'AspectText',
                'AnswerType',
                'RespondentID',
                'ChoiceTypeID',
                'SortOrder',
                'IsActive',
            ])
            ->with('category:CategoryID,CategoryName,SortOrder,IsActive')
            ->with('respondent:RespondentID,RespondentName')
            ->orderBy('SortOrder');
        $this->applyQuestionProgramScope($query, $scope);

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

        $items = $this->appendLatestActivityLog($result->items());
        $items = $this->appendQuestionProdis($items);

        return response()->json([
            'success' => true,
            'data' => $items,
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
        $scope = $this->resolveProgramScope();
        if (!$scope['is_administrator'] && empty($scope['program_code'])) {
            return $this->unauthorizedProgramScopeResponse();
        }

        $query = Question::query();
        $this->applyQuestionProgramScope($query, $scope);

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
        $scope = $this->resolveProgramScope();
        if (!$scope['is_administrator'] && empty($scope['program_code'])) {
            return $this->unauthorizedProgramScopeResponse();
        }

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
            ->where(function ($query) use ($scope) {
                $this->applyQuestionProgramScope($query, $scope);
            })
            ->findOrFail($id);

        $item = $this->appendLatestActivityLog([$question])[0] ?? $question;
        $item = $this->appendQuestionProdis([$item])[0] ?? $item;

        return response()->json([
            'success' => true,
            'data' => $item,
        ]);
    }

    /**
     * POST /api/questions
     */
    public function store(Request $request)
    {
        $scope = $this->resolveProgramScope();
        if (!$scope['is_administrator'] && empty($scope['program_code'])) {
            return $this->unauthorizedProgramScopeResponse();
        }

        $data = $request->validate([
            'CategoryID' => 'required|integer',
            'AspectText' => 'required|string|max:255',
            'AnswerType' => 'nullable|in:CHOICE,TEXT,NUMBER',
            'ChoiceTypeID' => 'nullable|integer',
            'SortOrder' => 'nullable|integer',
            'IsActive' => 'nullable|boolean',
            'prodi_id' => 'nullable|string|max:20',
            'ProdiID' => 'nullable|string|max:20',
            'prodi_ids' => 'nullable|array',
            'prodi_ids.*' => 'string|max:20',
        ]);

        $questionData = collect($data)->except(['prodi_ids', 'prodi_id', 'ProdiID'])->all();
        $question = Question::create($questionData);
        $prodiIds = $this->resolveAssignedProdiIds($request, $scope);
        $this->syncQuestionProdis($question, $prodiIds, auth('jwt')->user()?->name);

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
            'data' => $this->appendQuestionProdis([$this->appendLatestActivityLog([$question])[0] ?? $question])[0] ?? $question,
        ], 201);
    }

    /**
     * PUT /api/questions/{id}
     */
    public function update(Request $request, $id)
    {
        $scope = $this->resolveProgramScope();
        if (!$scope['is_administrator'] && empty($scope['program_code'])) {
            return $this->unauthorizedProgramScopeResponse();
        }

        $question = Question::query()
            ->where(function ($query) use ($scope) {
                $this->applyQuestionProgramScope($query, $scope);
            })
            ->findOrFail($id);
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
            'prodi_id' => 'nullable|string|max:20',
            'ProdiID' => 'nullable|string|max:20',
            'prodi_ids' => 'nullable|array',
            'prodi_ids.*' => 'string|max:20',
        ]);

        $questionData = collect($data)->except(['prodi_ids', 'prodi_id', 'ProdiID'])->all();
        $question->update($questionData);
        if ($request->has('prodi_ids') || $request->has('prodi_id') || $request->has('ProdiID')) {
            $prodiIds = $this->resolveAssignedProdiIds($request, $scope);
            $this->syncQuestionProdis($question, $prodiIds, auth('jwt')->user()?->name);
        }
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
            'data' => $this->appendQuestionProdis([$this->appendLatestActivityLog([$question])[0] ?? $question])[0] ?? $question,
        ]);
    }

    /**
     * DELETE /api/questions/{id}
     */
    public function destroy($id)
    {
        $scope = $this->resolveProgramScope();
        if (!$scope['is_administrator'] && empty($scope['program_code'])) {
            return $this->unauthorizedProgramScopeResponse();
        }

        $question = Question::query()
            ->where(function ($query) use ($scope) {
                $this->applyQuestionProgramScope($query, $scope);
            })
            ->findOrFail($id);
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
            ->map(fn($id) => (string) $id)
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

    private function appendQuestionProdis(array $questions): array
    {
        $aspectIds = collect($questions)->pluck('AspectID')->filter()->values()->all();
        if (empty($aspectIds)) {
            return $questions;
        }

        $rows = QuestionProdi::query()
            ->whereIn('AspectID', $aspectIds)
            ->orderBy('id')
            ->get(['AspectID', 'ProdiID']);

        $map = [];
        foreach ($rows as $row) {
            $map[$row->AspectID][] = (string) $row->ProdiID;
        }

        $allProdiIds = collect($rows)->pluck('ProdiID')->filter()->unique()->values()->all();
        $prodiMap = Prodi::query()
            ->whereIn('ProdiID', $allProdiIds)
            ->get(['ProdiID', 'Nama'])
            ->keyBy('ProdiID');

        foreach ($questions as $question) {
            $prodiIds = $map[$question->AspectID] ?? [];
            $question->setAttribute('prodi_ids', $prodiIds);
            $question->setAttribute('prodis', collect($prodiIds)->map(function ($prodiId) use ($prodiMap) {
                $prodi = $prodiMap->get($prodiId);
                return [
                    'ProdiID' => $prodiId,
                    'Nama' => $prodi?->Nama,
                ];
            })->values()->all());
        }

        return $questions;
    }

    private function resolveAssignedProdiIds(Request $request, array $scope): array
    {
        $payloadProdiIds = $request->input('prodi_ids', []);
        if (!is_array($payloadProdiIds)) {
            $payloadProdiIds = [$payloadProdiIds];
        }

        foreach (['prodi_id', 'ProdiID'] as $field) {
            if ($request->filled($field)) {
                $payloadProdiIds[] = $request->input($field);
            }
        }

        $fromPayload = collect($payloadProdiIds)
            ->map(fn($id) => trim((string) $id))
            ->filter()
            ->values()
            ->all();

        if (!empty($fromPayload)) {
            return array_values(array_unique($fromPayload));
        }

        if (!$scope['is_administrator'] && !empty($scope['program_code'])) {
            return [(string) $scope['program_code']];
        }

        if ($scope['is_administrator']) {
            return ['999999'];
        }

        return [];
    }

    private function syncQuestionProdis(Question $question, array $prodiIds, ?string $actorName): void
    {
        QuestionProdi::query()->where('AspectID', $question->AspectID)->delete();

        if (empty($prodiIds)) {
            return;
        }

        $now = now();
        $rows = array_map(function ($prodiId) use ($question, $actorName, $now) {
            return [
                'AspectID' => $question->AspectID,
                'ProdiID' => (string) $prodiId,
                'created_by' => $actorName,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }, $prodiIds);

        QuestionProdi::query()->insert($rows);
    }

    private function applyQuestionProgramScope($query, array $scope): void
    {
        if ($scope['is_administrator']) {
            return;
        }

        $programCode = (string) ($scope['program_code'] ?? '');
        if ($programCode === '') {
            $query->whereRaw('1 = 0');
            return;
        }

        $candidates = $this->normalizeProdiIdCandidates($programCode);
        $query->whereHas('questionProdis', function ($subQuery) use ($candidates) {
            $subQuery->where(function ($q) use ($candidates) {
                $q->whereIn('ProdiID', $candidates)
                    ->orWhere('ProdiID', '999999');
            });
        });
    }

    private function normalizeProdiIdCandidates(string $prodiId): array
    {
        $trimmed = trim($prodiId);
        if ($trimmed === '') {
            return [];
        }

        if (!ctype_digit($trimmed)) {
            return [$trimmed];
        }

        $normalized = ltrim($trimmed, '0');
        if ($normalized === '') {
            $normalized = '0';
        }

        return array_values(array_unique([
            $trimmed,
            $normalized,
            str_pad($normalized, 4, '0', STR_PAD_LEFT),
        ]));
    }
}
