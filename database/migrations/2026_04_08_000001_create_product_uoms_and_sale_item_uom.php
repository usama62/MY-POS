<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('product_uoms')) {
            Schema::create('product_uoms', function (Blueprint $table) {
                $table->id();
                $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
                $table->string('name', 50);
                $table->unsignedInteger('factor_to_base')->default(1);
                $table->decimal('price', 12, 2)->nullable();
                $table->boolean('is_base')->default(false);
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->timestamps();

                $table->unique(['product_id', 'name']);
                $table->index(['product_id', 'is_base']);
            });
        } else {
            Schema::table('product_uoms', function (Blueprint $table) {
                if (! Schema::hasColumn('product_uoms', 'factor_to_base')) {
                    $table->unsignedInteger('factor_to_base')->default(1)->after('name');
                }
                if (! Schema::hasColumn('product_uoms', 'price')) {
                    $table->decimal('price', 12, 2)->nullable()->after('factor_to_base');
                }
                if (! Schema::hasColumn('product_uoms', 'is_base')) {
                    $table->boolean('is_base')->default(false)->after('price');
                }
                if (! Schema::hasColumn('product_uoms', 'sort_order')) {
                    $table->unsignedSmallInteger('sort_order')->default(0)->after('is_base');
                }
            });
        }

        if (Schema::hasTable('sale_items')) {
            Schema::table('sale_items', function (Blueprint $table) {
                if (! Schema::hasColumn('sale_items', 'product_uom_id')) {
                    $table->foreignId('product_uom_id')->nullable()->after('product_id')->constrained('product_uoms')->nullOnDelete();
                }
                if (! Schema::hasColumn('sale_items', 'uom_name')) {
                    $table->string('uom_name', 50)->nullable()->after('product_uom_id');
                }
                if (! Schema::hasColumn('sale_items', 'uom_factor')) {
                    $table->unsignedInteger('uom_factor')->default(1)->after('uom_name');
                }
                if (! Schema::hasColumn('sale_items', 'base_quantity')) {
                    $table->unsignedInteger('base_quantity')->default(0)->after('quantity');
                }
            });

            DB::table('sale_items')
                ->where('base_quantity', 0)
                ->update([
                    'base_quantity' => DB::raw('quantity'),
                    'uom_name' => DB::raw("COALESCE(uom_name, 'Unit')"),
                    'uom_factor' => DB::raw('GREATEST(uom_factor, 1)'),
                ]);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('sale_items')) {
            Schema::table('sale_items', function (Blueprint $table) {
                if (Schema::hasColumn('sale_items', 'product_uom_id')) {
                    $table->dropConstrainedForeignId('product_uom_id');
                }
                foreach (['uom_name', 'uom_factor', 'base_quantity'] as $column) {
                    if (Schema::hasColumn('sale_items', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        Schema::dropIfExists('product_uoms');
    }
};
