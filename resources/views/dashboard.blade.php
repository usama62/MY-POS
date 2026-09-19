@extends('layouts.app')

@section('content')
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6"><h1 class="m-0">{{ __('pos.dashboard') }}</h1></div>
                <div class="col-sm-6">
                    <form method="GET" action="{{ route('dashboard') }}" class="form-inline float-sm-right">
                        <input type="date" name="from_date" value="{{ old('from_date', $fromDate) }}" class="form-control form-control-sm mr-2 @error('from_date') is-invalid @enderror">
                        <input type="date" name="to_date" value="{{ old('to_date', $toDate) }}" class="form-control form-control-sm mr-2 @error('to_date') is-invalid @enderror">
                        <button type="submit" class="btn btn-sm btn-primary">Filter</button>
                    </form>
                    @error('to_date')<div class="text-danger small text-right w-100 mt-1">{{ $message }}</div>@enderror
                    @error('from_date')<div class="text-danger small text-right w-100 mt-1">{{ $message }}</div>@enderror
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-3 col-6">
            <div class="small-box bg-info">
                <div class="inner">
                    <h3>{{ number_format($todaySales, 2) }}</h3>
                    <p>{{ __('pos.today_sales') }}</p>
                </div>
                <div class="icon"><i class="fas fa-dollar-sign"></i></div>
            </div>
        </div>
        <div class="col-lg-3 col-6">
            <div class="small-box bg-success">
                <div class="inner">
                    <h3>{{ $totalProducts }}</h3>
                    <p>{{ __('pos.products') }}</p>
                </div>
                <div class="icon"><i class="fas fa-boxes"></i></div>
            </div>
        </div>
        <div class="col-lg-3 col-6">
            <div class="small-box bg-warning">
                <div class="inner">
                    <h3>{{ $lowStock }}</h3>
                    <p>{{ __('pos.low_stock_items') }}</p>
                </div>
                <div class="icon"><i class="fas fa-exclamation-triangle"></i></div>
                <a href="{{ route('purchase-orders.index') }}" class="small-box-footer">{{ __('pos.purchase_orders') }} <i class="fas fa-arrow-circle-right"></i></a>
            </div>
        </div>
        <div class="col-lg-3 col-6">
            <div class="small-box bg-danger">
                <div class="inner">
                    <h3>{{ $draftPoCount ?? 0 }}</h3>
                    <p>{{ __('pos.po_drafts') }}</p>
                </div>
                <div class="icon"><i class="fas fa-file-alt"></i></div>
                <a href="{{ route('purchase-orders.index', ['status' => 'draft']) }}" class="small-box-footer">{{ __('pos.view') }} <i class="fas fa-arrow-circle-right"></i></a>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-7">
            <div class="card">
                <div class="card-header"><h3 class="card-title">Sales Trend (Daily)</h3></div>
                <div class="card-body">
                    <canvas id="salesTrendChart" height="120"></canvas>
                </div>
            </div>
        </div>
        <div class="col-md-5">
            <div class="card">
                <div class="card-header"><h3 class="card-title">{{ __('pos.recent_sales') }}</h3></div>
                <div class="card-body table-responsive p-0">
                    <table class="table table-hover text-nowrap">
                        <thead>
                            <tr>
                                <th>{{ __('pos.reference') }}</th>
                                <th>{{ __('pos.total') }}</th>
                                <th>{{ __('pos.date') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                        @forelse($recentSales as $sale)
                            <tr>
                                <td><a href="{{ route('sales.show', $sale) }}">{{ $sale->reference }}</a></td>
                                <td>{{ number_format($sale->total, 2) }}</td>
                                <td>{{ $sale->sold_at?->format('Y-m-d H:i') }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="3">{{ __('pos.no_data') }}</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
    const chartContext = document.getElementById('salesTrendChart');
    if (chartContext) {
        new Chart(chartContext, {
            type: 'line',
            data: {
                labels: @json($chartLabels),
                datasets: [{
                    label: 'Sales',
                    data: @json($chartData),
                    borderColor: '#007bff',
                    backgroundColor: 'rgba(0,123,255,0.15)',
                    fill: true,
                    tension: 0.35
                }]
            },
            options: { responsive: true, maintainAspectRatio: false }
        });
    }
</script>
@endsection
