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
@endsection
