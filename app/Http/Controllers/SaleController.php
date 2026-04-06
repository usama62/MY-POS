<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\CompanyProfile;
use App\Models\Product;
use App\Models\Sale;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class SaleController extends Controller
{
    public function index(): View
    {
        $sales = Sale::query()->with('customer')->latest('sold_at')->paginate(20);

        return view('sales.index', compact('sales'));
    }

    public function create(): View
    {
        $products = Product::query()->where('is_active', true)->orderBy('name')->get();
        $customers = Customer::query()->orderBy('name')->get();

        return view('sales.create', compact('products', 'customers'));
    }

    public function store(Request $request): RedirectResponse
    {
        $items = collect($request->input('items', []))
            ->filter(fn ($item) => !empty($item['product_id']))
            ->values()
            ->all();

        $request->merge(['items' => $items]);

        $validated = $request->validate([
            'customer_id' => ['nullable', 'exists:customers,id'],
            'discount' => ['nullable', 'numeric', 'min:0'],
            'tax' => ['nullable', 'numeric', 'min:0'],
            'paid_amount' => ['required', 'numeric', 'min:0'],
            'payment_method' => ['required', 'string', 'max:30'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
        ]);

        $discount = (float) ($validated['discount'] ?? 0);
        $tax = (float) ($validated['tax'] ?? 0);
        $paid = (float) $validated['paid_amount'];

        $subtotalPreview = 0;
        foreach ($validated['items'] as $item) {
            $product = Product::query()->findOrFail($item['product_id']);
            $quantity = (int) $item['quantity'];

            if ($product->stock < $quantity) {
                throw ValidationException::withMessages([
                    'items' => [__('pos.not_enough_stock', ['name' => $product->name])],
                ]);
            }

            $subtotalPreview += $quantity * (float) $product->price;
        }

        $totalPreview = max(0, $subtotalPreview - $discount + $tax);

        if ($paid < $totalPreview) {
            throw ValidationException::withMessages([
                'paid_amount' => [__('pos.paid_less_than_total')],
            ]);
        }

        $sale = DB::transaction(function () use ($validated, $discount, $tax, $paid): Sale {
            $subtotal = 0;
            $itemPayload = [];

            foreach ($validated['items'] as $item) {
                $product = Product::query()->lockForUpdate()->findOrFail($item['product_id']);
                $quantity = (int) $item['quantity'];

                $lineTotal = $quantity * (float) $product->price;
                $subtotal += $lineTotal;

                $itemPayload[] = [
                    'product' => $product,
                    'quantity' => $quantity,
                    'unit_price' => (float) $product->price,
                    'line_total' => $lineTotal,
                ];
            }

            $total = max(0, $subtotal - $discount + $tax);

            $sale = Sale::create([
                'customer_id' => $validated['customer_id'] ?? null,
                'subtotal' => $subtotal,
                'discount' => $discount,
                'tax' => $tax,
                'total' => $total,
                'paid_amount' => $paid,
                'change_amount' => $paid - $total,
                'payment_method' => $validated['payment_method'],
                'reference' => 'INV-'.now()->format('YmdHis').'-'.random_int(100, 999),
                'sold_at' => now(),
            ]);

            foreach ($itemPayload as $line) {
                $sale->items()->create([
                    'product_id' => $line['product']->id,
                    'quantity' => $line['quantity'],
                    'unit_price' => $line['unit_price'],
                    'line_total' => $line['line_total'],
                ]);

                $line['product']->decrement('stock', $line['quantity']);
            }

            return $sale;
        });

        return redirect()->route('sales.show', $sale)->with('status', __('pos.sale_created'));
    }

    public function show(Sale $sale): View
    {
        $sale->load(['customer', 'items.product']);
        $companyProfile = CompanyProfile::query()->first();

        return view('sales.show', compact('sale', 'companyProfile'));
    }
}
