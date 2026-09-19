<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SalePayment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SaleApiController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->integer('per_page', 20);

        $sales = Sale::query()
            ->with(['customer:id,name', 'items.product:id,name,sku', 'payments'])
            ->latest('sold_at')
            ->paginate(min($perPage, 100));

        return response()->json($sales);
    }

    public function store(Request $request): JsonResponse
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
            // Backward-compatible single tender
            'paid_amount' => ['nullable', 'numeric', 'min:0'],
            'payment_method' => ['nullable', 'string', 'max:30'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.discount_amount' => ['nullable', 'numeric', 'min:0'],
            'items.*.discount_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);

        $discount = (float) ($validated['discount'] ?? 0);
        $tax = (float) ($validated['tax'] ?? 0);

        $cashAmount = (float) ($validated['cash_amount'] ?? 0);
        $cardAmount = (float) ($validated['card_amount'] ?? 0);
        $bankAmount = (float) ($validated['bank_transfer_amount'] ?? 0);

        if ($cashAmount + $cardAmount + $bankAmount <= 0 && isset($validated['paid_amount'])) {
            $method = strtolower((string) ($validated['payment_method'] ?? SalePayment::METHOD_CASH));
            $paid = (float) $validated['paid_amount'];
            match ($method) {
                SalePayment::METHOD_CARD => $cardAmount = $paid,
                SalePayment::METHOD_BANK_TRANSFER => $bankAmount = $paid,
                default => $cashAmount = $paid,
            };
        }

        $resolvedLines = [];
        $subtotalPreview = 0;

        foreach ($validated['items'] as $item) {
            $product = Product::query()->findOrFail($item['product_id']);
            $quantity = (int) $item['quantity'];

            if ($product->stock < $quantity) {
                throw ValidationException::withMessages([
                    'items' => [__('pos.not_enough_stock', ['name' => $product->name])],
                ]);
            }

            $unitPrice = (float) $product->price;
            $lineSubtotal = round($unitPrice * $quantity, 2);
            [$lineDiscountAmount, $lineDiscountPercent] = $this->resolveLineDiscount(
                $lineSubtotal,
                (float) ($item['discount_amount'] ?? 0),
                (float) ($item['discount_percent'] ?? 0),
            );
            $lineTotal = round(max(0, $lineSubtotal - $lineDiscountAmount), 2);
            $subtotalPreview += $lineTotal;

            $resolvedLines[] = [
                'product_id' => $product->id,
                'quantity' => $quantity,
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
            $subtotal = 0;
            $itemPayload = [];

            foreach ($resolvedLines as $line) {
                $product = Product::query()->lockForUpdate()->findOrFail($line['product_id']);
                $subtotal += $line['line_total'];
                $itemPayload[] = [
                    'product' => $product,
                    'quantity' => $line['quantity'],
                    'unit_price' => $line['unit_price'],
                    'line_subtotal' => $line['line_subtotal'],
                    'discount_amount' => $line['discount_amount'],
                    'discount_percent' => $line['discount_percent'],
                    'line_total' => $line['line_total'],
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
                $sale->items()->create([
                    'product_id' => $line['product']->id,
                    'quantity' => $line['quantity'],
                    'unit_price' => $line['unit_price'],
                    'line_subtotal' => $line['line_subtotal'],
                    'discount_amount' => $line['discount_amount'],
                    'discount_percent' => $line['discount_percent'],
                    'line_total' => $line['line_total'],
                ]);

                $line['product']->decrement('stock', $line['quantity']);
            }

            return $sale;
        });

        $sale->load(['customer:id,name', 'items.product:id,name,sku', 'payments']);

        return response()->json($sale, 201);
    }

    public function show(Sale $sale): JsonResponse
    {
        $sale->load(['customer:id,name,phone,email', 'items.product:id,name,sku', 'payments']);

        return response()->json($sale);
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
}
