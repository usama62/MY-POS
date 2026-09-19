@csrf
<div class="row">
    <div class="col-md-6">
        <div class="form-group">
            <label>{{ __('pos.name') }}</label>
            <input type="text" name="name" value="{{ old('name', $product->name ?? '') }}" class="form-control @error('name') is-invalid @enderror" required maxlength="255">
            @error('name')<span class="invalid-feedback d-block">{{ $message }}</span>@enderror
        </div>
    </div>
    <div class="col-md-6">
        <div class="form-group">
            <label>SKU <small class="text-muted">({{ __('pos.sku_optional') }})</small></label>
            <input type="text" name="sku" value="{{ old('sku', $product->sku ?? '') }}" class="form-control @error('sku') is-invalid @enderror" maxlength="100" placeholder="{{ __('pos.sku_auto_placeholder') }}">
            @error('sku')<span class="invalid-feedback d-block">{{ $message }}</span>@enderror
        </div>
    </div>
    <div class="col-md-6">
        <div class="form-group">
            <label>{{ __('pos.price') }}</label>
            <input type="number" step="0.01" min="0" name="price" value="{{ old('price', $product->price ?? 0) }}" class="form-control @error('price') is-invalid @enderror" required inputmode="decimal">
            @error('price')<span class="invalid-feedback d-block">{{ $message }}</span>@enderror
        </div>
    </div>
    <div class="col-md-6">
        <div class="form-group">
            <label>{{ __('pos.stock') }}</label>
            <input type="number" min="0" step="1" name="stock" value="{{ old('stock', $product->stock ?? 0) }}" class="form-control @error('stock') is-invalid @enderror" required inputmode="numeric">
            @error('stock')<span class="invalid-feedback d-block">{{ $message }}</span>@enderror
        </div>
    </div>
    <div class="col-md-3">
        <div class="form-group">
            <label>{{ __('pos.min_stock') }}</label>
            <input type="number" min="0" step="1" name="min_stock" value="{{ old('min_stock', $product->min_stock ?? 10) }}" class="form-control @error('min_stock') is-invalid @enderror" required>
            @error('min_stock')<span class="invalid-feedback d-block">{{ $message }}</span>@enderror
        </div>
    </div>
    <div class="col-md-3">
        <div class="form-group">
            <label>{{ __('pos.max_stock') }}</label>
            <input type="number" min="0" step="1" name="max_stock" value="{{ old('max_stock', $product->max_stock ?? 100) }}" class="form-control @error('max_stock') is-invalid @enderror" required>
            @error('max_stock')<span class="invalid-feedback d-block">{{ $message }}</span>@enderror
        </div>
    </div>
    <div class="col-md-6">
        <div class="form-group">
            <label>{{ __('pos.category') }}</label>
            <select name="category" class="form-control @error('category') is-invalid @enderror">
                <option value="">{{ __('pos.select_category') }}</option>
                @foreach(($categories ?? []) as $categoryOption)
                    <option value="{{ $categoryOption }}" @selected(old('category', $product->category ?? '') === $categoryOption)>
                        {{ $categoryOption }}
                    </option>
                @endforeach
            </select>
            @error('category')<span class="invalid-feedback d-block">{{ $message }}</span>@enderror
        </div>
    </div>
    <div class="col-md-6">
        <div class="form-group mt-4">
            <div class="form-check">
                <input class="form-check-input" type="checkbox" name="is_active" value="1" @checked(old('is_active', $product->is_active ?? true))>
                <label class="form-check-label">{{ __('pos.active') }}</label>
            </div>
            <div class="form-check">
                <input class="form-check-input" type="checkbox" name="reorder_enabled" value="1" @checked(old('reorder_enabled', $product->reorder_enabled ?? true))>
                <label class="form-check-label">{{ __('pos.reorder_enabled') }}</label>
            </div>
        </div>
    </div>
</div>
<button class="btn btn-primary">{{ __('pos.save') }}</button>
