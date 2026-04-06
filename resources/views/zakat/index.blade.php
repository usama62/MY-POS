@extends('layouts.app')

@section('content')
<div class="card card-primary">
    <div class="card-header">
        <h3 class="card-title">{{ __('pos.zakat_calculator') }}</h3>
    </div>
    <form action="{{ route('zakat.calculate') }}" method="POST">
        @csrf
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label>{{ __('pos.cash') }} <span class="text-danger">*</span></label>
                        <input type="number" step="0.01" min="0" name="cash" value="{{ old('cash', ($inputs ?? [])['cash'] ?? 0) }}" class="form-control @error('cash') is-invalid @enderror" required inputmode="decimal">
                        @error('cash')<span class="invalid-feedback d-block">{{ $message }}</span>@enderror
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label>{{ __('pos.inventory_value') }} <span class="text-danger">*</span></label>
                        <input type="number" step="0.01" min="0" name="inventory_value" value="{{ old('inventory_value', ($inputs ?? [])['inventory_value'] ?? 0) }}" class="form-control @error('inventory_value') is-invalid @enderror" required inputmode="decimal">
                        @error('inventory_value')<span class="invalid-feedback d-block">{{ $message }}</span>@enderror
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label>{{ __('pos.receivables') }} <span class="text-danger">*</span></label>
                        <input type="number" step="0.01" min="0" name="receivables" value="{{ old('receivables', ($inputs ?? [])['receivables'] ?? 0) }}" class="form-control @error('receivables') is-invalid @enderror" required inputmode="decimal">
                        @error('receivables')<span class="invalid-feedback d-block">{{ $message }}</span>@enderror
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label>{{ __('pos.liabilities') }} <span class="text-danger">*</span></label>
                        <input type="number" step="0.01" min="0" name="liabilities" value="{{ old('liabilities', ($inputs ?? [])['liabilities'] ?? 0) }}" class="form-control @error('liabilities') is-invalid @enderror" required inputmode="decimal">
                        @error('liabilities')<span class="invalid-feedback d-block">{{ $message }}</span>@enderror
                    </div>
                </div>
            </div>
        </div>
        <div class="card-footer">
            <button type="submit" class="btn btn-primary">{{ __('pos.calculate') }}</button>
        </div>
    </form>
</div>

@if(!is_null($zakatAmount))
    <div class="card card-outline card-success mt-3">
        <div class="card-body">
            <p><strong>{{ __('pos.net_zakatable_amount') }}:</strong> {{ number_format($netAmount, 2) }}</p>
            <p class="text-lg mb-0"><strong>{{ __('pos.zakat_due') }} (2.5%):</strong> {{ number_format($zakatAmount, 2) }}</p>
        </div>
    </div>
@endif
@endsection
