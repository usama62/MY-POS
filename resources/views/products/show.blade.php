@extends('layouts.app')

@section('content')
    <div class="card">
        <div class="card-header d-flex justify-content-between">
            <h3 class="card-title mb-0">{{ $product->name }}</h3>
            <a href="{{ route('products.edit', $product) }}" class="btn btn-sm btn-warning">{{ __('pos.edit') }}</a>
        </div>
        <div class="card-body">
            <p><strong>SKU:</strong> {{ $product->sku }}</p>
            <p><strong>{{ __('pos.price') }}:</strong> {{ number_format($product->price, 2) }}</p>
            <p><strong>{{ __('pos.stock') }}:</strong> {{ $product->stock }}</p>
            <p><strong>{{ __('pos.category') }}:</strong> {{ $product->category ?: '-' }}</p>

            <h5 class="mt-4">{{ __('pos.batches_fefo') }}</h5>
            <table class="table table-sm table-bordered">
                <thead>
                    <tr>
                        <th>{{ __('pos.batch_no') }}</th>
                        <th>{{ __('pos.expiry_date') }}</th>
                        <th>{{ __('pos.quantity') }}</th>
                        <th>{{ __('pos.days_left') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($product->batches as $batch)
                        <tr class="{{ $batch->daysUntilExpiry() < 0 ? 'table-danger' : ($batch->daysUntilExpiry() <= 30 ? 'table-warning' : '') }}">
                            <td>{{ $batch->batch_no }}</td>
                            <td>{{ $batch->expiry_date->format('Y-m-d') }}</td>
                            <td>{{ $batch->quantity }}</td>
                            <td>{{ $batch->daysUntilExpiry() }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-muted text-center">{{ __('pos.no_batches') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
