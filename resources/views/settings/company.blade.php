@extends('layouts.app')

@section('content')
<div class="card card-primary">
    <div class="card-header">
        <h3 class="card-title">{{ __('pos.company_profile') }}</h3>
    </div>
    <form action="{{ route('settings.company.update') }}" method="POST" enctype="multipart/form-data">
        @csrf
        @method('PUT')
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label>{{ __('pos.company_name') }}</label>
                        <input type="text" name="company_name" class="form-control @error('company_name') is-invalid @enderror" value="{{ old('company_name', $profile->company_name) }}" required maxlength="255">
                        @error('company_name')<span class="invalid-feedback d-block">{{ $message }}</span>@enderror
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label>{{ __('pos.phone') }}</label>
                        <input type="text" name="phone" class="form-control @error('phone') is-invalid @enderror" value="{{ old('phone', $profile->phone) }}" maxlength="50">
                        @error('phone')<span class="invalid-feedback d-block">{{ $message }}</span>@enderror
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label>{{ __('pos.email') }}</label>
                        <input type="email" name="email" class="form-control @error('email') is-invalid @enderror" value="{{ old('email', $profile->email) }}" maxlength="255">
                        @error('email')<span class="invalid-feedback d-block">{{ $message }}</span>@enderror
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label>{{ __('pos.logo') }}</label>
                        <input type="file" name="logo" class="form-control @error('logo') is-invalid @enderror" accept="image/jpeg,image/png,image/gif,image/webp,image/svg+xml">
                        @error('logo')<span class="invalid-feedback d-block">{{ $message }}</span>@enderror
                    </div>
                    @if($profile->logo_url)
                        <img src="{{ $profile->logo_url }}" alt="logo" style="max-height:70px;">
                    @endif
                </div>
                <div class="col-md-12">
                    <div class="form-group">
                        <label>{{ __('pos.address') }}</label>
                        <textarea name="address" class="form-control" rows="3">{{ old('address', $profile->address) }}</textarea>
                    </div>
                </div>
            </div>
        </div>
        <div class="card-footer">
            <button class="btn btn-primary">{{ __('pos.save') }}</button>
        </div>
    </form>
</div>
@endsection
