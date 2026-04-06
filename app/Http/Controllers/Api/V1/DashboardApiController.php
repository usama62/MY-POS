<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Sale;
use Illuminate\Http\JsonResponse;

class DashboardApiController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $todaySales = Sale::query()->whereDate('sold_at', today())->sum('total');
        $totalProducts = Product::query()->count();
        $lowStock = Product::query()->where('stock', '<=', 5)->count();

        $recentSales = Sale::query()
            ->latest('sold_at')
            ->limit(10)
            ->get(['id', 'reference', 'total', 'sold_at']);

        return response()->json([
            'today_sales' => (float) $todaySales,
            'total_products' => $totalProducts,
            'low_stock_items' => $lowStock,
            'recent_sales' => $recentSales,
        ]);
    }
}
