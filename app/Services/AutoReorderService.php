<?php

namespace App\Services;

use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class AutoReorderService
{
    /**
     * If product stock is at/below min threshold, ensure a draft PO line exists
     * for the quantity needed to reach max_stock.
     */
    public function syncProduct(Product $product): ?PurchaseOrder
    {
        $product->refresh();

        if (! $product->reorder_enabled) {
            return null;
        }

        $min = (int) $product->min_stock;
        $max = max($min, (int) $product->max_stock);
        $stock = (int) $product->stock;

        if ($stock > $min) {
            return null;
        }

        $needed = max(0, $max - $stock);
        if ($needed < 1) {
            $needed = max(1, $min); // at least something to reorder
        }

        return DB::transaction(function () use ($product, $needed, $stock, $min, $max) {
            $draft = PurchaseOrder::query()
                ->where('status', PurchaseOrder::STATUS_DRAFT)
                ->where('source', 'auto')
                ->lockForUpdate()
                ->latest('id')
                ->first();

            if (! $draft) {
                $draft = PurchaseOrder::query()->create([
                    'reference' => PurchaseOrder::generateReference(),
                    'status' => PurchaseOrder::STATUS_DRAFT,
                    'source' => 'auto',
                    'notes' => 'Auto-generated from low-stock thresholds (FEFO inventory).',
                    'created_by' => Auth::id(),
                ]);
            }

            /** @var PurchaseOrderItem|null $item */
            $item = $draft->items()->where('product_id', $product->id)->lockForUpdate()->first();

            if ($item) {
                // Keep the larger suggested qty if stock dropped further.
                $item->update([
                    'quantity' => max((int) $item->quantity, $needed),
                    'unit_cost' => (float) $product->price,
                    'stock_at_trigger' => $stock,
                    'min_stock_at_trigger' => $min,
                    'max_stock_at_trigger' => $max,
                ]);
            } else {
                $draft->items()->create([
                    'product_id' => $product->id,
                    'quantity' => $needed,
                    'unit_cost' => (float) $product->price,
                    'stock_at_trigger' => $stock,
                    'min_stock_at_trigger' => $min,
                    'max_stock_at_trigger' => $max,
                ]);
            }

            return $draft->fresh('items');
        });
    }

    /**
     * @param  iterable<int, Product|int>  $products
     */
    public function syncMany(iterable $products): void
    {
        foreach ($products as $product) {
            if (! $product instanceof Product) {
                $product = Product::query()->find($product);
            }
            if ($product) {
                $this->syncProduct($product);
            }
        }
    }
}
