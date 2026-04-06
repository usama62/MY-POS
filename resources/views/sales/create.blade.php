@extends('layouts.app')

@section('content')
<div class="card card-primary">
    <div class="card-header"><h3 class="card-title">{{ __('pos.new_sale') }}</h3></div>
    <form action="{{ route('sales.store') }}" method="POST" id="sale-form" data-msg-items="{{ e(__('pos.validation_items_required')) }}">
        @csrf
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label>{{ __('pos.customer') }}</label>
                        <select name="customer_id" class="form-control @error('customer_id') is-invalid @enderror">
                            <option value="">{{ __('pos.walk_in_customer') }}</option>
                            @foreach($customers as $customer)
                                <option value="{{ $customer->id }}" @selected(old('customer_id') == $customer->id)>{{ $customer->name }}</option>
                            @endforeach
                        </select>
                        @error('customer_id')<span class="invalid-feedback d-block">{{ $message }}</span>@enderror
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label>{{ __('pos.payment_method') }} <span class="text-danger">*</span></label>
                        <select name="payment_method" class="form-control @error('payment_method') is-invalid @enderror" required>
                            <option value="cash" @selected(old('payment_method', 'cash') === 'cash')>Cash</option>
                            <option value="card" @selected(old('payment_method') === 'card')>Card</option>
                            <option value="bank_transfer" @selected(old('payment_method') === 'bank_transfer')>Bank Transfer</option>
                        </select>
                        @error('payment_method')<span class="invalid-feedback d-block">{{ $message }}</span>@enderror
                    </div>
                </div>
            </div>

            <h5 class="mb-2">{{ __('pos.items') }} <span class="text-danger">*</span></h5>
            @error('items')
                <div class="alert alert-danger py-2">{{ $message }}</div>
            @enderror

            <div id="cart-rows">
                <div class="row cart-row" data-row-index="0">
                    <div class="col-md-6">
                        <div class="form-group">
                            <select name="items[0][product_id]" class="form-control product-select @error('items.*.product_id') is-invalid @enderror">
                                <option value="">{{ __('pos.select_product') }}</option>
                                @foreach($products as $product)
                                    <option value="{{ $product->id }}" data-price="{{ (float) $product->price }}" @selected(old('items.0.product_id') == $product->id)>{{ $product->name }} ({{ $product->stock }})</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="form-group">
                            <input type="number" min="1" step="1" name="items[0][quantity]" value="{{ old('items.0.quantity', 1) }}" class="form-control item-qty @error('items.*.quantity') is-invalid @enderror" placeholder="{{ __('pos.quantity') }}">
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="form-group">
                            <input type="text" class="form-control item-line-total" value="0.00" readonly tabindex="-1" aria-hidden="true">
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="form-group">
                            <button type="button" class="btn btn-danger btn-block remove-row">{{ __('pos.delete') }}</button>
                        </div>
                    </div>
                </div>
            </div>
            <div class="mb-3">
                <button type="button" id="add-row" class="btn btn-sm btn-secondary"><i class="fas fa-plus"></i> {{ __('pos.add_item') }}</button>
            </div>

            <div class="row">
                <div class="col-md-4">
                    <div class="form-group">
                        <label>{{ __('pos.discount') }}</label>
                        <input type="number" step="0.01" min="0" name="discount" value="{{ old('discount', '0') }}" class="form-control @error('discount') is-invalid @enderror" id="discount-input">
                        @error('discount')<span class="invalid-feedback d-block">{{ $message }}</span>@enderror
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-group">
                        <label>{{ __('pos.tax') }}</label>
                        <input type="number" step="0.01" min="0" name="tax" value="{{ old('tax', '0') }}" class="form-control @error('tax') is-invalid @enderror" id="tax-input">
                        @error('tax')<span class="invalid-feedback d-block">{{ $message }}</span>@enderror
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-group">
                        <label>{{ __('pos.paid_amount') }} <span class="text-danger">*</span></label>
                        <input type="number" step="0.01" min="0" name="paid_amount" value="{{ old('paid_amount', '0') }}" class="form-control @error('paid_amount') is-invalid @enderror" id="paid-input" required inputmode="decimal">
                        @error('paid_amount')<span class="invalid-feedback d-block">{{ $message }}</span>@enderror
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-3"><div class="small-box bg-info"><div class="inner"><h4 id="preview-subtotal">0.00</h4><p>{{ __('pos.subtotal') }}</p></div></div></div>
                <div class="col-md-3"><div class="small-box bg-primary"><div class="inner"><h4 id="preview-total">0.00</h4><p>{{ __('pos.total') }}</p></div></div></div>
                <div class="col-md-3"><div class="small-box bg-success"><div class="inner"><h4 id="preview-paid">0.00</h4><p>{{ __('pos.paid_amount') }}</p></div></div></div>
                <div class="col-md-3"><div class="small-box bg-warning"><div class="inner"><h4 id="preview-change">0.00</h4><p>{{ __('pos.change') }}</p></div></div></div>
            </div>
        </div>
        <div class="card-footer">
            <button type="submit" class="btn btn-primary"><i class="fas fa-check-circle"></i> {{ __('pos.complete_sale') }}</button>
        </div>
    </form>
