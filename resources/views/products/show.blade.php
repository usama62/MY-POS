@extends('layouts.app')

@section('content')
    <h1 class="text-2xl font-bold mb-4">{{ $product->name }}</h1>
    <div class="bg-white border rounded p-4">
        <p><strong>SKU:</strong> {{ $product->sku }}</p>
        <p><strong>{{ __('pos.price') }}:</strong> {{ number_format($product->price, 2) }}</p>
        <p><strong>{{ __('pos.stock') }}:</strong> {{ $product->stock }}</p>
        <p><strong>{{ __('pos.category') }}:</strong> {{ $product->category ?: '-' }}</p>
    </div>
@endsection
