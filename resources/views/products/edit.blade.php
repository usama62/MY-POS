@extends('layouts.app')

@section('content')
    <div class="card card-warning">
        <div class="card-header"><h3 class="card-title">{{ __('pos.edit_product') }}</h3></div>
        <div class="card-body">
        <form action="{{ route('products.update', $product) }}" method="POST">
            @method('PUT')
            @include('products._form')
        </form>
        </div>
    </div>

    <div class="card card-info">
        <div class="card-header"><h3 class="card-title">{{ __('pos.batches_fefo') }}</h3></div>
        <div class="card-body">
            <p class="text-muted">{{ __('pos.fefo_batches_help') }}</p>
            <div class="table-responsive mb-3">
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

            <form action="{{ route('products.batches.store', $product) }}" method="POST" class="form-row align-items-end">
                @csrf
                <div class="form-group col-md-3">
                    <label>{{ __('pos.batch_no') }}</label>
                    <input type="text" name="batch_no" class="form-control" required maxlength="100" value="{{ old('batch_no') }}">
                </div>
                <div class="form-group col-md-3">
                    <label>{{ __('pos.expiry_date') }}</label>
                    <input type="date" name="expiry_date" class="form-control" required value="{{ old('expiry_date') }}">
                </div>
                <div class="form-group col-md-2">
                    <label>{{ __('pos.quantity') }}</label>
                    <input type="number" name="quantity" class="form-control" min="1" required value="{{ old('quantity', 1) }}">
                </div>
                <div class="form-group col-md-2">
                    <button class="btn btn-info">{{ __('pos.add_batch') }}</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card card-secondary">
        <div class="card-header"><h3 class="card-title">{{ __('pos.uoms') }}</h3></div>
        <div class="card-body">
            <p class="text-muted">{{ __('pos.uom_help') }}</p>
            <div class="table-responsive mb-3">
                <table class="table table-sm table-bordered">
                    <thead>
                        <tr>
                            <th>{{ __('pos.uom') }}</th>
                            <th>{{ __('pos.factor_to_base') }}</th>
                            <th>{{ __('pos.price') }}</th>
                            <th>{{ __('pos.base_unit') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($product->uoms as $uom)
                            <tr>
                                <td>{{ $uom->name }}</td>
                                <td>{{ $uom->factor_to_base }}</td>
                                <td>{{ number_format($uom->unitPrice(), 2) }}</td>
                                <td>{{ $uom->is_base ? __('pos.yes') : __('pos.no') }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="text-muted text-center">{{ __('pos.no_uoms') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <form action="{{ route('products.uoms.store', $product) }}" method="POST" class="form-row align-items-end">
                @csrf
                <div class="form-group col-md-3">
                    <label>{{ __('pos.uom') }}</label>
                    <input type="text" name="name" class="form-control" required maxlength="50" placeholder="Strip" value="{{ old('name') }}">
                </div>
                <div class="form-group col-md-2">
                    <label>{{ __('pos.factor_to_base') }}</label>
                    <input type="number" name="factor_to_base" class="form-control" min="1" required value="{{ old('factor_to_base', 10) }}">
                </div>
                <div class="form-group col-md-2">
                    <label>{{ __('pos.price') }}</label>
                    <input type="number" step="0.01" min="0" name="price" class="form-control" value="{{ old('price') }}" placeholder="{{ __('pos.auto_price') }}">
                </div>
                <div class="form-group col-md-2">
                    <div class="form-check mt-4">
                        <input class="form-check-input" type="checkbox" name="is_base" value="1" id="is_base">
                        <label class="form-check-label" for="is_base">{{ __('pos.base_unit') }}</label>
                    </div>
                </div>
                <div class="form-group col-md-2">
                    <button class="btn btn-secondary">{{ __('pos.add_uom') }}</button>
                </div>
            </form>
        </div>
    </div>
@endsection
