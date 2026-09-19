<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->string('batch_no', 100);
            $table->date('expiry_date');
            $table->unsignedInteger('quantity')->default(0);
            $table->timestamp('received_at')->nullable();
            $table->timestamps();

            $table->unique(['product_id', 'batch_no']);
            $table->index(['product_id', 'expiry_date']);
        });

        Schema::create('sale_item_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_item_id')->constrained('sale_items')->cascadeOnDelete();
            $table->foreignId('product_batch_id')->nullable()->constrained('product_batches')->nullOnDelete();
            $table->string('batch_no', 100);
            $table->date('expiry_date');
            $table->unsignedInteger('quantity');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sale_item_batches');
        Schema::dropIfExists('product_batches');
    }
};
