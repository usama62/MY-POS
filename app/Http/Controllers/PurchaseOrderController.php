<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Services\AutoReorderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class PurchaseOrderController extends Controller
{
    public function index(Request $request): View
    {
        $status = (string) $request->query('status', 'draft');
        $allowed = ['draft', 'ordered', 'received', 'cancelled', 'all'];
        if (! in_array($status, $allowed, true)) {
            $status = 'draft';
        }

        $orders = PurchaseOrder::query()
            ->withCount('items')
            ->with('creator')
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        $lowStockProducts = Product::query()
            ->where('reorder_enabled', true)
            ->whereColumn('stock', '<=', 'min_stock')
            ->orderBy('name')
            ->limit(20)
            ->get();

        return view('purchase_orders.index', compact('orders', 'status', 'lowStockProducts'));
    }

    public function show(PurchaseOrder $purchaseOrder): View
    {
        $purchaseOrder->load(['items.product', 'creator']);

        return view('purchase_orders.show', compact('purchaseOrder'));
    }

    public function generate(): RedirectResponse
    {
        $service = app(AutoReorderService::class);
        $count = 0;

        Product::query()
            ->where('reorder_enabled', true)
            ->whereColumn('stock', '<=', 'min_stock')
            ->orderBy('id')
            ->chunkById(100, function ($products) use ($service, &$count): void {
                foreach ($products as $product) {
                    if ($service->syncProduct($product)) {
                        $count++;
                    }
                }
            });

        return redirect()
            ->route('purchase-orders.index', ['status' => 'draft'])
            ->with('status', __('pos.po_generated', ['count' => $count]));
    }

    public function markOrdered(PurchaseOrder $purchaseOrder): RedirectResponse
    {
        if (! $purchaseOrder->isDraft()) {
            return back()->withErrors(['status' => __('pos.po_invalid_status')]);
        }

        $purchaseOrder->update([
            'status' => PurchaseOrder::STATUS_ORDERED,
            'ordered_at' => now(),
        ]);

        return back()->with('status', __('pos.po_marked_ordered'));
    }

    public function receive(PurchaseOrder $purchaseOrder): RedirectResponse
    {
        if (! in_array($purchaseOrder->status, [PurchaseOrder::STATUS_DRAFT, PurchaseOrder::STATUS_ORDERED], true)) {
            return back()->withErrors(['status' => __('pos.po_invalid_status')]);
        }

        DB::transaction(function () use ($purchaseOrder): void {
            $purchaseOrder->load('items.product');

            foreach ($purchaseOrder->items as $item) {
                $product = Product::query()->lockForUpdate()->find($item->product_id);
                if (! $product) {
                    continue;
                }

                $qty = (int) $item->quantity;
                $product->increment('stock', $qty);

                $product->batches()->create([
                    'batch_no' => 'PO-'.$purchaseOrder->id.'-'.$product->id.'-'.now()->format('His'),
                    'expiry_date' => now()->addYear()->toDateString(),
                    'quantity' => $qty,
                    'received_at' => now(),
                ]);
            }

            $purchaseOrder->update([
                'status' => PurchaseOrder::STATUS_RECEIVED,
                'ordered_at' => $purchaseOrder->ordered_at ?? now(),
                'received_at' => now(),
            ]);
        });

        return redirect()
            ->route('purchase-orders.show', $purchaseOrder)
            ->with('status', __('pos.po_received'));
    }

    public function cancel(PurchaseOrder $purchaseOrder): RedirectResponse
    {
        if (! in_array($purchaseOrder->status, [PurchaseOrder::STATUS_DRAFT, PurchaseOrder::STATUS_ORDERED], true)) {
            return back()->withErrors(['status' => __('pos.po_invalid_status')]);
        }

        $purchaseOrder->update(['status' => PurchaseOrder::STATUS_CANCELLED]);

        return back()->with('status', __('pos.po_cancelled'));
    }
}
