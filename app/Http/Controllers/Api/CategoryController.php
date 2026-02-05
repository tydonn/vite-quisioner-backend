<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Quisioner\Category;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    //
    /**
     * GET /api/categories
     */
    public function index(Request $request)
    {
        $query = Category::query()
            ->orderBy('SortOrder');

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
     * GET /api/categories/{id}
     */
    public function show($id)
    {
        $category = Category::findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $category,
        ]);
    }

    /**
     * POST /api/categories
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'CategoryName' => 'required|string|max:255',
            'Description' => 'nullable|string',
            'SortOrder' => 'nullable|integer',
            'IsActive' => 'nullable|boolean',
        ]);

        $category = Category::create($data);

        return response()->json([
            'success' => true,
            'data' => $category,
        ], 201);
    }

    /**
     * PUT /api/categories/{id}
     */
    public function update(Request $request, $id)
    {
        $category = Category::findOrFail($id);

        $data = $request->validate([
            'CategoryName' => 'sometimes|string|max:255',
            'Description' => 'nullable|string',
            'SortOrder' => 'nullable|integer',
            'IsActive' => 'nullable|boolean',
        ]);

        $category->update($data);

        return response()->json([
            'success' => true,
            'data' => $category,
        ]);
    }

    /**
     * DELETE /api/categories/{id}
     */
    public function destroy($id)
    {
        $category = Category::findOrFail($id);
        $category->delete();

        return response()->json([
            'success' => true,
            'message' => 'Category deleted',
        ]);
    }
}
