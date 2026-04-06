<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ProductApiController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->integer('per_page', 15);

        $products = Product::query()
            ->when($request->filled('search'), function ($query) use ($request): void {
                $search = (string) $request->string('search');
                $query->where(function ($subQuery) use ($search): void {
                    $subQuery->where('name', 'like', "%{$search}%")
                        ->orWhere('sku', 'like', "%{$search}%");
                });
            })
            ->latest()
            ->paginate(min($perPage, 100));

        return response()->json($products);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'sku' => ['required', 'string', 'max:100', 'unique:products,sku'],
            'price' => ['required', 'numeric', 'min:0'],
            'stock' => ['required', 'integer', 'min:0'],
            'category' => ['nullable', 'string', 'max:255'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $validated['is_active'] = $request->boolean('is_active', true);
        $product = Product::create($validated);

        return response()->json($product, 201);
    }

    public function show(Product $product): JsonResponse
    {
        return response()->json($product);
    }

    public function update(Request $request, Product $product): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'sku' => ['required', 'string', 'max:100', Rule::unique('products', 'sku')->ignore($product->id)],
            'price' => ['required', 'numeric', 'min:0'],
            'stock' => ['required', 'integer', 'min:0'],
            'category' => ['nullable', 'string', 'max:255'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $validated['is_active'] = $request->boolean('is_active', true);
        $product->update($validated);

        return response()->json($product);
    }

    public function destroy(Product $product): JsonResponse
    {
        $product->delete();

        return response()->json([], 204);
    }

    public function import(Request $request): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt'],
        ]);

        $handle = fopen($request->file('file')->getRealPath(), 'r');

        if ($handle === false) {
            return response()->json(['message' => 'Invalid CSV file.'], 422);
        }

        $header = fgetcsv($handle);
        $imported = 0;

        DB::transaction(function () use (&$imported, $handle, $header): void {
            while (($row = fgetcsv($handle)) !== false) {
                $data = $this->normalizeCsvRow($header, $row);

                if (empty($data['name']) || empty($data['sku'])) {
                    continue;
                }

                Product::updateOrCreate(
                    ['sku' => $data['sku']],
                    [
                        'name' => $data['name'],
                        'price' => (float) ($data['price'] ?? 0),
                        'stock' => (int) ($data['stock'] ?? 0),
                        'category' => $data['category'] ?? null,
                        'is_active' => filter_var($data['is_active'] ?? true, FILTER_VALIDATE_BOOLEAN),
                    ]
                );

                $imported++;
            }
        });

        fclose($handle);

        return response()->json([
            'message' => 'Products imported successfully.',
            'imported' => $imported,
        ]);
    }

    private function normalizeCsvRow(array|false $header, array $row): array
    {
        if (is_array($header) && count($header) > 0) {
            $cleanHeader = array_map(static fn ($value) => strtolower(trim((string) $value)), $header);

            return array_combine($cleanHeader, $row) ?: [];
        }

        return [
            'name' => $row[0] ?? null,
            'sku' => $row[1] ?? null,
            'price' => $row[2] ?? 0,
            'stock' => $row[3] ?? 0,
            'category' => $row[4] ?? null,
            'is_active' => $row[5] ?? true,
        ];
    }
}