</div>
@endsection

@section('scripts')
<script>
    (function () {
        const form = document.getElementById('sale-form');
        const cartRows = document.getElementById('cart-rows');
        const addRowBtn = document.getElementById('add-row');
        const discountInput = document.getElementById('discount-input');
        const taxInput = document.getElementById('tax-input');
        const paidInput = document.getElementById('paid-input');
        let rowIndex = 1;

        const formatMoney = (value) => Number(value || 0).toFixed(2);

        const hasAtLeastOneLine = () => {
            let ok = false;
            cartRows.querySelectorAll('.cart-row').forEach((row) => {
                const sel = row.querySelector('.product-select');
                const qty = parseFloat(row.querySelector('.item-qty')?.value || '0');
                if (sel && sel.value && qty >= 1) {
                    ok = true;
                }
            });
            return ok;
        };

        form.addEventListener('submit', function (e) {
            if (!hasAtLeastOneLine()) {
                e.preventDefault();
                alert(form.dataset.msgItems || 'Add at least one product.');
                return false;
            }
        });

        const recalc = () => {
            let subtotal = 0;

            cartRows.querySelectorAll('.cart-row').forEach((row) => {
                const productSelect = row.querySelector('.product-select');
                const qtyInput = row.querySelector('.item-qty');
                const lineTotalInput = row.querySelector('.item-line-total');
                const selected = productSelect.options[productSelect.selectedIndex];
                const price = parseFloat(selected?.dataset?.price || '0');
                const qty = parseFloat(qtyInput.value || '0');
                const lineTotal = price * qty;
                subtotal += lineTotal;
                lineTotalInput.value = formatMoney(lineTotal);
            });

            const discount = parseFloat(discountInput.value || '0');
            const tax = parseFloat(taxInput.value || '0');
            const paid = parseFloat(paidInput.value || '0');
            const total = Math.max(0, subtotal - discount + tax);
            const change = paid - total;

            document.getElementById('preview-subtotal').textContent = formatMoney(subtotal);
            document.getElementById('preview-total').textContent = formatMoney(total);
            document.getElementById('preview-paid').textContent = formatMoney(paid);
            document.getElementById('preview-change').textContent = formatMoney(change);
        };

        const bindRowEvents = (row) => {
            row.querySelector('.product-select').addEventListener('change', recalc);
            row.querySelector('.item-qty').addEventListener('input', recalc);
            row.querySelector('.remove-row').addEventListener('click', () => {
                if (cartRows.querySelectorAll('.cart-row').length > 1) {
                    row.remove();
                    recalc();
                }
            });
        };

        addRowBtn.addEventListener('click', () => {
            const row = cartRows.querySelector('.cart-row').cloneNode(true);
            row.dataset.rowIndex = String(rowIndex);
            row.querySelectorAll('select, input').forEach((el) => {
                if (el.name?.includes('[product_id]')) {
                    el.name = `items[${rowIndex}][product_id]`;
                    el.value = '';
                    el.classList.remove('is-invalid');
                } else if (el.name?.includes('[quantity]')) {
                    el.name = `items[${rowIndex}][quantity]`;
                    el.value = '1';
                } else if (el.classList.contains('item-line-total')) {
                    el.value = '0.00';
                }
            });
            cartRows.appendChild(row);
            bindRowEvents(row);
            rowIndex += 1;
            recalc();
        });

        [discountInput, taxInput, paidInput].forEach((el) => el.addEventListener('input', recalc));
        cartRows.querySelectorAll('.cart-row').forEach(bindRowEvents);
        recalc();
    })();
</script>
@endsection
