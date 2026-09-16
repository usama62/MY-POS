<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\CompanyProfile;
use App\Models\Product;
use App\Models\Sale;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SaleController extends Controller
{
    public function index(Request $request): View
    {
        [$sort, $direction] = $this->resolveSort($request);
        [$period, $from, $to] = $this->resolveDateRange($request);

        $sales = $this->salesQuery($sort, $direction, $from, $to)
            ->paginate(20)
            ->withQueryString();

        return view('sales.index', compact('sales', 'sort', 'direction', 'period', 'from', 'to'));
    }

    public function export(Request $request): StreamedResponse
    {
        [$sort, $direction] = $this->resolveSort($request);
        [, $from, $to] = $this->resolveDateRange($request);

        $filename = 'sales-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($sort, $direction, $from, $to): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, [
                'Reference',
                'Customer',
                'Subtotal',
                'Discount',
                'Tax',
                'Total',
                'Paid',
                'Change',
                'Payment Method',
                'Sold At',
            ]);

            $this->salesQuery($sort, $direction, $from, $to)
                ->cursor()
                ->each(function (Sale $sale) use ($handle): void {
                    fputcsv($handle, [
                        $sale->reference,
                        $sale->customer?->name ?? 'Walk-in',
                        number_format((float) $sale->subtotal, 2, '.', ''),
                        number_format((float) $sale->discount, 2, '.', ''),
                        number_format((float) $sale->tax, 2, '.', ''),
                        number_format((float) $sale->total, 2, '.', ''),
                        number_format((float) $sale->paid_amount, 2, '.', ''),
                        number_format((float) $sale->change_amount, 2, '.', ''),
                        $sale->payment_method,
                        optional($sale->sold_at)->format('Y-m-d H:i:s'),
                    ]);
                });

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
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
            ->filter(fn ($item) => ! empty($item['product_id']))
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

    /**
     * @return array{0: string, 1: string}
     */
    private function resolveSort(Request $request): array
    {
        $allowedSorts = ['sold_at', 'total', 'reference', 'customer', 'payment_method'];
        $sort = (string) $request->query('sort', 'sold_at');
        $direction = strtolower((string) $request->query('direction', 'desc')) === 'asc' ? 'asc' : 'desc';

        if (! in_array($sort, $allowedSorts, true)) {
            $sort = 'sold_at';
        }

        return [$sort, $direction];
    }

    /**
     * @return array{0: string, 1: ?Carbon, 2: ?Carbon}
     */
    private function resolveDateRange(Request $request): array
    {
        $allowed = [
            'all',
            'today',
            'yesterday',
            'this_week',
            'last_week',
            'this_month',
            'last_month',
            'this_year',
            'custom',
        ];

        $period = (string) $request->query('period', 'all');
        if (! in_array($period, $allowed, true)) {
            $period = 'all';
        }

        $now = now();
        $from = null;
        $to = null;

        switch ($period) {
            case 'today':
                $from = $now->copy()->startOfDay();
                $to = $now->copy()->endOfDay();
                break;
            case 'yesterday':
                $from = $now->copy()->subDay()->startOfDay();
                $to = $now->copy()->subDay()->endOfDay();
                break;
            case 'this_week':
                $from = $now->copy()->startOfWeek(Carbon::MONDAY);
                $to = $now->copy()->endOfWeek(Carbon::SUNDAY);
                break;
            case 'last_week':
                $from = $now->copy()->subWeek()->startOfWeek(Carbon::MONDAY);
                $to = $now->copy()->subWeek()->endOfWeek(Carbon::SUNDAY);
                break;
            case 'this_month':
                $from = $now->copy()->startOfMonth();
                $to = $now->copy()->endOfMonth();
                break;
            case 'last_month':
                $from = $now->copy()->subMonthNoOverflow()->startOfMonth();
                $to = $now->copy()->subMonthNoOverflow()->endOfMonth();
                break;
            case 'this_year':
                $from = $now->copy()->startOfYear();
                $to = $now->copy()->endOfYear();
                break;
            case 'custom':
                $fromInput = trim((string) $request->query('from', ''));
                $toInput = trim((string) $request->query('to', ''));

                if ($fromInput !== '') {
                    try {
                        $from = Carbon::parse($fromInput)->startOfDay();
                    } catch (\Throwable) {
                        $from = null;
                    }
                }

                if ($toInput !== '') {
                    try {
                        $to = Carbon::parse($toInput)->endOfDay();
                    } catch (\Throwable) {
                        $to = null;
                    }
                }

                if ($from && $to && $from->gt($to)) {
                    [$from, $to] = [$to->copy()->startOfDay(), $from->copy()->endOfDay()];
                }
                break;
            default:
                break;
        }

        return [$period, $from, $to];
    }

    private function salesQuery(string $sort, string $direction, ?Carbon $from = null, ?Carbon $to = null)
    {
        $query = Sale::query()->with('customer');

        if ($from && $to) {
            $query->whereBetween('sales.sold_at', [$from, $to]);
        } elseif ($from) {
            $query->where('sales.sold_at', '>=', $from);
        } elseif ($to) {
            $query->where('sales.sold_at', '<=', $to);
        }

        if ($sort === 'customer') {
            return $query
                ->leftJoin('customers', 'customers.id', '=', 'sales.customer_id')
                ->select('sales.*')
                ->orderBy('customers.name', $direction)
                ->orderBy('sales.id', $direction);
        }

        $column = match ($sort) {
            'total' => 'sales.total',
            'reference' => 'sales.reference',
            'payment_method' => 'sales.payment_method',
            default => 'sales.sold_at',
        };

        return $query->orderBy($column, $direction)->orderBy('sales.id', $direction);
    }
}
