<!DOCTYPE html>
<html
    lang="{{ str_replace('_', '-', app()->getLocale()) }}"
    class="light-style customizer-hide"
    dir="ltr"
    data-theme="theme-default"
    data-assets-path="{{ asset('vendor/sneat/assets') }}/"
    data-template="vertical-menu-template-free"
>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>Log in - {{ config('app.name') }}</title>

        <link rel="stylesheet" href="{{ asset('vendor/sneat/assets/vendor/fonts/boxicons.css') }}">
        <link rel="stylesheet" href="{{ asset('vendor/sneat/assets/vendor/css/core.css') }}" class="template-customizer-core-css">
        <link rel="stylesheet" href="{{ asset('vendor/sneat/assets/vendor/css/theme-default.css') }}" class="template-customizer-theme-css">
        <link rel="stylesheet" href="{{ asset('vendor/sneat/assets/css/demo.css') }}">
        <link rel="stylesheet" href="{{ asset('vendor/sneat/assets/vendor/css/pages/page-auth.css') }}">
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        <script src="{{ asset('vendor/sneat/assets/vendor/js/helpers.js') }}"></script>
        <script src="{{ asset('vendor/sneat/assets/js/config.js') }}"></script>
    </head>
    <body>
        <div class="container-xxl">
            <div class="authentication-wrapper authentication-basic container-p-y">
                <div class="authentication-inner">
                    <div class="card">
                        <div class="card-body">
                            <div class="app-brand justify-content-center">
                                <a href="{{ route('login') }}" class="app-brand-link gap-2">
                                    <span class="app-brand-logo demo">
                                        <span class="badge bg-primary rounded p-2">R</span>
                                    </span>
                                    <span class="app-brand-text demo text-body fw-bolder">{{ config('app.name') }}</span>
                                </a>
                            </div>

                            <h4 class="mb-2">Sign in</h4>
                            <p class="mb-4">Use your RAKIT account to continue.</p>

                            <form method="POST" action="{{ route('login') }}" class="mb-3">
                                @csrf

                                <div class="mb-3">
                                    <label for="email" class="form-label">Email</label>
                                    <input
                                        type="email"
                                        class="form-control @error('email') is-invalid @enderror"
                                        id="email"
                                        name="email"
                                        value="{{ old('email') }}"
                                        required
                                        autofocus
                                        autocomplete="username"
                                    >
                                    @error('email')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="mb-3 form-password-toggle">
                                    <label class="form-label" for="password">Password</label>
                                    <div class="input-group input-group-merge">
                                        <input
                                            type="password"
                                            id="password"
                                            class="form-control @error('password') is-invalid @enderror"
                                            name="password"
                                            required
                                            autocomplete="current-password"
                                        >
                                        <span class="input-group-text cursor-pointer"><i class="bx bx-hide"></i></span>
                                    </div>
                                    @error('password')
                                        <div class="invalid-feedback d-block">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="mb-3">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="remember" name="remember" value="1">
                                        <label class="form-check-label" for="remember">Remember me</label>
                                    </div>
                                </div>

                                <button type="submit" class="btn btn-primary d-grid w-100">Log in</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <script src="{{ asset('vendor/sneat/assets/vendor/libs/jquery/jquery.js') }}"></script>
        <script src="{{ asset('vendor/sneat/assets/vendor/libs/popper/popper.js') }}"></script>
        <script src="{{ asset('vendor/sneat/assets/vendor/js/bootstrap.js') }}"></script>
        <script src="{{ asset('vendor/sneat/assets/vendor/js/menu.js') }}"></script>
        <script src="{{ asset('vendor/sneat/assets/js/main.js') }}"></script>
    </body>
</html>
