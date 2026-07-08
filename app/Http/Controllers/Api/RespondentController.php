<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Quisioner\Respondent;
use Illuminate\Http\Request;

class RespondentController extends Controller
{
    public function index(Request $request)
    {
        $query = Respondent::query()
            ->select([
                'RespondentID',
                'RespondentName',
                'LevelID',
            ])
            ->orderBy('RespondentID');

        if ($request->filled('q')) {
            $query->where(function ($subQuery) use ($request) {
                $subQuery->where('RespondentName', 'like', '%' . $request->q . '%')
                    ->orWhere('LevelID', 'like', '%' . $request->q . '%');
            });
        }

        if ($request->filled('level_id')) {
            $query->where('LevelID', $request->level_id);
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

    public function count(Request $request)
    {
        $query = Respondent::query();

        if ($request->filled('q')) {
            $query->where(function ($subQuery) use ($request) {
                $subQuery->where('RespondentName', 'like', '%' . $request->q . '%')
                    ->orWhere('LevelID', 'like', '%' . $request->q . '%');
            });
        }

        if ($request->filled('level_id')) {
            $query->where('LevelID', $request->level_id);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'total' => $query->count(),
            ],
        ]);
    }

    public function show(string $id)
    {
        $respondent = Respondent::query()
            ->select([
                'RespondentID',
                'RespondentName',
                'LevelID',
            ])
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $respondent,
        ]);
    }
}
