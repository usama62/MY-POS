@extends('layouts.app')

@section('content')
    <div class="card card-primary">
        <div class="card-header"><h3 class="card-title">{{ __('pos.add_product') }}</h3></div>
        <div class="card-body">
        <form action="{{ route('products.store') }}" method="POST">
            @include('products._form')
        </form>
        </div>
    </div>
@endsection
