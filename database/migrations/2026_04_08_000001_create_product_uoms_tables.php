<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('base_uom', 50)->default('Tablet')->after('category');
        });

        Schema::create('product_uoms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->string('name', 50);
            $table->unsignedInteger('factor_to_base')->default(1);
            $table->decimal('price', 12, 2)->nullable();
            $table->boolean('is_default')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['product_id', 'name']);
            $table->index(['product_id', 'factor_to_base']);
        });

        Schema::table('sale_items', function (Blueprint $table) {
            $table->foreignId('product_uom_id')->nullable()->after('product_id')->constrained('product_uoms')->nullOnDelete();
            $table->string('uom_name', 50)->nullable()->after('product_uom_id');
            $table->unsignedInteger('uom_factor')->default(1)->after('uom_name');
            $table->unsignedInteger('base_quantity')->default(0)->after('quantity');
        });
    }

    public function down(): void
    {
        Schema::table('sale_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('product_uom_id');
            $table->dropColumn(['uom_name', 'uom_factor', 'base_quantity']);
        });

        Schema::dropIfExists('product_uoms');

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('base_uom');
        });
    }
};
