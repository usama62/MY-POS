<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Sale;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View|RedirectResponse
    {
        $validator = Validator::make($request->all(), [
            'from_date' => ['nullable', 'date'],
            'to_date' => ['nullable', 'date'],
        ]);

        if ($validator->fails()) {
            return redirect()->route('dashboard')->withErrors($validator)->withInput();
        }

        $fromDate = $request->input('from_date', now()->startOfMonth()->toDateString());
        $toDate = $request->input('to_date', now()->toDateString());

        if ($fromDate > $toDate) {
            return redirect()->route('dashboard')->withErrors([
                'to_date' => __('pos.validation_date_range'),
            ])->withInput();
        }

        $salesInRange = Sale::query()
            ->whereDate('sold_at', '>=', $fromDate)
            ->whereDate('sold_at', '<=', $toDate);

        $todaySales = Sale::query()->whereDate('sold_at', today())->sum('total');
        $totalProducts = Product::query()->count();
        $lowStock = Product::query()->whereColumn('stock', '<=', 'min_stock')->count();
        $draftPoCount = \App\Models\PurchaseOrder::query()->where('status', 'draft')->count();
        $recentSales = Sale::query()->latest('sold_at')->limit(10)->get();
        $monthlySales = (clone $salesInRange)
            ->selectRaw('DATE_FORMAT(sold_at, "%Y-%m-%d") as day_key, SUM(total) as total_amount')
            ->whereNotNull('sold_at')
            ->groupBy('day_key')
            ->orderBy('day_key')
            ->limit(31)
            ->get();

        $chartLabels = $monthlySales->pluck('day_key')->toArray();
        $chartData = $monthlySales->pluck('total_amount')->map(fn ($value) => (float) $value)->toArray();

        return view('dashboard', compact(
            'todaySales',
            'totalProducts',
            'lowStock',
            'draftPoCount',
            'recentSales',
            'chartLabels',
            'chartData',
            'fromDate',
            'toDate'
        ));
    }
}
