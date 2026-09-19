<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductBatch;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) $request->query('q', ''));

        $products = Product::query()
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('sku', 'like', "%{$search}%")
                        ->orWhere('category', 'like', "%{$search}%");
                });
            })
            ->orderBy('name')
            ->paginate(50)
            ->withQueryString();

        return view('products.index', compact('products', 'search'));
    }

    public function create()
    {
        $categories = Product::categoryOptions();

        return view('products.create', compact('categories'));
    }

    public function store(Request $request)
    {
        $request->merge([
            'sku' => trim((string) $request->input('sku', '')) ?: null,
        ]);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'sku' => ['nullable', 'string', 'max:100', 'unique:products,sku'],
            'price' => ['required', 'numeric', 'min:0'],
            'stock' => ['required', 'integer', 'min:0'],
            'min_stock' => ['nullable', 'integer', 'min:0'],
            'max_stock' => ['nullable', 'integer', 'min:0'],
            'reorder_enabled' => ['nullable', 'boolean'],
            'category' => ['nullable', 'string', 'max:255'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        if (isset($validated['max_stock'], $validated['min_stock']) && $validated['max_stock'] < $validated['min_stock']) {
            $validated['max_stock'] = $validated['min_stock'];
        }

        $validated['sku'] = $validated['sku'] ?? Product::generateUniqueSku();
        $validated['is_active'] = $request->boolean('is_active');
        $validated['reorder_enabled'] = $request->boolean('reorder_enabled', true);
        $validated['min_stock'] = (int) ($validated['min_stock'] ?? 10);
        $validated['max_stock'] = (int) ($validated['max_stock'] ?? max(100, $validated['min_stock']));
        $product = Product::create($validated);

        if ((int) $product->stock > 0) {
            $product->batches()->create([
                'batch_no' => 'B'.$product->sku.'-01',
                'expiry_date' => now()->addYear()->toDateString(),
                'quantity' => (int) $product->stock,
                'received_at' => now(),
            ]);
        }

        $product->ensureDefaultUoms();

        return redirect()->route('products.index')->with('status', __('pos.product_created'));
    }

    public function show(Product $product)
    {
        $product->load(['batches' => fn ($q) => $q->orderBy('expiry_date')->orderBy('id')]);

        return view('products.show', compact('product'));
    }

    public function edit(Product $product)
    {
        $categories = Product::categoryOptions();
        $product->ensureDefaultUoms();
        $product->load([
            'batches' => fn ($q) => $q->orderBy('expiry_date')->orderBy('id'),
            'uoms',
        ]);

        return view('products.edit', compact('product', 'categories'));
    }

    public function update(Request $request, Product $product)
    {
        $request->merge([
            'sku' => trim((string) $request->input('sku', '')) ?: null,
        ]);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'sku' => ['nullable', 'string', 'max:100', Rule::unique('products', 'sku')->ignore($product->id)],
            'price' => ['required', 'numeric', 'min:0'],
            'stock' => ['required', 'integer', 'min:0'],
            'min_stock' => ['nullable', 'integer', 'min:0'],
            'max_stock' => ['nullable', 'integer', 'min:0'],
            'reorder_enabled' => ['nullable', 'boolean'],
            'category' => ['nullable', 'string', 'max:255'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        if (isset($validated['max_stock'], $validated['min_stock']) && $validated['max_stock'] < $validated['min_stock']) {
            $validated['max_stock'] = $validated['min_stock'];
        }

        $validated['sku'] = $validated['sku'] ?? $product->sku ?? Product::generateUniqueSku();
        $validated['is_active'] = $request->boolean('is_active');
        $validated['reorder_enabled'] = $request->boolean('reorder_enabled');
        $validated['min_stock'] = (int) ($validated['min_stock'] ?? $product->min_stock ?? 10);
        $validated['max_stock'] = (int) ($validated['max_stock'] ?? $product->max_stock ?? 100);
        $product->update($validated);

        app(\App\Services\AutoReorderService::class)->syncProduct($product->fresh());

        return redirect()->route('products.index')->with('status', __('pos.product_updated'));
    }

    public function destroy(Product $product)
    {
        $product->delete();

        return redirect()->route('products.index')->with('status', __('pos.product_deleted'));
    }

    public function storeBatch(Request $request, Product $product)
    {
        $validated = $request->validate([
            'batch_no' => ['required', 'string', 'max:100', Rule::unique('product_batches', 'batch_no')->where('product_id', $product->id)],
            'expiry_date' => ['required', 'date'],
            'quantity' => ['required', 'integer', 'min:1'],
        ]);

        $product->batches()->create([
            'batch_no' => $validated['batch_no'],
            'expiry_date' => $validated['expiry_date'],
            'quantity' => $validated['quantity'],
            'received_at' => now(),
        ]);

        $product->syncStockFromBatches();

        return redirect()
            ->route('products.edit', $product)
            ->with('status', __('pos.batch_added'));
    }

    public function storeUom(Request $request, Product $product)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:50', Rule::unique('product_uoms', 'name')->where('product_id', $product->id)],
            'factor_to_base' => ['required', 'integer', 'min:1'],
            'price' => ['nullable', 'numeric', 'min:0'],
            'is_base' => ['nullable', 'boolean'],
        ]);

        if ($request->boolean('is_base')) {
            $product->uoms()->update(['is_base' => false]);
        }

        $product->uoms()->create([
            'name' => $validated['name'],
            'factor_to_base' => $validated['factor_to_base'],
            'price' => $validated['price'] ?? round((float) $product->price * (int) $validated['factor_to_base'], 2),
            'is_base' => $request->boolean('is_base'),
            'sort_order' => ((int) $product->uoms()->max('sort_order')) + 1,
        ]);

        return redirect()
            ->route('products.edit', $product)
            ->with('status', __('pos.uom_added'));
    }

    public function import(Request $request)
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:10240'],
        ]);

        $handle = fopen($request->file('file')->getRealPath(), 'r');

        if ($handle === false) {
            return back()->withErrors(['file' => __('pos.invalid_csv')])->withInput();
        }

        $header = fgetcsv($handle);
        $imported = 0;

        DB::transaction(function () use (&$imported, $handle, $header): void {
            while (($row = fgetcsv($handle)) !== false) {
                $data = $this->normalizeCsvRow($header, $row);

                if (empty($data['name'])) {
                    continue;
                }

                $sku = trim((string) ($data['sku'] ?? ''));
                if ($sku === '') {
                    $sku = Product::generateUniqueSku();
                    Product::create([
                        'sku' => $sku,
                        'name' => $data['name'],
                        'price' => (float) ($data['price'] ?? 0),
                        'stock' => (int) ($data['stock'] ?? 0),
                        'category' => $data['category'] ?? null,
                        'is_active' => filter_var($data['is_active'] ?? true, FILTER_VALIDATE_BOOLEAN),
                    ]);
                } else {
                    Product::updateOrCreate(
                        ['sku' => $sku],
                        [
                            'name' => $data['name'],
                            'price' => (float) ($data['price'] ?? 0),
                            'stock' => (int) ($data['stock'] ?? 0),
                            'category' => $data['category'] ?? null,
                            'is_active' => filter_var($data['is_active'] ?? true, FILTER_VALIDATE_BOOLEAN),
                        ]
                    );
                }

                $imported++;
            }
        });

        fclose($handle);

        if ($imported === 0) {
            return back()->withErrors(['file' => __('pos.import_no_rows')])->withInput();
        }

        return redirect()->route('products.index')->with('status', __('pos.import_success', ['count' => $imported]));
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
