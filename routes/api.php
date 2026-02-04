<?php

use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\ChoiceController;
use App\Http\Controllers\Api\ChoiceTypeController;
use App\Http\Controllers\Api\QuestionController;
use App\Http\Controllers\Api\ResponseController;
use App\Http\Controllers\AuthController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// Get auth user
Route::middleware('auth:sanctum')->get('/me', function (Request $request) {
    return response()->json([
        'success' => true,
        'user' => $request->user(),
    ]);
});

// Auth routes
Route::post('/login', [AuthController::class, 'login']);
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', function (Request $request) {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Logout berhasil'
        ]);
    });

    // // Routes resources for categories
    Route::apiResource('categories', CategoryController::class);
    // Route resources for questions
    Route::apiResource('questions', QuestionController::class);
    // Route resources for choices
    Route::apiResource('choices', ChoiceController::class);
    // Route resources for choice types
    Route::apiResource('choice-types', ChoiceTypeController::class);
    // Route resources for responses
    Route::apiResource('responses', ResponseController::class);
});
