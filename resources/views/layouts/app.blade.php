<!DOCTYPE html>
<html
    lang="{{ str_replace('_', '-', app()->getLocale()) }}"
    class="light-style layout-menu-fixed"
    dir="ltr"
    data-theme="theme-default"
    data-assets-path="{{ asset('vendor/sneat/assets') }}/"
    data-template="vertical-menu-template-free"
>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>@yield('title', 'Dashboard') - {{ config('app.name') }}</title>

        <link rel="stylesheet" href="{{ asset('vendor/sneat/assets/vendor/fonts/boxicons.css') }}">
        <link rel="stylesheet" href="{{ asset('vendor/sneat/assets/vendor/css/core.css') }}" class="template-customizer-core-css">
        <link rel="stylesheet" href="{{ asset('vendor/sneat/assets/vendor/css/theme-default.css') }}" class="template-customizer-theme-css">
        <link rel="stylesheet" href="{{ asset('vendor/sneat/assets/css/demo.css') }}">
        <link rel="stylesheet" href="{{ asset('vendor/sneat/assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.css') }}">
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        <script src="{{ asset('vendor/sneat/assets/vendor/js/helpers.js') }}"></script>
        <script src="{{ asset('vendor/sneat/assets/js/config.js') }}"></script>
    </head>
    <body>
        <div class="layout-wrapper layout-content-navbar">
            <div class="layout-container">
                <aside id="layout-menu" class="layout-menu menu-vertical menu bg-menu-theme">
                    <div class="app-brand demo">
                        <a href="{{ route('dashboard') }}" class="app-brand-link">
                            <span class="app-brand-logo demo">
                                <span class="badge bg-primary rounded p-2">R</span>
                            </span>
                            <span class="app-brand-text demo menu-text fw-bolder ms-2">{{ config('app.name') }}</span>
                        </a>

                        <a href="javascript:void(0);" class="layout-menu-toggle menu-link text-large ms-auto d-block d-xl-none">
                            <i class="bx bx-chevron-left bx-sm align-middle"></i>
                        </a>
                    </div>

                    <div class="menu-inner-shadow"></div>

                    <ul class="menu-inner py-1">
                        <li class="menu-item {{ request()->routeIs('dashboard') ? 'active' : '' }}">
                            <a href="{{ route('dashboard') }}" class="menu-link">
                                <i class="menu-icon tf-icons bx bx-home-circle"></i>
                                <div>Dashboard</div>
                            </a>
                        </li>
                        <li class="menu-item {{ request()->routeIs('catalog.*') ? 'active' : '' }}">
                            <a href="{{ route('catalog.index') }}" class="menu-link">
                                <i class="menu-icon tf-icons bx bx-library"></i>
                                <div>Catalog</div>
                            </a>
                        </li>
                    </ul>
                </aside>

                <div class="layout-page">
                    <nav class="layout-navbar container-xxl navbar navbar-expand-xl navbar-detached align-items-center bg-navbar-theme" id="layout-navbar">
                        <div class="layout-menu-toggle navbar-nav align-items-xl-center me-3 me-xl-0 d-xl-none">
                            <a class="nav-item nav-link px-0 me-xl-4" href="javascript:void(0);">
                                <i class="bx bx-menu bx-sm"></i>
                            </a>
                        </div>

                        <div class="navbar-nav-right d-flex align-items-center" id="navbar-collapse">
                            <div class="navbar-nav align-items-center">
                                <div class="nav-item">
                                    <h4 class="fw-bold mb-0">@yield('title', 'Dashboard')</h4>
                                </div>
                            </div>

                            <ul class="navbar-nav flex-row align-items-center ms-auto">
                                @auth
                                    <li class="nav-item">
                                        <span class="nav-link text-muted">{{ auth()->user()->email }}</span>
                                    </li>
                                @endauth
                            </ul>
                        </div>
                    </nav>

                    <div class="content-wrapper">
                        <div class="container-xxl flex-grow-1 container-p-y">
                            @if (session('status'))
                                <div class="alert alert-success alert-dismissible" role="alert">
                                    {{ session('status') }}
                                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                </div>
                            @endif

                            @if ($errors->has('catalog'))
                                <div class="alert alert-danger" role="alert">{{ $errors->first('catalog') }}</div>
                            @endif

                            @yield('content')
                        </div>

                        @auth
                            <footer class="content-footer footer bg-footer-theme">
                                <div class="container-xxl d-flex flex-wrap justify-content-end py-2">
                                    <form method="POST" action="{{ route('logout') }}">
                                        @csrf
                                        <button type="submit" class="btn btn-outline-secondary">
                                            <i class="bx bx-log-out-circle me-1"></i>
                                            Log out
                                        </button>
                                    </form>
                                </div>
                            </footer>
                        @endauth

                        <div class="content-backdrop fade"></div>
                    </div>
                </div>
            </div>

            <div class="layout-overlay layout-menu-toggle"></div>
        </div>

        <script src="{{ asset('vendor/sneat/assets/vendor/libs/jquery/jquery.js') }}"></script>
        <script src="{{ asset('vendor/sneat/assets/vendor/libs/popper/popper.js') }}"></script>
        <script src="{{ asset('vendor/sneat/assets/vendor/js/bootstrap.js') }}"></script>
        <script src="{{ asset('vendor/sneat/assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.js') }}"></script>
        <script src="{{ asset('vendor/sneat/assets/vendor/js/menu.js') }}"></script>
        <script src="{{ asset('vendor/sneat/assets/js/main.js') }}"></script>
    </body>
</html>
