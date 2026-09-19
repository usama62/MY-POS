<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sale_items', function (Blueprint $table) {
            if (! Schema::hasColumn('sale_items', 'line_subtotal')) {
                $table->decimal('line_subtotal', 12, 2)->default(0)->after('unit_price');
            }
            if (! Schema::hasColumn('sale_items', 'discount_amount')) {
                $table->decimal('discount_amount', 12, 2)->default(0)->after('line_subtotal');
            }
            if (! Schema::hasColumn('sale_items', 'discount_percent')) {
                $table->decimal('discount_percent', 5, 2)->default(0)->after('discount_amount');
            }
        });

        if (! Schema::hasTable('sale_payments')) {
            Schema::create('sale_payments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('sale_id')->constrained('sales')->cascadeOnDelete();
                $table->string('method', 30);
                $table->decimal('amount', 12, 2);
                $table->string('reference')->nullable();
                $table->timestamps();

                $table->index(['sale_id', 'method']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('sale_payments');

        Schema::table('sale_items', function (Blueprint $table) {
            foreach (['discount_percent', 'discount_amount', 'line_subtotal'] as $column) {
                if (Schema::hasColumn('sale_items', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
