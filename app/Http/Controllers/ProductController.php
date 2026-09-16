<?php

namespace App\Http\Controllers;

use App\Models\Product;
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
            'category' => ['nullable', 'string', 'max:255'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $validated['sku'] = $validated['sku'] ?? Product::generateUniqueSku();
        $validated['is_active'] = $request->boolean('is_active');
        Product::create($validated);

        return redirect()->route('products.index')->with('status', __('pos.product_created'));
    }

    public function show(Product $product)
    {
        return view('products.show', compact('product'));
    }

    public function edit(Product $product)
    {
        $categories = Product::categoryOptions();

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
            'category' => ['nullable', 'string', 'max:255'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $validated['sku'] = $validated['sku'] ?? $product->sku ?? Product::generateUniqueSku();
        $validated['is_active'] = $request->boolean('is_active');
        $product->update($validated);

        return redirect()->route('products.index')->with('status', __('pos.product_updated'));
    }

    public function destroy(Product $product)
    {
        $product->delete();

        return redirect()->route('products.index')->with('status', __('pos.product_deleted'));
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
