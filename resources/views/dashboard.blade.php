@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
    <div class="row">
        <div class="col-12">
            <div class="card mb-4">
                <div class="card-body">
                    <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3">
                        <div>
                            <h5 class="card-title mb-1">RAKIT dashboard</h5>
                            <p class="card-text text-muted mb-0">Foundation shell for authenticated workflows.</p>
                        </div>
                        <span class="badge bg-label-success">Authenticated</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-4 mb-4">
            <div class="card h-100">
                <div class="card-body">
                    <div class="avatar flex-shrink-0 mb-3">
                        <span class="avatar-initial rounded bg-label-primary"><i class="bx bx-data"></i></span>
                    </div>
                    <span class="fw-semibold d-block mb-1">Database</span>
                    <h5 class="card-title mb-2">PostgreSQL</h5>
                    <small class="text-muted">Configured foundation connection</small>
                </div>
            </div>
        </div>

        <div class="col-md-4 mb-4">
            <div class="card h-100">
                <div class="card-body">
                    <div class="avatar flex-shrink-0 mb-3">
                        <span class="avatar-initial rounded bg-label-info"><i class="bx bx-list-check"></i></span>
                    </div>
                    <span class="fw-semibold d-block mb-1">Queue</span>
                    <h5 class="card-title mb-2">Database</h5>
                    <small class="text-muted">Worker baseline enabled</small>
                </div>
            </div>
        </div>

        <div class="col-md-4 mb-4">
            <div class="card h-100">
                <div class="card-body">
                    <div class="avatar flex-shrink-0 mb-3">
                        <span class="avatar-initial rounded bg-label-warning"><i class="bx bx-lock-alt"></i></span>
                    </div>
                    <span class="fw-semibold d-block mb-1">Storage</span>
                    <h5 class="card-title mb-2">Private local</h5>
                    <small class="text-muted">Public serving disabled</small>
                </div>
            </div>
        </div>
    </div>
@endsection
