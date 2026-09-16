<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ in_array(app()->getLocale(), ['ar', 'ur'], true) ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name', 'POS') }}</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@5.15.4/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @endif
    <style>
        @media print {
            .no-print { display: none !important; }
            .content-wrapper, .content, .container-fluid { margin: 0 !important; padding: 0 !important; }
        }
    </style>
    @stack('styles')
</head>
<body class="hold-transition sidebar-mini layout-fixed">
<div class="wrapper">
    <nav class="main-header navbar navbar-expand navbar-white navbar-light no-print">
        <ul class="navbar-nav">
            <li class="nav-item">
                <a class="nav-link" data-widget="pushmenu" href="#"><i class="fas fa-bars"></i></a>
            </li>
        </ul>
    </nav>

    <aside class="main-sidebar sidebar-dark-primary elevation-4 no-print">
        <a href="{{ route('dashboard') }}" class="brand-link">
            <span class="brand-text font-weight-light">POS Pro</span>
        </a>
        <div class="sidebar">
            <nav class="mt-2">
                <ul class="nav nav-pills nav-sidebar flex-column">
                    <li class="nav-item"><a href="{{ route('dashboard') }}" class="nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}"><i class="nav-icon fas fa-chart-line"></i><p>{{ __('pos.dashboard') }}</p></a></li>
                    <li class="nav-item"><a href="{{ route('products.index') }}" class="nav-link {{ request()->routeIs('products.*') ? 'active' : '' }}"><i class="nav-icon fas fa-boxes"></i><p>{{ __('pos.products') }}</p></a></li>
                    <li class="nav-item"><a href="{{ route('sales.create') }}" class="nav-link {{ request()->routeIs('sales.create') ? 'active' : '' }}"><i class="nav-icon fas fa-cash-register"></i><p>{{ __('pos.new_sale') }}</p></a></li>
                    <li class="nav-item"><a href="{{ route('sales.index') }}" class="nav-link {{ request()->routeIs('sales.index') || request()->routeIs('sales.show') ? 'active' : '' }}"><i class="nav-icon fas fa-receipt"></i><p>{{ __('pos.sales') }}</p></a></li>
                    <li class="nav-item"><a href="{{ route('zakat.index') }}" class="nav-link {{ request()->routeIs('zakat.*') ? 'active' : '' }}"><i class="nav-icon fas fa-percentage"></i><p>{{ __('pos.zakat') }}</p></a></li>
                    <li class="nav-item"><a href="{{ route('settings.company.edit') }}" class="nav-link {{ request()->routeIs('settings.company.*') ? 'active' : '' }}"><i class="nav-icon fas fa-building"></i><p>{{ __('pos.company_profile') }}</p></a></li>
                </ul>
            </nav>
        </div>
    </aside>

    <div class="content-wrapper">
        <section class="content pt-3">
            <div class="container-fluid">
                @if(session('status'))
                    <div class="alert alert-success">{{ session('status') }}</div>
                @endif
                @if($errors->any())
                    <div class="alert alert-danger">
                        <ul class="mb-0">
                            @foreach($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif
                @yield('content')
            </div>
        </section>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/js/adminlte.min.js"></script>
@yield('scripts')
</body>
</html>
