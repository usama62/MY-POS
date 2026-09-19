<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (! Schema::hasColumn('products', 'min_stock')) {
                $table->unsignedInteger('min_stock')->default(10)->after('stock');
            }
            if (! Schema::hasColumn('products', 'max_stock')) {
                $table->unsignedInteger('max_stock')->default(100)->after('min_stock');
            }
            if (! Schema::hasColumn('products', 'reorder_enabled')) {
                $table->boolean('reorder_enabled')->default(true)->after('max_stock');
            }
        });

        // Sensible defaults for existing catalogue.
        DB::table('products')->update([
            'min_stock' => DB::raw('GREATEST(5, LEAST(20, FLOOR(stock * 0.15)))'),
            'max_stock' => DB::raw('GREATEST(min_stock + 10, LEAST(500, stock + GREATEST(20, FLOOR(stock * 0.5))))'),
            'reorder_enabled' => 1,
        ]);

        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();
            $table->string('status', 30)->default('draft'); // draft, ordered, received, cancelled
            $table->string('source', 30)->default('auto'); // auto, manual
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('ordered_at')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->timestamps();

            $table->index('status');
        });

        Schema::create('purchase_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_order_id')->constrained('purchase_orders')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->unsignedInteger('quantity');
            $table->decimal('unit_cost', 12, 2)->default(0);
            $table->unsignedInteger('stock_at_trigger')->default(0);
            $table->unsignedInteger('min_stock_at_trigger')->default(0);
            $table->unsignedInteger('max_stock_at_trigger')->default(0);
            $table->timestamps();

            $table->unique(['purchase_order_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_order_items');
        Schema::dropIfExists('purchase_orders');

        Schema::table('products', function (Blueprint $table) {
            foreach (['reorder_enabled', 'max_stock', 'min_stock'] as $column) {
                if (Schema::hasColumn('products', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
