<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SaleItem extends Model
{
    protected $fillable = [
        'sale_id',
        'product_id',
        'product_uom_id',
        'uom_name',
        'uom_factor',
        'quantity',
        'base_quantity',
        'unit_price',
        'line_subtotal',
        'discount_amount',
        'discount_percent',
        'line_total',
    ];

    protected function casts(): array
    {
        return [
            'uom_factor' => 'integer',
            'quantity' => 'integer',
            'base_quantity' => 'integer',
            'unit_price' => 'decimal:2',
            'line_subtotal' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'discount_percent' => 'decimal:2',
            'line_total' => 'decimal:2',
        ];
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function uom(): BelongsTo
    {
        return $this->belongsTo(ProductUom::class, 'product_uom_id');
    }

    public function batchAllocations(): HasMany
    {
        return $this->hasMany(SaleItemBatch::class);
    }
}
