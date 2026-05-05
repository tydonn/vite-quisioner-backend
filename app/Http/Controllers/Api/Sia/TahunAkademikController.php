<?php

namespace App\Http\Controllers\Api\Sia;

use App\Http\Controllers\Controller;
use App\Models\Sia\TahunAkademik;
use Illuminate\Http\JsonResponse;

class TahunAkademikController extends Controller
{
    public function options(): JsonResponse
    {
        $data = TahunAkademik::query()
            ->select('TahunID')
            ->whereNotNull('TahunID')
            ->distinct()
            ->orderByDesc('TahunID')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }
}
