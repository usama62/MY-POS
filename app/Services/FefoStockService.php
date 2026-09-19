<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductBatch;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class FefoStockService
{
    /**
     * Suggest FEFO pick plan without locking stock.
     *
     * @return list<array{batch_id: int|null, batch_no: string, expiry_date: string, quantity: int, days_left: int}>
     */
    public function suggest(Product $product, int $quantity): array
    {
        if ($quantity < 1) {
            return [];
        }

        $batches = $this->availableBatches($product->id);
        $plan = $this->consumeFromBatches($batches, $quantity);

        $plannedQty = array_sum(array_column($plan, 'quantity'));
        if ($plannedQty < $quantity && $batches->isEmpty() && (int) $product->stock >= $quantity) {
            return [[
                'batch_id' => null,
                'batch_no' => 'UNBATCHED',
                'expiry_date' => now()->addYears(5)->toDateString(),
                'quantity' => $quantity,
                'days_left' => 365 * 5,
            ]];
        }

        return $plan;
    }

    /**
     * Lock batches and allocate FEFO quantities for a sale line.
     *
     * @return list<array{batch: ?ProductBatch, batch_no: string, expiry_date: string, quantity: int}>
     */
    public function allocate(Product $product, int $quantity): array
    {
        if ($quantity < 1) {
            throw ValidationException::withMessages([
                'items' => [__('pos.not_enough_stock', ['name' => $product->name])],
            ]);
        }

        $batches = ProductBatch::query()
            ->where('product_id', $product->id)
            ->where('quantity', '>', 0)
            ->orderBy('expiry_date')
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        $batchAvailable = (int) $batches->sum('quantity');

        if ($batchAvailable < $quantity && ! ($batches->isEmpty() && (int) $product->stock >= $quantity)) {
            throw ValidationException::withMessages([
                'items' => [__('pos.not_enough_stock', ['name' => $product->name])],
            ]);
        }

        $allocations = [];

        if ($batches->isEmpty()) {
            $allocations[] = [
                'batch' => null,
                'batch_no' => 'UNBATCHED',
                'expiry_date' => now()->addYears(5)->toDateString(),
                'quantity' => $quantity,
            ];
        } else {
            $remaining = $quantity;
            foreach ($batches as $batch) {
                if ($remaining <= 0) {
                    break;
                }

                $take = min($remaining, (int) $batch->quantity);
                if ($take < 1) {
                    continue;
                }

                $batch->decrement('quantity', $take);
                $allocations[] = [
                    'batch' => $batch->fresh(),
                    'batch_no' => $batch->batch_no,
                    'expiry_date' => $batch->expiry_date->toDateString(),
                    'quantity' => $take,
                ];
                $remaining -= $take;
            }

            if ($remaining > 0) {
                throw ValidationException::withMessages([
                    'items' => [__('pos.not_enough_stock', ['name' => $product->name])],
                ]);
            }
        }

        $product->decrement('stock', $quantity);

        return $allocations;
    }

    /**
     * @return Collection<int, ProductBatch>
     */
    private function availableBatches(int $productId): Collection
    {
        return ProductBatch::query()
            ->where('product_id', $productId)
            ->where('quantity', '>', 0)
            ->orderBy('expiry_date')
            ->orderBy('id')
            ->get();
    }

    /**
     * @param  Collection<int, ProductBatch>  $batches
     * @return list<array{batch_id: int|null, batch_no: string, expiry_date: string, quantity: int, days_left: int}>
     */
    private function consumeFromBatches(Collection $batches, int $quantity): array
    {
        $remaining = $quantity;
        $plan = [];

        foreach ($batches as $batch) {
            if ($remaining <= 0) {
                break;
            }

            $take = min($remaining, (int) $batch->quantity);
            if ($take < 1) {
                continue;
            }

            $plan[] = [
                'batch_id' => $batch->id,
                'batch_no' => $batch->batch_no,
                'expiry_date' => $batch->expiry_date->toDateString(),
                'quantity' => $take,
                'days_left' => $batch->daysUntilExpiry(),
            ];
            $remaining -= $take;
        }

        return $plan;
    }
}
