<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Quisioner\Choice;
use App\Models\Quisioner\Question;
use App\Models\Quisioner\Respondent;
use App\Support\ProgramScope;
use Illuminate\Http\Request;

class ChoiceController extends Controller
{
    use ProgramScope;

    /**
     * GET /api/choices
     */
    public function index(Request $request)
    {
        $scope = $this->resolveProgramScope();
        if (!$scope['is_administrator'] && empty($scope['program_code'])) {
            return $this->unauthorizedProgramScopeResponse();
        }

        $query = Choice::query()
            ->select([
                'ChoiceID',
                'AspectID',
                'ChoiceLabel',
                'ChoiceValue',
                'SortOrder',
                'IsActive',
            ])
            ->with('question:AspectID,CategoryID,RespondentID,AspectText,AnswerType')
            ->orderBy('SortOrder');
        $this->applyChoiceQuestionScope($query, $scope);

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
        $scope = $this->resolveProgramScope();
        if (!$scope['is_administrator'] && empty($scope['program_code'])) {
            return $this->unauthorizedProgramScopeResponse();
        }

        $data = $request->validate([
            'AspectID' => 'required|integer',
            'ChoiceLabel' => 'required|string|max:255',
            'ChoiceValue' => 'nullable|integer',
            'SortOrder' => 'nullable|integer',
            'IsActive' => 'nullable|boolean',
        ]);

        if (!$this->isAspectAllowedForScope((int) $data['AspectID'], $scope)) {
            return response()->json([
                'success' => false,
                'message' => 'Question is not available for this program scope.',
            ], 403);
        }

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
        $scope = $this->resolveProgramScope();
        if (!$scope['is_administrator'] && empty($scope['program_code'])) {
            return $this->unauthorizedProgramScopeResponse();
        }

        $query = Choice::with(['question:AspectID,CategoryID,RespondentID,AspectText,AnswerType'])
            ->select([
                'ChoiceID',
                'AspectID',
                'ChoiceLabel',
                'ChoiceValue',
                'SortOrder',
                'IsActive',
            ]);
        $this->applyChoiceQuestionScope($query, $scope);

        $choice = $query->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $choice,
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
        $scope = $this->resolveProgramScope();
        if (!$scope['is_administrator'] && empty($scope['program_code'])) {
            return $this->unauthorizedProgramScopeResponse();
        }

        $query = Choice::query();
        $this->applyChoiceQuestionScope($query, $scope);
        $choice = $query->findOrFail($id);

        $data = $request->validate([
            'AspectID' => 'sometimes|integer',
            'ChoiceLabel' => 'sometimes|string|max:255',
            'ChoiceValue' => 'nullable|integer',
            'SortOrder' => 'nullable|integer',
            'IsActive' => 'nullable|boolean',
        ]);

        if (array_key_exists('AspectID', $data) && !$this->isAspectAllowedForScope((int) $data['AspectID'], $scope)) {
            return response()->json([
                'success' => false,
                'message' => 'Question is not available for this program scope.',
            ], 403);
        }

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
        $scope = $this->resolveProgramScope();
        if (!$scope['is_administrator'] && empty($scope['program_code'])) {
            return $this->unauthorizedProgramScopeResponse();
        }

        $query = Choice::query();
        $this->applyChoiceQuestionScope($query, $scope);
        $choice = $query->findOrFail($id);
        $choice->delete();

        return response()->json([
            'success' => true,
            'message' => 'Choice deleted',
        ]);
    }

    private function applyChoiceQuestionScope($query, array $scope): void
    {
        if ($scope['is_administrator']) {
            return;
        }

        $programCode = (string) ($scope['program_code'] ?? '');
        if ($programCode === '') {
            $query->whereRaw('1 = 0');
            return;
        }

        $respondentIds = $this->resolveMahasiswaRespondentIds();
        if (empty($respondentIds)) {
            $query->whereRaw('1 = 0');
            return;
        }

        $prodiCandidates = $this->normalizeProdiIdCandidates($programCode);
        $query->whereHas('question', function ($questionQuery) use ($respondentIds, $prodiCandidates) {
            $questionQuery
                ->whereIn('RespondentID', $respondentIds)
                ->whereHas('questionProdis', function ($prodiQuery) use ($prodiCandidates) {
                    $prodiQuery->where(function ($q) use ($prodiCandidates) {
                        $q->whereIn('ProdiID', $prodiCandidates)
                            ->orWhere('ProdiID', '999999');
                    });
                });
        });
    }

    private function isAspectAllowedForScope(int $aspectId, array $scope): bool
    {
        if ($scope['is_administrator']) {
            return true;
        }

        $programCode = (string) ($scope['program_code'] ?? '');
        if ($programCode === '') {
            return false;
        }

        $respondentIds = $this->resolveMahasiswaRespondentIds();
        if (empty($respondentIds)) {
            return false;
        }

        $prodiCandidates = $this->normalizeProdiIdCandidates($programCode);
        $query = Question::query()
            ->where('AspectID', $aspectId)
            ->whereIn('RespondentID', $respondentIds)
            ->whereHas('questionProdis', function ($prodiQuery) use ($prodiCandidates) {
                $prodiQuery->where(function ($q) use ($prodiCandidates) {
                    $q->whereIn('ProdiID', $prodiCandidates)
                        ->orWhere('ProdiID', '999999');
                });
            });

        return $query->exists();
    }

    private function resolveMahasiswaRespondentIds(): array
    {
        return Respondent::query()
            ->where(function ($subQuery) {
                $subQuery->where('RespondentName', 'like', '%mahasiswa%')
                    ->orWhere('LevelID', 'like', '%mahasiswa%')
                    ->orWhere('LevelID', 'MHS');
            })
            ->pluck('RespondentID')
            ->values()
            ->all();
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
