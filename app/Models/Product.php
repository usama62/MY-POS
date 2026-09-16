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
        'category',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function saleItems(): HasMany
    {
        return $this->hasMany(SaleItem::class);
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
