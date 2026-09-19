<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\CompanyProfile;
use App\Models\Product;
use App\Models\ProductUom;
use App\Models\Sale;
use App\Models\SalePayment;
use App\Services\AutoReorderService;
use App\Services\FefoStockService;
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
                'Cash',
                'Card',
                'Bank Transfer',
                'Change',
                'Payment Method',
                'Sold At',
            ]);

            $this->salesQuery($sort, $direction, $from, $to)
                ->with('payments')
                ->cursor()
                ->each(function (Sale $sale) use ($handle): void {
                    $byMethod = $sale->payments->groupBy('method')->map(
                        fn ($rows) => (float) $rows->sum('amount')
                    );

                    fputcsv($handle, [
                        $sale->reference,
                        $sale->customer?->name ?? 'Walk-in',
                        number_format((float) $sale->subtotal, 2, '.', ''),
                        number_format((float) $sale->discount, 2, '.', ''),
                        number_format((float) $sale->tax, 2, '.', ''),
                        number_format((float) $sale->total, 2, '.', ''),
                        number_format((float) $sale->paid_amount, 2, '.', ''),
                        number_format((float) ($byMethod[SalePayment::METHOD_CASH] ?? 0), 2, '.', ''),
                        number_format((float) ($byMethod[SalePayment::METHOD_CARD] ?? 0), 2, '.', ''),
                        number_format((float) ($byMethod[SalePayment::METHOD_BANK_TRANSFER] ?? 0), 2, '.', ''),
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
        $fefo = app(FefoStockService::class);

        $products = Product::query()
            ->where('is_active', true)
            ->with([
                'batches' => fn ($q) => $q->where('quantity', '>', 0)->orderBy('expiry_date')->orderBy('id'),
                'uoms',
            ])
            ->orderBy('name')
            ->get()
            ->map(function (Product $product) use ($fefo) {
                if ($product->uoms->isEmpty()) {
                    $product->ensureDefaultUoms();
                    $product->load('uoms');
                }

                $suggestion = $fefo->suggest($product, 1);
                $uoms = $product->uoms->map(function (ProductUom $uom) use ($product) {
                    $price = $uom->price !== null
                        ? (float) $uom->price
                        : round((float) $product->price * max(1, (int) $uom->factor_to_base), 2);

                    return [
                        'id' => $uom->id,
                        'name' => $uom->name,
                        'factor_to_base' => (int) $uom->factor_to_base,
                        'price' => $price,
                        'is_base' => (bool) $uom->is_base,
                        'max_qty' => $uom->maxSellableFromStock((int) $product->stock),
                    ];
                })->values();

                return [
                    'id' => $product->id,
                    'name' => $product->name,
                    'sku' => $product->sku,
                    'price' => (float) $product->price,
                    'stock' => (int) $product->stock,
                    'batches' => $product->batches->map(fn ($b) => [
                        'id' => $b->id,
                        'batch_no' => $b->batch_no,
                        'expiry_date' => $b->expiry_date->toDateString(),
                        'quantity' => (int) $b->quantity,
                        'days_left' => $b->daysUntilExpiry(),
                    ])->values(),
                    'uoms' => $uoms,
                    'fefo_hint' => $suggestion[0] ?? null,
                ];
            });

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
            'cash_amount' => ['nullable', 'numeric', 'min:0'],
            'card_amount' => ['nullable', 'numeric', 'min:0'],
            'bank_transfer_amount' => ['nullable', 'numeric', 'min:0'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'exists:products,id'],
            'items.*.product_uom_id' => ['nullable', 'exists:product_uoms,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.discount_amount' => ['nullable', 'numeric', 'min:0'],
            'items.*.discount_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);

        $discount = (float) ($validated['discount'] ?? 0);
        $tax = (float) ($validated['tax'] ?? 0);
        $cashAmount = round((float) ($validated['cash_amount'] ?? 0), 2);
        $cardAmount = round((float) ($validated['card_amount'] ?? 0), 2);
        $bankAmount = round((float) ($validated['bank_transfer_amount'] ?? 0), 2);

        $resolvedLines = [];
        $subtotalPreview = 0;

        foreach ($validated['items'] as $item) {
            $product = Product::query()->with('uoms')->findOrFail($item['product_id']);
            if ($product->uoms->isEmpty()) {
                $product->ensureDefaultUoms();
                $product->load('uoms');
            }

            $uomQuantity = (int) $item['quantity'];
            $uom = null;
            if (! empty($item['product_uom_id'])) {
                $uom = $product->uoms->firstWhere('id', (int) $item['product_uom_id']);
            }
            $uom ??= $product->uoms->firstWhere('is_base', true) ?? $product->uoms->first();

            if (! $uom) {
                throw ValidationException::withMessages([
                    'items' => [__('pos.uom_missing', ['name' => $product->name])],
                ]);
            }

            $baseQuantity = $uom->toBaseQuantity($uomQuantity);
            $unitPrice = $uom->unitPrice();
            $lineSubtotal = round($unitPrice * $uomQuantity, 2);
            [$lineDiscountAmount, $lineDiscountPercent] = $this->resolveLineDiscount(
                $lineSubtotal,
                (float) ($item['discount_amount'] ?? 0),
                (float) ($item['discount_percent'] ?? 0),
            );
            $lineTotal = round(max(0, $lineSubtotal - $lineDiscountAmount), 2);

            if ($product->stock < $baseQuantity) {
                throw ValidationException::withMessages([
                    'items' => [__('pos.not_enough_stock_uom', [
                        'name' => $product->name,
                        'uom' => $uom->name,
                        'need' => $uomQuantity,
                        'have' => $uom->maxSellableFromStock((int) $product->stock),
                    ])],
                ]);
            }

            $subtotalPreview += $lineTotal;
            $resolvedLines[] = [
                'product' => $product,
                'uom' => $uom,
                'uom_quantity' => $uomQuantity,
                'base_quantity' => $baseQuantity,
                'unit_price' => $unitPrice,
                'line_subtotal' => $lineSubtotal,
                'discount_amount' => $lineDiscountAmount,
                'discount_percent' => $lineDiscountPercent,
                'line_total' => $lineTotal,
            ];
        }

        $totalPreview = max(0, $subtotalPreview - $discount + $tax);
        $tenders = $this->normalizeTenders($cashAmount, $cardAmount, $bankAmount, $totalPreview);

        $sale = DB::transaction(function () use ($validated, $discount, $tax, $resolvedLines, $tenders): Sale {
            $fefo = app(FefoStockService::class);
            $subtotal = 0;
            $itemPayload = [];

            foreach ($resolvedLines as $line) {
                $product = Product::query()->lockForUpdate()->findOrFail($line['product']->id);
                $allocations = $fefo->allocate($product, $line['base_quantity']);
                $subtotal += $line['line_total'];

                $itemPayload[] = [
                    'product' => $product,
                    'uom' => $line['uom'],
                    'uom_quantity' => $line['uom_quantity'],
                    'base_quantity' => $line['base_quantity'],
                    'unit_price' => $line['unit_price'],
                    'line_subtotal' => $line['line_subtotal'],
                    'discount_amount' => $line['discount_amount'],
                    'discount_percent' => $line['discount_percent'],
                    'line_total' => $line['line_total'],
                    'allocations' => $allocations,
                ];
            }

            $total = max(0, $subtotal - $discount + $tax);

            $sale = Sale::create([
                'customer_id' => $validated['customer_id'] ?? null,
                'subtotal' => $subtotal,
                'discount' => $discount,
                'tax' => $tax,
                'total' => $total,
                'paid_amount' => $tenders['paid'],
                'change_amount' => $tenders['change'],
                'payment_method' => $tenders['method'],
                'reference' => 'INV-'.now()->format('YmdHis').'-'.random_int(100, 999),
                'sold_at' => now(),
            ]);

            foreach ($tenders['payments'] as $payment) {
                $sale->payments()->create($payment);
            }

            foreach ($itemPayload as $line) {
                $saleItem = $sale->items()->create([
                    'product_id' => $line['product']->id,
                    'product_uom_id' => $line['uom']->id,
                    'uom_name' => $line['uom']->name,
                    'uom_factor' => $line['uom']->factor_to_base,
                    'quantity' => $line['uom_quantity'],
                    'base_quantity' => $line['base_quantity'],
                    'unit_price' => $line['unit_price'],
                    'line_subtotal' => $line['line_subtotal'],
                    'discount_amount' => $line['discount_amount'],
                    'discount_percent' => $line['discount_percent'],
                    'line_total' => $line['line_total'],
                ]);

                foreach ($line['allocations'] as $allocation) {
                    $saleItem->batchAllocations()->create([
                        'product_batch_id' => $allocation['batch']?->id,
                        'batch_no' => $allocation['batch_no'],
                        'expiry_date' => $allocation['expiry_date'],
                        'quantity' => $allocation['quantity'],
                    ]);
                }
            }

            return $sale;
        });

        $touchedProductIds = collect($resolvedLines)->pluck('product.id')->unique()->all();
        app(AutoReorderService::class)->syncMany($touchedProductIds);

        $draftCount = \App\Models\PurchaseOrder::query()
            ->where('status', \App\Models\PurchaseOrder::STATUS_DRAFT)
            ->whereHas('items', fn ($q) => $q->whereIn('product_id', $touchedProductIds))
            ->count();

        $status = __('pos.sale_created');
        if ($draftCount > 0) {
            $status .= ' '.__('pos.auto_po_created_hint');
        }

        return redirect()->route('sales.show', $sale)->with('status', $status);
    }

    public function show(Sale $sale): View
    {
        $sale->load(['customer', 'items.product', 'items.batchAllocations', 'payments']);
        $companyProfile = CompanyProfile::query()->first();

        return view('sales.show', compact('sale', 'companyProfile'));
    }

    /**
     * @return array{0: float, 1: float}
     */
    private function resolveLineDiscount(float $lineSubtotal, float $discountAmount, float $discountPercent): array
    {
        $percent = max(0, min(100, $discountPercent));
        $amount = max(0, $discountAmount);

        if ($percent > 0) {
            $amount = round($lineSubtotal * ($percent / 100), 2);
        }

        $amount = min($amount, $lineSubtotal);

        return [$amount, $percent];
    }

    /**
     * @return array{paid: float, change: float, method: string, payments: list<array{method: string, amount: float}>}
     */
    private function normalizeTenders(float $cashAmount, float $cardAmount, float $bankAmount, float $total): array
    {
        $cashAmount = round(max(0, $cashAmount), 2);
        $cardAmount = round(max(0, $cardAmount), 2);
        $bankAmount = round(max(0, $bankAmount), 2);
        $nonCash = round($cardAmount + $bankAmount, 2);
        $paid = round($cashAmount + $nonCash, 2);

        if ($paid < $total) {
            throw ValidationException::withMessages([
                'cash_amount' => [__('pos.paid_less_than_total')],
            ]);
        }

        if ($nonCash > $total + 0.00001) {
            throw ValidationException::withMessages([
                'card_amount' => [__('pos.non_cash_exceeds_total')],
            ]);
        }

        $dueFromCash = round(max(0, $total - $nonCash), 2);
        $change = round(max(0, $cashAmount - $dueFromCash), 2);

        $payments = [];
        foreach ([
            SalePayment::METHOD_CASH => $cashAmount,
            SalePayment::METHOD_CARD => $cardAmount,
            SalePayment::METHOD_BANK_TRANSFER => $bankAmount,
        ] as $method => $amount) {
            if ($amount > 0) {
                $payments[] = [
                    'method' => $method,
                    'amount' => $amount,
                ];
            }
        }

        if ($payments === []) {
            throw ValidationException::withMessages([
                'cash_amount' => [__('pos.payment_required')],
            ]);
        }

        $method = count($payments) === 1 ? $payments[0]['method'] : 'mixed';

        return [
            'paid' => $paid,
            'change' => $change,
            'method' => $method,
            'payments' => $payments,
        ];
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
