<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    protected $fillable = [
        'name',
        'sku',
        'price',
        'stock',
        'min_stock',
        'max_stock',
        'reorder_enabled',
        'category',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'is_active' => 'boolean',
            'reorder_enabled' => 'boolean',
            'min_stock' => 'integer',
            'max_stock' => 'integer',
        ];
    }

    public function isLowStock(): bool
    {
        return (int) $this->stock <= (int) $this->min_stock;
    }

    public function reorderQuantity(): int
    {
        $min = (int) $this->min_stock;
        $max = max($min, (int) $this->max_stock);
        $stock = (int) $this->stock;

        return max(0, $max - $stock);
    }

    public function saleItems(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }

    public function batches(): HasMany
    {
        return $this->hasMany(ProductBatch::class);
    }

    public function uoms(): HasMany
    {
        return $this->hasMany(ProductUom::class)->orderBy('sort_order')->orderBy('factor_to_base');
    }

    public function baseUom(): ?ProductUom
    {
        return $this->uoms()->where('is_base', true)->first()
            ?? $this->uoms()->orderBy('factor_to_base')->first();
    }

    public function ensureDefaultUoms(): void
    {
        if ($this->uoms()->exists()) {
            return;
        }

        $basePrice = (float) $this->price;
        $defaults = $this->defaultUomBlueprint($basePrice);

        foreach ($defaults as $row) {
            $this->uoms()->create($row);
        }
    }

    /**
     * @return list<array{name: string, factor_to_base: int, price: float, is_base: bool, sort_order: int}>
     */
    public function defaultUomBlueprint(float $basePrice): array
    {
        $category = strtolower((string) $this->category);
        $liquidOrDevice = str_contains($category, 'syrup')
            || str_contains($category, 'device')
            || str_contains($category, 'first aid')
            || str_contains($category, 'surgical')
            || str_contains($category, 'hygiene')
            || str_contains($category, 'eye')
            || str_contains($category, 'ear');

        if ($liquidOrDevice) {
            return [[
                'name' => 'Unit',
                'factor_to_base' => 1,
                'price' => $basePrice,
                'is_base' => true,
                'sort_order' => 1,
            ]];
        }

        return [
            [
                'name' => 'Tablet',
                'factor_to_base' => 1,
                'price' => $basePrice,
                'is_base' => true,
                'sort_order' => 1,
            ],
            [
                'name' => 'Strip',
                'factor_to_base' => 10,
                'price' => round($basePrice * 10, 2),
                'is_base' => false,
                'sort_order' => 2,
            ],
            [
                'name' => 'Box',
                'factor_to_base' => 100,
                'price' => round($basePrice * 100, 2),
                'is_base' => false,
                'sort_order' => 3,
            ],
        ];
    }

    public function syncStockFromBatches(): void
    {
        $total = (int) $this->batches()->sum('quantity');
        $this->forceFill(['stock' => $total])->save();
    }

    /**
     * Batches ordered for FEFO (soonest expiry first).
     */
    public function fefoBatches()
    {
        return $this->batches()
            ->where('quantity', '>', 0)
            ->orderBy('expiry_date')
            ->orderBy('id');
    }

    /**
     * @return list<string>
     */
    public static function categoryOptions(): array
    {
        $defaults = [
            'Analgesics',
            'Antibiotics',
            'Antihistamines',
            'Antifungal',
            'Antiviral',
            'Antiparasitic',
            'Cardiac',
            'Cough & Cold',
            'Dermatology',
            'Devices',
            'Diabetes',
            'Diagnostics',
            'Ear Care',
            'Eye Care',
            'First Aid',
            'Gastrointestinal',
            'Hormonal',
            'Hygiene',
            'Neurology',
            'Psychiatric',
            'Respiratory',
            'Supplements',
            'Surgical',
            'Syrups',
            'Vitamins',
        ];

        $fromDb = static::query()
            ->whereNotNull('category')
            ->where('category', '!=', '')
            ->distinct()
            ->orderBy('category')
            ->pluck('category')
            ->all();

        $categories = array_values(array_unique(array_merge($defaults, $fromDb)));
        sort($categories, SORT_NATURAL | SORT_FLAG_CASE);

        return $categories;
    }

    public static function generateUniqueSku(string $prefix = 'MED-'): string
    {
        $lastSku = static::query()
            ->where('sku', 'like', $prefix.'%')
            ->orderByDesc('id')
            ->value('sku');

        $number = 1;
        if (is_string($lastSku) && preg_match('/(\d+)$/', $lastSku, $matches)) {
            $number = (int) $matches[1] + 1;
        }

        do {
            $sku = sprintf('%s%05d', $prefix, $number);
            $number++;
        } while (static::query()->where('sku', $sku)->exists());

        return $sku;
    }
}
