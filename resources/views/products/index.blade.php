@extends('layouts.app')

@section('content')
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h3 class="card-title">{{ __('pos.products') }}</h3>
        <a href="{{ route('products.create') }}" class="btn btn-primary btn-sm"><i class="fas fa-plus"></i> {{ __('pos.add_product') }}</a>
    </div>
    <div class="card-body">
        <div class="mb-3 no-print">
            <h5>{{ __('pos.import_products') }}</h5>
            <form action="{{ route('products.import') }}" method="POST" enctype="multipart/form-data" class="form-inline">
                @csrf
                <input type="file" name="file" class="form-control form-control-sm mr-2" accept=".csv,.txt,text/csv,text/plain" required>
                <button class="btn btn-dark btn-sm">{{ __('pos.import') }}</button>
                <button type="button" onclick="window.print()" class="btn btn-secondary btn-sm ml-2"><i class="fas fa-print"></i> Print</button>
            </form>
            <small class="text-muted">CSV headers: name, sku, price, stock, category, is_active</small>
        </div>
        <div class="table-responsive p-0">
            <table class="table table-hover text-nowrap">
                <thead>
                    <tr>
                        <th>{{ __('pos.name') }}</th>
                        <th>SKU</th>
                        <th>{{ __('pos.price') }}</th>
                        <th>{{ __('pos.stock') }}</th>
                        <th class="no-print">{{ __('pos.actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($products as $product)
                    <tr>
                        <td>{{ $product->name }}</td>
                        <td>{{ $product->sku }}</td>
                        <td>{{ number_format($product->price, 2) }}</td>
                        <td>{{ $product->stock }}</td>
                        <td class="no-print">
                            <a href="{{ route('products.edit', $product) }}" class="btn btn-xs btn-info">{{ __('pos.edit') }}</a>
                            <form action="{{ route('products.destroy', $product) }}" method="POST" class="d-inline">
                                @csrf
                                @method('DELETE')
                                <button class="btn btn-xs btn-danger" onclick="return confirm('Delete this product?')">{{ __('pos.delete') }}</button>
                            </form>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    <div class="card-footer clearfix">{{ $products->links() }}</div>
</div>
@endsection
