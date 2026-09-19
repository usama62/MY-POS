@extends('layouts.guest')

@section('content')
<div class="login-box">
    <div class="login-logo"><b>POS</b> Pro</div>
    <div class="card">
        <div class="card-body login-card-body">
            <p class="login-box-msg">{{ __('pos.login_title') }}</p>
            <form action="{{ route('login.attempt') }}" method="POST">
                @csrf
                <div class="input-group mb-3">
                    <input type="email" name="email" class="form-control @error('email') is-invalid @enderror" placeholder="Email" value="{{ old('email') }}" required autofocus>
                    <div class="input-group-append"><div class="input-group-text"><span class="fas fa-envelope"></span></div></div>
                    @error('email')<span class="invalid-feedback d-block">{{ $message }}</span>@enderror
                </div>
                <div class="input-group mb-3">
                    <input type="password" name="password" class="form-control" placeholder="Password" required>
                    <div class="input-group-append"><div class="input-group-text"><span class="fas fa-lock"></span></div></div>
                </div>
                <div class="row">
                    <div class="col-8">
                        <div class="icheck-primary">
                            <input type="checkbox" id="remember" name="remember" value="1">
                            <label for="remember">{{ __('pos.remember_me') }}</label>
                        </div>
                    </div>
                    <div class="col-4">
                        <button type="submit" class="btn btn-primary btn-block">{{ __('pos.login') }}</button>
                    </div>
                </div>
            </form>
            <p class="mb-0 mt-3 text-muted small">
                Admin: admin@pos.local / password<br>
                Cashier: cashier@pos.local / password
            </p>
        </div>
    </div>
</div>
@endsection
