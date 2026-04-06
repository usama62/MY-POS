@extends('layouts.app')

@section('content')
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h3 class="card-title">{{ __('pos.sales') }}</h3>
        <a href="{{ route('sales.create') }}" class="btn btn-primary btn-sm"><i class="fas fa-plus"></i> {{ __('pos.new_sale') }}</a>
    </div>
    <div class="card-body table-responsive p-0">
        <table class="table table-hover text-nowrap">
            <thead>
            <tr>
                <th>{{ __('pos.reference') }}</th>
                <th>{{ __('pos.customer') }}</th>
                <th>{{ __('pos.total') }}</th>
                <th>{{ __('pos.date') }}</th>
                <th class="no-print">Actions</th>
            </tr>
            </thead>
            <tbody>
            @forelse($sales as $sale)
                <tr>
                    <td><a href="{{ route('sales.show', $sale) }}">{{ $sale->reference }}</a></td>
                    <td>{{ $sale->customer?->name ?? '-' }}</td>
                    <td>{{ number_format($sale->total, 2) }}</td>
                    <td>{{ $sale->sold_at?->format('Y-m-d H:i') }}</td>
                    <td class="no-print">
                        <a href="{{ route('sales.show', $sale) }}" class="btn btn-xs btn-info">View</a>
                        <a href="{{ route('sales.show', $sale) }}" class="btn btn-xs btn-secondary" target="_blank">Print</a>
                    </td>
                </tr>
            @empty
                <tr><td colspan="5">{{ __('pos.no_data') }}</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-footer clearfix">{{ $sales->links() }}</div>
</div>
@endsection
