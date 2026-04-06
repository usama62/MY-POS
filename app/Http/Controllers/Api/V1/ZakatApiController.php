<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ZakatApiController extends Controller
{
    public function calculate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'cash' => ['required', 'numeric', 'min:0'],
            'inventory_value' => ['required', 'numeric', 'min:0'],
            'receivables' => ['required', 'numeric', 'min:0'],
            'liabilities' => ['required', 'numeric', 'min:0'],
        ]);

        $net = (float) $validated['cash'] + (float) $validated['inventory_value'] + (float) $validated['receivables'] - (float) $validated['liabilities'];
        $zakatAmount = max(0, $net * 0.025);

        return response()->json([
            'inputs' => $validated,
            'net_zakatable_amount' => $net,
            'zakat_rate' => 0.025,
            'zakat_due' => $zakatAmount,
        ]);
    }
}
