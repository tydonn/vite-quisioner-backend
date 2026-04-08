<?php

use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\ChoiceController;
use App\Http\Controllers\Api\ChoiceTypeController;
use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\Api\QuestionController;
use App\Http\Controllers\Api\ResponseController;
use App\Http\Controllers\Api\ResponseDetailController;
use App\Http\Controllers\Api\Siakad\DosenController;
use App\Http\Controllers\AuthController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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

// Health check (no role check)


Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', function (Request $request) {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Logout berhasil'
        ]);
    });

    // // Routes resources for categories
    Route::get('categories/count', [CategoryController::class, 'count']);
    Route::apiResource('categories', CategoryController::class);
    // Route resources for questions
    Route::get('questions/count', [QuestionController::class, 'count']);
    Route::apiResource('questions', QuestionController::class);
    // Route resources for choices
    Route::apiResource('choices', ChoiceController::class);
    // Route resources for choice types
    Route::apiResource('choice-types', ChoiceTypeController::class);
    // Route options for response filters (dropdown)
    Route::get('responses/filter-options/prodi', [ResponseController::class, 'prodiOptions']);
    Route::get('responses/filter-options/matakuliah', [ResponseController::class, 'matakuliahOptions']);
    Route::get('responses/count-respondents', [ResponseController::class, 'countRespondents']);
    // Route resources for responses
    Route::apiResource('responses', ResponseController::class);
    // Route download response details (must be above apiResource)
    Route::get('response-details/download', [ResponseDetailController::class, 'download']);
    Route::get('response-details/satisfaction-labels', [ResponseDetailController::class, 'satisfactionLabels']);
    Route::get('response-details/label-counts', [ResponseDetailController::class, 'labelCounts']);
    // // Async export response details
    // Route::post('response-details/exports', [ResponseDetailController::class, 'requestExport']);
    // Route::get('response-details/exports/{id}', [ResponseDetailController::class, 'exportStatus']);
    // Route::get('response-details/exports/{id}/download', [ResponseDetailController::class, 'exportDownload']);
    // Route resources for response details
    Route::apiResource('response-details', ResponseDetailController::class);

    // Route for dosen (siakad)
    Route::apiResource('dosen', DosenController::class);

    // Health check routes
    Route::get('/health/db/quisioner', [HealthController::class, 'db']);
    Route::get('/health/db/siakad', [HealthController::class, 'dbSiakad']);
});

// Route::get('/test-siakad', function () {
//     try {
//         DB::connection('siakad')->getPdo();

//         return response()->json([
//             'success' => true,
//             'message' => 'Koneksi DB siakad OK'
//         ]);
//     } catch (\Exception $e) {
//         return response()->json([
//             'success' => false,
//             'error' => $e->getMessage()
//         ], 500);
//     }
// });
