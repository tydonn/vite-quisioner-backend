<?php

use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\ChoiceController;
use App\Http\Controllers\Api\ChoiceTypeController;
use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\Api\JwtAuthController;
use App\Http\Controllers\Api\QuestionController;
use App\Http\Controllers\Api\ResponseController;
use App\Http\Controllers\Api\ResponseDetailController;
use App\Http\Controllers\Api\ResponseDetailResultController;
use App\Http\Controllers\Api\Siakad\DosenController;
use App\Http\Controllers\AuthSSOController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:jwt');

// Get auth user
Route::middleware('auth:jwt')->get('/me', function (Request $request) {
    return response()->json([
        'success' => true,
        'user' => $request->user(),
    ]);
});

// Auth routes
Route::post('/login', function () {
    return response()->json([
        'success' => false,
        'message' => 'Endpoint deprecated. Use /api/jwt/login.',
    ], 410);
});
Route::post('/jwt/sso/init', [AuthSSOController::class, 'init'])->middleware('throttle:sso-init');
Route::post('/jwt/sso/exchange', [AuthSSOController::class, 'exchange'])->middleware('throttle:sso-exchange');
Route::post('/jwt/login', [JwtAuthController::class, 'login'])->middleware('throttle:jwt-login');
Route::middleware('auth:jwt')->prefix('jwt')->group(function () {
    Route::get('/me', [JwtAuthController::class, 'me']);
    Route::post('/logout', [JwtAuthController::class, 'logout']);

    // JWT hybrid routes: master data
    Route::get('categories/count', [CategoryController::class, 'count']);
    Route::apiResource('categories', CategoryController::class);
    Route::get('questions/count', [QuestionController::class, 'count']);
    Route::apiResource('questions', QuestionController::class);
    Route::apiResource('choices', ChoiceController::class);
    Route::apiResource('choice-types', ChoiceTypeController::class);
    Route::apiResource('dosen', DosenController::class);

    // JWT hybrid routes: responses (read-first migration)
    Route::get('responses/filter-options/prodi', [ResponseController::class, 'prodiOptions']);
    Route::get('responses/filter-options/matakuliah', [ResponseController::class, 'matakuliahOptions']);
    Route::get('responses/count-respondents', [ResponseController::class, 'countRespondents']);
    Route::get('responses', [ResponseController::class, 'index']);
    Route::get('responses/{response}', [ResponseController::class, 'show']);

    // JWT hybrid routes: response-details (dashboard/data export)
    Route::get('response-details', [ResponseDetailController::class, 'index']);
    Route::get('response-details/download', [ResponseDetailController::class, 'download']);
    Route::get('response-details/satisfaction-labels', [ResponseDetailController::class, 'satisfactionLabels']);
    Route::get('response-details/label-counts', [ResponseDetailController::class, 'labelCounts']);
    Route::get('response-details/result-by-dosen', [ResponseDetailResultController::class, 'index']);
    Route::get('response-details/{response_detail}', [ResponseDetailController::class, 'show']);

    // Health check routes
    Route::get('/health/db/quisioner', [HealthController::class, 'db']);
    Route::get('/health/db/siakad', [HealthController::class, 'dbSiakad']);
});

// Health check (no role check)


// Route::middleware('auth:sanctum')->group(function () {
//     Route::post('/logout', function (Request $request) {
//         $request->user()->currentAccessToken()->delete();

//         return response()->json([
//             'success' => true,
//             'message' => 'Logout berhasil'
//         ]);
//     });

//     // // Routes resources for categories
//     Route::get('categories/count', [CategoryController::class, 'count']);
//     Route::apiResource('categories', CategoryController::class);
//     // Route resources for questions
//     Route::get('questions/count', [QuestionController::class, 'count']);
//     Route::apiResource('questions', QuestionController::class);
//     // Route resources for choices
//     Route::apiResource('choices', ChoiceController::class);
//     // Route resources for choice types
//     Route::apiResource('choice-types', ChoiceTypeController::class);
//     // Route options for response filters (dropdown)
//     Route::get('responses/filter-options/prodi', [ResponseController::class, 'prodiOptions']);
//     Route::get('responses/filter-options/matakuliah', [ResponseController::class, 'matakuliahOptions']);
//     Route::get('responses/count-respondents', [ResponseController::class, 'countRespondents']);
//     // Route resources for responses
//     Route::apiResource('responses', ResponseController::class);
//     // Route download response details (must be above apiResource)
//     Route::get('response-details/download', [ResponseDetailController::class, 'download']);
//     Route::get('response-details/satisfaction-labels', [ResponseDetailController::class, 'satisfactionLabels']);
//     Route::get('response-details/label-counts', [ResponseDetailController::class, 'labelCounts']);
//     // // Async export response details
//     // Route::post('response-details/exports', [ResponseDetailController::class, 'requestExport']);
//     // Route::get('response-details/exports/{id}', [ResponseDetailController::class, 'exportStatus']);
//     // Route::get('response-details/exports/{id}/download', [ResponseDetailController::class, 'exportDownload']);
//     // Route resources for response details
//     Route::apiResource('response-details', ResponseDetailController::class);

//     // Route for dosen (siakad)
//     Route::apiResource('dosen', DosenController::class);

//     // Health check routes
//     Route::get('/health/db/quisioner', [HealthController::class, 'db']);
//     Route::get('/health/db/siakad', [HealthController::class, 'dbSiakad']);
// });
