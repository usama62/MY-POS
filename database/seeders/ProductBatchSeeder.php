<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\ProductBatch;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ProductBatchSeeder extends Seeder
{
    public function run(): void
    {
        Schema::disableForeignKeyConstraints();
        DB::table('sale_item_batches')->delete();
        ProductBatch::query()->delete();
        Schema::enableForeignKeyConstraints();

        $now = now();
        $buffer = [];

        Product::query()->orderBy('id')->chunkById(200, function ($products) use (&$buffer, $now): void {
            foreach ($products as $product) {
                $stock = max(0, (int) $product->stock);
                if ($stock === 0) {
                    continue;
                }

                // Split stock across 2–3 FEFO batches (nearest expiry first).
                $parts = $stock >= 3
                    ? [
                        (int) floor($stock * 0.25),
                        (int) floor($stock * 0.35),
                        0,
                    ]
                    : [$stock, 0, 0];
                $parts[2] = $stock - $parts[0] - $parts[1];

                $expiries = [
                    $now->copy()->addDays(20 + ($product->id % 40)),
                    $now->copy()->addMonths(4 + ($product->id % 5)),
                    $now->copy()->addMonths(10 + ($product->id % 8)),
                ];

                foreach ($parts as $i => $qty) {
                    if ($qty < 1) {
                        continue;
                    }

                    $buffer[] = [
                        'product_id' => $product->id,
                        'batch_no' => sprintf('B%s-%02d', $product->sku, $i + 1),
                        'expiry_date' => $expiries[$i]->toDateString(),
                        'quantity' => $qty,
                        'received_at' => $now->copy()->subDays(30 + $i * 15),
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }

                if (count($buffer) >= 500) {
                    ProductBatch::query()->insert($buffer);
                    $buffer = [];
                }
            }
        });

        if ($buffer !== []) {
            ProductBatch::query()->insert($buffer);
        }

        // Keep product.stock aligned with batch totals.
        Product::query()->orderBy('id')->chunkById(200, function ($products): void {
            foreach ($products as $product) {
                $product->syncStockFromBatches();
            }
        });

        $this->command?->info('Seeded FEFO batches for products.');
    }
}
