@extends('layouts.app')

@section('content')
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap">
        <h3 class="card-title mb-0">{{ __('pos.purchase_order') }}: {{ $purchaseOrder->reference }}</h3>
        <div class="no-print">
            <a href="{{ route('purchase-orders.index') }}" class="btn btn-sm btn-secondary">{{ __('pos.back') }}</a>
            @if(in_array($purchaseOrder->status, ['draft', 'ordered'], true))
                @if($purchaseOrder->status === 'draft')
                    <form action="{{ route('purchase-orders.mark-ordered', $purchaseOrder) }}" method="POST" class="d-inline">@csrf<button class="btn btn-sm btn-primary">{{ __('pos.mark_ordered') }}</button></form>
                @endif
                <form action="{{ route('purchase-orders.receive', $purchaseOrder) }}" method="POST" class="d-inline" onsubmit="return confirm('{{ __('pos.confirm_receive_po') }}')">@csrf<button class="btn btn-sm btn-success">{{ __('pos.receive_stock') }}</button></form>
                <form action="{{ route('purchase-orders.cancel', $purchaseOrder) }}" method="POST" class="d-inline" onsubmit="return confirm('{{ __('pos.confirm_cancel_po') }}')">@csrf<button class="btn btn-sm btn-danger">{{ __('pos.cancel') }}</button></form>
            @endif
        </div>
    </div>
    <div class="card-body">
        <p><strong>{{ __('pos.status') }}:</strong> {{ $purchaseOrder->status }}</p>
        <p><strong>{{ __('pos.source') }}:</strong> {{ $purchaseOrder->source }}</p>
        @if($purchaseOrder->notes)
            <p><strong>{{ __('pos.notes') }}:</strong> {{ $purchaseOrder->notes }}</p>
        @endif

        <div class="table-responsive">
            <table class="table table-bordered">
                <thead>
                    <tr>
                        <th>{{ __('pos.product') }}</th>
                        <th>{{ __('pos.quantity') }}</th>
                        <th>{{ __('pos.price') }}</th>
                        <th>{{ __('pos.stock') }} @ {{ __('pos.trigger') }}</th>
                        <th>Min / Max</th>
                        <th>{{ __('pos.total') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @php $grand = 0; @endphp
                    @foreach($purchaseOrder->items as $item)
                        @php $line = (float) $item->unit_cost * (int) $item->quantity; $grand += $line; @endphp
                        <tr>
                            <td>{{ $item->product?->name ?? ('#'.$item->product_id) }}</td>
                            <td>{{ $item->quantity }}</td>
                            <td>{{ number_format($item->unit_cost, 2) }}</td>
                            <td>{{ $item->stock_at_trigger }}</td>
                            <td>{{ $item->min_stock_at_trigger }} / {{ $item->max_stock_at_trigger }}</td>
                            <td>{{ number_format($line, 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr>
                        <th colspan="5" class="text-right">{{ __('pos.total') }}</th>
                        <th>{{ number_format($grand, 2) }}</th>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>
@endsection
