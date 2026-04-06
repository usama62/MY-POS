@extends('layouts.app')

@push('styles')
<style>
    /* Thermal receipt: hidden on screen */
    .invoice-thermal {
        display: none !important;
    }

    @media print {
        body.print-thermal-receipt .wrapper > .main-header,
        body.print-thermal-receipt .main-sidebar,
        body.print-thermal-receipt .no-print,
        body.print-thermal-receipt .invoice-a4 {
            display: none !important;
        }

        body.print-thermal-receipt .invoice-thermal {
            display: block !important;
            width: 72mm;
            max-width: 72mm;
            margin: 0 auto;
            padding: 2mm 3mm;
            font-family: ui-monospace, 'Courier New', Courier, monospace;
            font-size: 11px;
            line-height: 1.35;
            color: #000;
            background: #fff;
        }

        body.print-thermal-receipt .thermal-line {
            border: 0;
            border-top: 1px dashed #000;
            margin: 6px 0;
        }

        body.print-thermal-receipt .thermal-row {
            display: flex;
            justify-content: space-between;
            gap: 4px;
            word-break: break-word;
        }

        body.print-thermal-receipt .thermal-item-name {
            flex: 1;
            min-width: 0;
        }

        body.print-a4-invoice .invoice-thermal {
            display: none !important;
        }

        @page {
            size: auto;
            margin: 8mm;
        }

        body.print-thermal-receipt {
            margin: 0;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
    }
</style>
@endpush

@section('content')
<div class="card no-print">
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap">
        <h3 class="card-title mb-0">{{ __('pos.sale_receipt') }}: {{ $sale->reference }}</h3>
        <div class="btn-group" role="group">
            <a href="{{ route('sales.index') }}" class="btn btn-sm btn-secondary"><i class="fas fa-arrow-left"></i> {{ __('pos.back') }}</a>
            <button type="button" onclick="document.body.classList.remove('print-thermal-receipt'); document.body.classList.add('print-a4-invoice'); window.print(); document.body.classList.remove('print-a4-invoice');" class="btn btn-sm btn-primary">
                <i class="fas fa-file-alt"></i> {{ __('pos.print_a4') }}
            </button>
            <button type="button" onclick="document.body.classList.add('print-thermal-receipt'); document.body.classList.remove('print-a4-invoice'); window.print(); document.body.classList.remove('print-thermal-receipt');" class="btn btn-sm btn-dark">
                <i class="fas fa-receipt"></i> {{ __('pos.print_thermal') }}
            </button>
        </div>
    </div>
</div>

{{-- A4 / full-width invoice --}}
<div class="invoice invoice-a4 p-3 mb-3 bg-white">
    <div class="row">
        <div class="col-12">
            <h4>
                @if(!empty($companyProfile?->logo_url))
                    <img src="{{ $companyProfile->logo_url }}" alt="logo" style="height:42px; margin-right:8px; display:inline-block; vertical-align:middle;">
                @endif
                <i class="fas fa-store"></i> {{ $companyProfile?->company_name ?: config('app.name', 'POS Pro') }}
                <small class="float-right">{{ $sale->sold_at?->format('Y-m-d H:i') }}</small>
            </h4>
            <small class="text-muted">
                {{ $companyProfile?->address ?: 'Business Address' }}
                @if(!empty($companyProfile?->phone)) | {{ __('pos.phone') }}: {{ $companyProfile->phone }} @endif
                @if(!empty($companyProfile?->email)) | {{ __('pos.email') }}: {{ $companyProfile->email }} @endif
            </small>
        </div>
    </div>
    <div class="row invoice-info">
        <div class="col-sm-6 invoice-col">
            <strong>{{ __('pos.customer') }}:</strong> {{ $sale->customer?->name ?? __('pos.walk_in_customer') }}<br>
            <strong>{{ __('pos.payment_method') }}:</strong> {{ ucfirst(str_replace('_', ' ', $sale->payment_method)) }}
        </div>
        <div class="col-sm-6 invoice-col text-right">
            <b>{{ __('pos.reference') }}:</b> {{ $sale->reference }}
        </div>
    </div>
    <div class="row mt-3">
        <div class="col-12 table-responsive">
            <table class="table table-striped">
                <thead>
                <tr>
                    <th>{{ __('pos.product') }}</th>
                    <th>{{ __('pos.quantity') }}</th>
                    <th>{{ __('pos.price') }}</th>
                    <th>{{ __('pos.total') }}</th>
                </tr>
                </thead>
                <tbody>
                @foreach($sale->items as $item)
                    <tr>
                        <td>{{ $item->product->name }}</td>
                        <td>{{ $item->quantity }}</td>
                        <td>{{ number_format($item->unit_price, 2) }}</td>
                        <td>{{ number_format($item->line_total, 2) }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
    <div class="row">
        <div class="col-6"></div>
        <div class="col-6">
            <table class="table">
                <tr><th>{{ __('pos.subtotal') }}:</th><td>{{ number_format($sale->subtotal, 2) }}</td></tr>
                <tr><th>{{ __('pos.discount') }}:</th><td>{{ number_format($sale->discount, 2) }}</td></tr>
                <tr><th>{{ __('pos.tax') }}:</th><td>{{ number_format($sale->tax, 2) }}</td></tr>
                <tr><th>{{ __('pos.total') }}:</th><td>{{ number_format($sale->total, 2) }}</td></tr>
                <tr><th>{{ __('pos.paid_amount') }}:</th><td>{{ number_format($sale->paid_amount, 2) }}</td></tr>
                <tr><th>{{ __('pos.change') }}:</th><td>{{ number_format($sale->change_amount, 2) }}</td></tr>
            </table>
        </div>
    </div>
    <div class="row mt-4">
        <div class="col-12 text-center">
            <small class="text-muted">{{ __('pos.thank_you') }}</small>
        </div>
    </div>
</div>

{{-- 80mm thermal receipt (print via “Print Thermal” only) --}}
<div class="invoice-thermal" dir="{{ in_array(app()->getLocale(), ['ar', 'ur'], true) ? 'rtl' : 'ltr' }}">
    @if(!empty($companyProfile?->logo_url))
        <div style="text-align:center;margin-bottom:4px;">
            <img src="{{ $companyProfile->logo_url }}" alt="" style="max-height:40px;max-width:100%;">
        </div>
    @endif
    <div style="text-align:center;font-weight:bold;font-size:12px;">
        {{ $companyProfile?->company_name ?: config('app.name', 'POS Pro') }}
    </div>
    @if(!empty($companyProfile?->address))
        <div style="text-align:center;font-size:10px;margin-top:2px;">{{ $companyProfile->address }}</div>
    @endif
    <div style="text-align:center;font-size:10px;">
        @if(!empty($companyProfile?->phone)){{ __('pos.phone') }}: {{ $companyProfile->phone }}@endif
        @if(!empty($companyProfile?->phone) && !empty($companyProfile?->email)) &middot; @endif
        @if(!empty($companyProfile?->email)){{ __('pos.email') }}: {{ $companyProfile->email }}@endif
    </div>
    <hr class="thermal-line">
    <div class="thermal-row"><span>{{ __('pos.reference') }}</span><span>{{ $sale->reference }}</span></div>
    <div class="thermal-row"><span>{{ __('pos.date') }}</span><span>{{ $sale->sold_at?->format('Y-m-d H:i') }}</span></div>
    <div class="thermal-row"><span>{{ __('pos.customer') }}</span><span>{{ $sale->customer?->name ?? __('pos.walk_in_customer') }}</span></div>
    <div class="thermal-row"><span>{{ __('pos.payment_method') }}</span><span>{{ ucfirst(str_replace('_', ' ', $sale->payment_method)) }}</span></div>
    <hr class="thermal-line">
    @foreach($sale->items as $item)
        <div style="margin-bottom:4px;">
            <div class="thermal-item-name">{{ $item->product->name }}</div>
            <div class="thermal-row">
                <span>{{ $item->quantity }} × {{ number_format($item->unit_price, 2) }}</span>
                <span>{{ number_format($item->line_total, 2) }}</span>
            </div>
        </div>
    @endforeach
    <hr class="thermal-line">
    <div class="thermal-row"><span>{{ __('pos.subtotal') }}</span><span>{{ number_format($sale->subtotal, 2) }}</span></div>
    <div class="thermal-row"><span>{{ __('pos.discount') }}</span><span>{{ number_format($sale->discount, 2) }}</span></div>
    <div class="thermal-row"><span>{{ __('pos.tax') }}</span><span>{{ number_format($sale->tax, 2) }}</span></div>
    <div class="thermal-row" style="font-weight:bold;"><span>{{ __('pos.total') }}</span><span>{{ number_format($sale->total, 2) }}</span></div>
    <div class="thermal-row"><span>{{ __('pos.paid_amount') }}</span><span>{{ number_format($sale->paid_amount, 2) }}</span></div>
    <div class="thermal-row"><span>{{ __('pos.change') }}</span><span>{{ number_format($sale->change_amount, 2) }}</span></div>
    <hr class="thermal-line">
    <div style="text-align:center;font-size:10px;">{{ __('pos.thank_you') }}</div>
</div>
@endsection
