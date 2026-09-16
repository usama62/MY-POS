@extends('layouts.app')

@push('styles')
<style>
    .product-search-wrap { position: relative; }
    .product-search-results {
        position: absolute;
        left: 0;
        right: 0;
        top: 100%;
        z-index: 1050;
        max-height: 260px;
        overflow-y: auto;
        background: #fff;
        border: 1px solid #ced4da;
        border-top: 0;
        border-radius: 0 0 .25rem .25rem;
        box-shadow: 0 6px 16px rgba(0,0,0,.12);
        display: none;
    }
    .product-search-results.show { display: block; }
    .product-search-item {
        display: flex;
        justify-content: space-between;
        gap: 8px;
        padding: 8px 12px;
        cursor: pointer;
        border-bottom: 1px solid #f1f1f1;
    }
    .product-search-item:hover,
    .product-search-item.active {
        background: #007bff;
        color: #fff;
    }
    .product-search-item .meta { opacity: .85; font-size: 12px; white-space: nowrap; }
    .product-search-empty { padding: 10px 12px; color: #6c757d; }
</style>
@endpush

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

            <div class="form-group product-search-wrap mb-3">
                <label for="product-search">{{ __('pos.search_product') }}</label>
                <div class="input-group input-group-lg">
                    <div class="input-group-prepend">
                        <span class="input-group-text"><i class="fas fa-barcode"></i></span>
                    </div>
                    <input
                        type="text"
                        id="product-search"
                        class="form-control"
                        placeholder="{{ __('pos.search_product_placeholder') }}"
                        autocomplete="off"
                        autofocus
                    >
                </div>
                <div id="product-search-results" class="product-search-results" role="listbox"></div>
                <small class="text-muted d-block mt-1">{{ __('pos.search_product_hint') }}</small>
                <div id="scan-feedback" class="small mt-1" aria-live="polite"></div>
            </div>

            <div class="table-responsive mb-3">
                <table class="table table-bordered table-striped mb-0" id="cart-table">
                    <thead>
                        <tr>
                            <th>{{ __('pos.product') }}</th>
                            <th style="width:110px">{{ __('pos.quantity') }}</th>
                            <th style="width:110px">{{ __('pos.price') }}</th>
                            <th style="width:110px">{{ __('pos.total') }}</th>
                            <th style="width:90px" class="text-center">{{ __('pos.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody id="cart-rows">
                        <tr id="cart-empty-row">
                            <td colspan="5" class="text-center text-muted py-3">{{ __('pos.cart_empty') }}</td>
                        </tr>
                    </tbody>
                </table>
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
@php
    $productsForJs = $products->map(function ($p) {
        return [
            'id' => $p->id,
            'name' => $p->name,
            'sku' => $p->sku,
            'price' => (float) $p->price,
            'stock' => (int) $p->stock,
        ];
    })->values();
@endphp
<script>
    (function () {
        const products = @json($productsForJs);

        const form = document.getElementById('sale-form');
        const cartRows = document.getElementById('cart-rows');
        const cartEmptyRow = document.getElementById('cart-empty-row');
        const searchInput = document.getElementById('product-search');
        const resultsBox = document.getElementById('product-search-results');
        const scanFeedback = document.getElementById('scan-feedback');
        const discountInput = document.getElementById('discount-input');
        const taxInput = document.getElementById('tax-input');
        const paidInput = document.getElementById('paid-input');

        let rowIndex = 0;
        let activeResultIndex = -1;
        let filteredProducts = [];
        let filterTimer = null;
        let lastKeyAt = 0;

        const formatMoney = (value) => Number(value || 0).toFixed(2);
        const escapeHtml = (value) => String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');

        let audioCtx = null;

        const ensureAudio = () => {
            const AudioCtx = window.AudioContext || window.webkitAudioContext;
            if (!AudioCtx) return null;
            if (!audioCtx || audioCtx.state === 'closed') {
                audioCtx = new AudioCtx();
            }
            if (audioCtx.state === 'suspended') {
                audioCtx.resume();
            }
            return audioCtx;
        };

        const playBeep = () => {
            try {
                const ctx = ensureAudio();
                if (!ctx) return;

                const play = () => {
                    const oscillator = ctx.createOscillator();
                    const gain = ctx.createGain();
                    oscillator.type = 'square';
                    oscillator.frequency.value = 1400;
                    gain.gain.setValueAtTime(0.08, ctx.currentTime);
                    oscillator.connect(gain);
                    gain.connect(ctx.destination);
                    oscillator.start(ctx.currentTime);
                    gain.gain.exponentialRampToValueAtTime(0.0001, ctx.currentTime + 0.12);
                    oscillator.stop(ctx.currentTime + 0.13);
                };

                if (ctx.state === 'suspended') {
                    ctx.resume().then(play).catch(() => {});
                } else {
                    play();
                }
            } catch (e) {
                // Ignore audio errors (autoplay policies, etc.)
            }
        };

        // Unlock audio on first user interaction with the search box.
        ['pointerdown', 'keydown', 'touchstart'].forEach((evt) => {
            searchInput.addEventListener(evt, () => ensureAudio(), { once: false });
        });

        const updateEmptyState = () => {
            const hasRows = cartRows.querySelectorAll('.cart-row').length > 0;
            cartEmptyRow.style.display = hasRows ? 'none' : '';
        };

        const hasAtLeastOneLine = () => cartRows.querySelectorAll('.cart-row').length > 0;

        form.addEventListener('submit', function (e) {
            if (!hasAtLeastOneLine()) {
                e.preventDefault();
                alert(form.dataset.msgItems || 'Add at least one product.');
                searchInput.focus();
                return false;
            }
        });

        const recalc = () => {
            let subtotal = 0;
            cartRows.querySelectorAll('.cart-row').forEach((row) => {
                const price = parseFloat(row.dataset.price || '0');
                const qty = parseFloat(row.querySelector('.item-qty')?.value || '0');
                const lineTotal = price * qty;
                subtotal += lineTotal;
                row.querySelector('.item-line-total').textContent = formatMoney(lineTotal);
            });

            const discount = parseFloat(discountInput.value || '0');
            const tax = parseFloat(taxInput.value || '0');
            const paid = parseFloat(paidInput.value || '0');
            const total = Math.max(0, subtotal - discount + tax);

            document.getElementById('preview-subtotal').textContent = formatMoney(subtotal);
            document.getElementById('preview-total').textContent = formatMoney(total);
            document.getElementById('preview-paid').textContent = formatMoney(paid);
            document.getElementById('preview-change').textContent = formatMoney(paid - total);
        };

        const findCartRow = (productId) =>
            cartRows.querySelector(`.cart-row[data-product-id="${productId}"]`);

        const addProductToCart = (product) => {
            if (!product) return;

            const existing = findCartRow(product.id);
            if (existing) {
                const qtyInput = existing.querySelector('.item-qty');
                const nextQty = parseInt(qtyInput.value || '1', 10) + 1;
                if (nextQty > product.stock) {
                    alert('Not enough stock for ' + product.name);
                    return;
                }
                qtyInput.value = String(nextQty);
                playBeep();
                recalc();
                return;
            }

            if (product.stock < 1) {
                alert('Not enough stock for ' + product.name);
                return;
            }

            const tr = document.createElement('tr');
            tr.className = 'cart-row';
            tr.dataset.productId = String(product.id);
            tr.dataset.price = String(product.price);
            tr.innerHTML = `
                <td>
                    <strong>${escapeHtml(product.name)}</strong><br>
                    <small class="text-muted">${escapeHtml(product.sku)}</small>
                    <input type="hidden" name="items[${rowIndex}][product_id]" value="${Number(product.id)}">
                </td>
                <td>
                    <input type="number" min="1" max="${Number(product.stock)}" step="1" name="items[${rowIndex}][quantity]" value="1" class="form-control item-qty">
                </td>
                <td>${formatMoney(product.price)}</td>
                <td class="item-line-total">${formatMoney(product.price)}</td>
                <td class="text-center">
                    <button type="button" class="btn btn-sm btn-danger remove-row"><i class="fas fa-trash"></i></button>
                </td>
            `;

            tr.querySelector('.item-qty').addEventListener('input', recalc);
            tr.querySelector('.remove-row').addEventListener('click', () => {
                tr.remove();
                updateEmptyState();
                recalc();
            });

            cartRows.appendChild(tr);
            rowIndex += 1;
            updateEmptyState();
            playBeep();
            recalc();
        };

        const hideResults = () => {
            resultsBox.classList.remove('show');
            resultsBox.innerHTML = '';
            activeResultIndex = -1;
            filteredProducts = [];
        };

        const renderResults = (list) => {
            filteredProducts = list;
            activeResultIndex = list.length ? 0 : -1;

            if (!searchInput.value.trim()) {
                hideResults();
                return;
            }

            if (!list.length) {
                resultsBox.innerHTML = `<div class="product-search-empty">{{ __('pos.no_products_found') }}</div>`;
                resultsBox.classList.add('show');
                return;
            }

            resultsBox.innerHTML = list.map((p, i) => `
                <div class="product-search-item ${i === 0 ? 'active' : ''}" data-index="${i}" role="option">
                    <span>${escapeHtml(p.name)} <small>(${escapeHtml(p.sku)})</small></span>
                    <span class="meta">${formatMoney(p.price)} · stock ${Number(p.stock)}</span>
                </div>
            `).join('');

            resultsBox.querySelectorAll('.product-search-item').forEach((el) => {
                el.addEventListener('mousedown', (e) => {
                    e.preventDefault();
                    const idx = parseInt(el.dataset.index, 10);
                    selectResult(idx);
                });
            });

            resultsBox.classList.add('show');
        };

        const setActiveResult = (index) => {
            const items = resultsBox.querySelectorAll('.product-search-item');
            if (!items.length) return;
            activeResultIndex = (index + items.length) % items.length;
            items.forEach((el, i) => el.classList.toggle('active', i === activeResultIndex));
            items[activeResultIndex].scrollIntoView({ block: 'nearest' });
        };

        const selectResult = (index) => {
            const product = filteredProducts[index];
            if (!product) return;
            addProductToCart(product);
            searchInput.value = '';
            hideResults();
            showScanFeedback('');
            searchInput.focus();
        };

        const showScanFeedback = (message, isError = false) => {
            scanFeedback.textContent = message || '';
            scanFeedback.className = 'small mt-1 ' + (message ? (isError ? 'text-danger' : 'text-success') : '');
        };

        const findByExactSku = (code) => {
            const q = (code || '').trim().toLowerCase();
            if (!q) return null;
            return products.find((p) => String(p.sku).toLowerCase() === q) || null;
        };

        const addByBarcodeOrQuery = (raw) => {
            const code = (raw || '').trim();
            if (!code) return false;

            const exactSku = findByExactSku(code);
            if (exactSku) {
                addProductToCart(exactSku);
                searchInput.value = '';
                hideResults();
                showScanFeedback(exactSku.name + ' · ' + exactSku.sku);
                searchInput.focus();
                return true;
            }

            // If dropdown has an active match, use it.
            if (resultsBox.classList.contains('show') && activeResultIndex >= 0 && filteredProducts[activeResultIndex]) {
                selectResult(activeResultIndex);
                return true;
            }

            const q = code.toLowerCase();
            const startsWithMatches = products.filter((p) =>
                p.name.toLowerCase().startsWith(q) || p.sku.toLowerCase().startsWith(q)
            );
            if (startsWithMatches.length === 1) {
                addProductToCart(startsWithMatches[0]);
                searchInput.value = '';
                hideResults();
                showScanFeedback(startsWithMatches[0].name);
                searchInput.focus();
                return true;
            }

            showScanFeedback(@json(__('pos.barcode_not_found')) + ': ' + code, true);
            return false;
        };

        const filterProducts = (query, { allowAutoSelect = true } = {}) => {
            const q = (query || '').trim().toLowerCase();
            if (!q) {
                hideResults();
                return;
            }

            // Exact SKU typed/scanned mid-entry completion without Enter.
            const exactSku = findByExactSku(q);
            if (exactSku && allowAutoSelect) {
                addProductToCart(exactSku);
                searchInput.value = '';
                hideResults();
                showScanFeedback(exactSku.name + ' · ' + exactSku.sku);
                return;
            }

            const startsWithMatches = products.filter((p) =>
                p.name.toLowerCase().startsWith(q) || p.sku.toLowerCase().startsWith(q)
            );

            // Name shortcut: only auto-add after debounce, and only for short typed prefixes
            // so USB barcode scanners (fast + Enter) are not interrupted mid-scan.
            if (allowAutoSelect && startsWithMatches.length === 1 && q.length <= 3) {
                addProductToCart(startsWithMatches[0]);
                searchInput.value = '';
                hideResults();
                showScanFeedback(startsWithMatches[0].name);
                return;
            }

            const containsMatches = products.filter((p) =>
                !startsWithMatches.includes(p) &&
                (p.name.toLowerCase().includes(q) || p.sku.toLowerCase().includes(q))
            );

            renderResults([...startsWithMatches, ...containsMatches].slice(0, 12));
        };

        searchInput.addEventListener('input', () => {
            clearTimeout(filterTimer);
            const value = searchInput.value;
            const elapsed = Date.now() - lastKeyAt;
            // Fast bursts (< 40ms between keys) look like a scanner — wait for Enter.
            const likelyScanner = elapsed > 0 && elapsed < 40;

            filterTimer = setTimeout(() => {
                filterProducts(value, { allowAutoSelect: !likelyScanner });
            }, likelyScanner ? 180 : 220);
        });

        searchInput.addEventListener('keydown', (e) => {
            lastKeyAt = Date.now();
            ensureAudio();

            if (e.key === 'ArrowDown') {
                e.preventDefault();
                setActiveResult(activeResultIndex + 1);
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                setActiveResult(activeResultIndex - 1);
            } else if (e.key === 'Enter') {
                e.preventDefault();
                clearTimeout(filterTimer);
                addByBarcodeOrQuery(searchInput.value);
            } else if (e.key === 'Escape') {
                hideResults();
                showScanFeedback('');
            }
        });

        searchInput.addEventListener('blur', () => {
            setTimeout(hideResults, 150);
        });

        [discountInput, taxInput, paidInput].forEach((el) => el.addEventListener('input', recalc));
        updateEmptyState();
        recalc();
    })();
</script>
@endsection
