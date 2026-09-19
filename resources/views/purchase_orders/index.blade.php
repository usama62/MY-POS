@extends('layouts.app')

@section('content')
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap">
        <h3 class="card-title mb-0">{{ __('pos.purchase_orders') }}</h3>
        <form action="{{ route('purchase-orders.generate') }}" method="POST" class="no-print">
            @csrf
            <button class="btn btn-primary btn-sm"><i class="fas fa-magic"></i> {{ __('pos.generate_reorder_drafts') }}</button>
        </form>
    </div>
    <div class="card-body border-bottom no-print">
        <div class="btn-group">
            @foreach(['draft' => __('pos.po_draft'), 'ordered' => __('pos.po_ordered'), 'received' => __('pos.po_received_status'), 'cancelled' => __('pos.po_cancelled_status'), 'all' => __('pos.filter_all')] as $key => $label)
                <a href="{{ route('purchase-orders.index', ['status' => $key]) }}" class="btn btn-sm {{ ($status ?? 'draft') === $key ? 'btn-dark' : 'btn-outline-secondary' }}">{{ $label }}</a>
            @endforeach
        </div>
    </div>
    <div class="card-body table-responsive p-0">
        <table class="table table-hover text-nowrap">
            <thead>
                <tr>
                    <th>{{ __('pos.reference') }}</th>
                    <th>{{ __('pos.status') }}</th>
                    <th>{{ __('pos.items') }}</th>
                    <th>{{ __('pos.source') }}</th>
                    <th>{{ __('pos.date') }}</th>
                    <th>{{ __('pos.actions') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse($orders as $order)
                    <tr>
                        <td><a href="{{ route('purchase-orders.show', $order) }}">{{ $order->reference }}</a></td>
                        <td><span class="badge badge-{{ $order->status === 'draft' ? 'warning' : ($order->status === 'received' ? 'success' : 'secondary') }}">{{ $order->status }}</span></td>
                        <td>{{ $order->items_count }}</td>
                        <td>{{ $order->source }}</td>
                        <td>{{ $order->created_at?->format('Y-m-d H:i') }}</td>
                        <td><a href="{{ route('purchase-orders.show', $order) }}" class="btn btn-xs btn-info">{{ __('pos.view') }}</a></td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-muted py-4">{{ __('pos.no_data') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-footer">{{ $orders->links() }}</div>
</div>

@if($lowStockProducts->isNotEmpty())
<div class="card card-warning">
    <div class="card-header"><h3 class="card-title">{{ __('pos.low_stock_items') }}</h3></div>
    <div class="card-body table-responsive p-0">
        <table class="table table-sm mb-0">
            <thead>
                <tr>
                    <th>{{ __('pos.name') }}</th>
                    <th>{{ __('pos.stock') }}</th>
                    <th>{{ __('pos.min_stock') }}</th>
                    <th>{{ __('pos.max_stock') }}</th>
                    <th>{{ __('pos.reorder_qty') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach($lowStockProducts as $product)
                    <tr>
                        <td>{{ $product->name }}</td>
                        <td class="text-danger font-weight-bold">{{ $product->stock }}</td>
                        <td>{{ $product->min_stock }}</td>
                        <td>{{ $product->max_stock }}</td>
                        <td>{{ $product->reorderQuantity() }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endif
@endsection
