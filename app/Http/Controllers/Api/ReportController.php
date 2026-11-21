<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function __construct(
        private readonly ReportService $reports,
    ) {}

    public function overview(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $this->reports->overview(
                $request->query('date_from'),
                $request->query('date_to'),
            ),
        ]);
    }

    public function revenue(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $this->reports->revenue(
                $request->query('date_from'),
                $request->query('date_to'),
            ),
        ]);
    }

    public function inventoryAlerts(): JsonResponse
    {
        return response()->json([
            'data' => $this->reports->inventoryAlerts(),
        ]);
    }
}
