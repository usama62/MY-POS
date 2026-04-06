<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;

class ZakatController extends Controller
{
    public function index(): View
    {
        return view('zakat.index', [
            'zakatAmount' => null,
            'inputs' => [],
        ]);
    }

    public function calculate(Request $request): View
    {
        $validated = $request->validate([
            'cash' => ['required', 'numeric', 'min:0'],
            'inventory_value' => ['required', 'numeric', 'min:0'],
            'receivables' => ['required', 'numeric', 'min:0'],
            'liabilities' => ['required', 'numeric', 'min:0'],
        ]);

        $net = (float) $validated['cash'] + (float) $validated['inventory_value'] + (float) $validated['receivables'] - (float) $validated['liabilities'];
        $zakatAmount = max(0, $net * 0.025);

        return view('zakat.index', [
            'zakatAmount' => $zakatAmount,
            'inputs' => $validated,
            'netAmount' => $net,
        ]);
    }
}
