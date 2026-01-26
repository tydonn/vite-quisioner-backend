<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Quisioner\Category;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    //
    public function index()
    {
        return response()->json([
            'success' => true,
            'data' => Category::where('IsActive', 1)
                ->orderBy('SortOrder')
                ->get()
        ]);
    }
}
