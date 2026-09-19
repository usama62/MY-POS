<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductUom extends Model
{
    protected $fillable = [
        'product_id',
        'name',
        'factor_to_base',
        'price',
        'is_base',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'factor_to_base' => 'integer',
            'price' => 'decimal:2',
            'is_base' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function unitPrice(): float
    {
        if ($this->price !== null) {
            return (float) $this->price;
        }

        $basePrice = (float) ($this->product?->price ?? 0);

        return round($basePrice * max(1, (int) $this->factor_to_base), 2);
    }

    public function toBaseQuantity(int $uomQuantity): int
    {
        return max(0, $uomQuantity) * max(1, (int) $this->factor_to_base);
    }

    public function maxSellableFromStock(int $baseStock): int
    {
        $factor = max(1, (int) $this->factor_to_base);

        return intdiv(max(0, $baseStock), $factor);
    }
}
