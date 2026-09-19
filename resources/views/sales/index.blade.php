@extends('layouts.app')

@section('content')
@php
    $sortOptions = [
        'sold_at' => __('pos.sort_date'),
        'total' => __('pos.sort_total'),
        'reference' => __('pos.sort_reference'),
        'customer' => __('pos.sort_customer'),
        'payment_method' => __('pos.sort_payment'),
    ];
    $periodOptions = [
        'all' => __('pos.filter_all'),
        'today' => __('pos.filter_today'),
        'yesterday' => __('pos.filter_yesterday'),
        'this_week' => __('pos.filter_this_week'),
        'last_week' => __('pos.filter_last_week'),
        'this_month' => __('pos.filter_this_month'),
        'last_month' => __('pos.filter_last_month'),
        'this_year' => __('pos.filter_this_year'),
        'custom' => __('pos.filter_custom'),
    ];
    $period = $period ?? 'all';
@endphp
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap">
        <h3 class="card-title mb-0">{{ __('pos.sales') }}</h3>
        <div class="no-print">
            <a href="{{ route('sales.create') }}" class="btn btn-primary btn-sm"><i class="fas fa-plus"></i> {{ __('pos.new_sale') }}</a>
        </div>
    </div>

    <div class="card-body border-bottom no-print">
        <form action="{{ route('sales.index') }}" method="GET" id="sales-filter-form">
            <div class="form-row align-items-end">
                <div class="form-group col-md-3 mb-2">
                    <label for="period">{{ __('pos.date_filter') }}</label>
                    <select name="period" id="period" class="form-control">
                        @foreach($periodOptions as $value => $label)
                            <option value="{{ $value }}" @selected($period === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group col-md-2 mb-2 custom-range-fields" style="{{ $period === 'custom' ? '' : 'display:none' }}">
                    <label for="from">{{ __('pos.from_date') }}</label>
                    <input type="date" name="from" id="from" class="form-control" value="{{ old('from', optional($from ?? null)->format('Y-m-d')) }}">
                </div>
                <div class="form-group col-md-2 mb-2 custom-range-fields" style="{{ $period === 'custom' ? '' : 'display:none' }}">
                    <label for="to">{{ __('pos.to_date') }}</label>
                    <input type="date" name="to" id="to" class="form-control" value="{{ old('to', optional($to ?? null)->format('Y-m-d')) }}">
                </div>
                <div class="form-group col-md-2 mb-2">
                    <label for="sort">{{ __('pos.sort_by') }}</label>
                    <select name="sort" id="sort" class="form-control">
                        @foreach($sortOptions as $value => $label)
                            <option value="{{ $value }}" @selected(($sort ?? 'sold_at') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group col-md-2 mb-2">
                    <label for="direction">{{ __('pos.sort_direction') }}</label>
                    <select name="direction" id="direction" class="form-control">
                        <option value="desc" @selected(($direction ?? 'desc') === 'desc')>{{ __('pos.sort_desc') }}</option>
                        <option value="asc" @selected(($direction ?? 'desc') === 'asc')>{{ __('pos.sort_asc') }}</option>
                    </select>
                </div>
                <div class="form-group col-md-3 mb-2">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-filter"></i> {{ __('pos.apply') }}</button>
                    <a href="{{ route('sales.index') }}" class="btn btn-outline-secondary">{{ __('pos.clear') }}</a>
                </div>
            </div>
            <div class="mt-1">
                <button type="button" class="btn btn-secondary btn-sm" onclick="window.print()"><i class="fas fa-print"></i> {{ __('pos.print') }}</button>
                <a
                    href="{{ route('sales.export', request()->only(['sort', 'direction', 'period', 'from', 'to'])) }}"
                    class="btn btn-success btn-sm"
                >
                    <i class="fas fa-file-csv"></i> {{ __('pos.download_csv') }}
                </a>
                @if($period !== 'all')
                    <small class="text-muted ml-2">
                        {{ __('pos.showing_filtered') }}
                        @if(!empty($from) && !empty($to))
                            ({{ $from->format('Y-m-d') }} → {{ $to->format('Y-m-d') }})
                        @endif
                        · {{ $sales->total() }}
                    </small>
                @endif
            </div>
        </form>
    </div>

    <div class="card-body table-responsive p-0">
        <table class="table table-hover text-nowrap">
            <thead>
            <tr>
                <th>{{ __('pos.reference') }}</th>
                <th>{{ __('pos.customer') }}</th>
                <th>{{ __('pos.total') }}</th>
                <th>{{ __('pos.payment_method') }}</th>
                <th>{{ __('pos.date') }}</th>
                <th class="no-print">{{ __('pos.actions') }}</th>
            </tr>
            </thead>
            <tbody>
            @forelse($sales as $sale)
                <tr>
                    <td><a href="{{ route('sales.show', $sale) }}">{{ $sale->reference }}</a></td>
                    <td>{{ $sale->customer?->name ?? __('pos.walk_in_customer') }}</td>
                    <td>{{ number_format($sale->total, 2) }}</td>
                    <td>{{ ucfirst(str_replace('_', ' ', $sale->payment_method)) }}</td>
                    <td>{{ $sale->sold_at?->format('Y-m-d H:i') }}</td>
                    <td class="no-print">
                        <a href="{{ route('sales.show', $sale) }}" class="btn btn-xs btn-info">{{ __('pos.view') }}</a>
                        <a href="{{ route('sales.show', $sale) }}" class="btn btn-xs btn-secondary" target="_blank">{{ __('pos.print') }}</a>
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="text-center text-muted py-4">{{ __('pos.no_data') }}</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-footer clearfix no-print">{{ $sales->links() }}</div>
</div>
@endsection

@section('scripts')
<script>
    (function () {
        const period = document.getElementById('period');
        const customFields = document.querySelectorAll('.custom-range-fields');
        if (!period) return;

        const toggleCustom = () => {
            const show = period.value === 'custom';
            customFields.forEach((el) => {
                el.style.display = show ? '' : 'none';
            });
        };

        period.addEventListener('change', toggleCustom);
        toggleCustom();
    })();
</script>
@endsection
