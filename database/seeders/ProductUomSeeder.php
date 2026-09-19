<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\ProductUom;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

class ProductUomSeeder extends Seeder
{
    public function run(): void
    {
        Schema::disableForeignKeyConstraints();
        ProductUom::query()->delete();
        Schema::enableForeignKeyConstraints();

        $now = now();
        $buffer = [];

        Product::query()->orderBy('id')->chunkById(200, function ($products) use (&$buffer, $now): void {
            foreach ($products as $product) {
                foreach ($product->defaultUomBlueprint((float) $product->price) as $row) {
                    $buffer[] = [
                        'product_id' => $product->id,
                        'name' => $row['name'],
                        'factor_to_base' => $row['factor_to_base'],
                        'price' => $row['price'],
                        'is_base' => $row['is_base'],
                        'sort_order' => $row['sort_order'],
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }

                if (count($buffer) >= 500) {
                    ProductUom::query()->insert($buffer);
                    $buffer = [];
                }
            }
        });

        if ($buffer !== []) {
            ProductUom::query()->insert($buffer);
        }

        $this->command?->info('Seeded product UOMs (Tablet/Strip/Box or Unit).');
    }
}
