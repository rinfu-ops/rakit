@extends('layouts.app')

@section('title', 'New Catalog Item')

@section('content')
    @if ($readOnly)
        <div class="alert alert-warning" role="alert">Catalog creation is blocked while RAKIT is in READ_ONLY mode.</div>
    @endif

    <form method="GET" action="{{ route('catalog.create') }}" class="card mb-4">
        <div class="card-body">
            <label for="candidate_name" class="form-label">Duplicate candidate search</label>
            <div class="input-group">
                <input id="candidate_name" name="candidate_name" type="search" class="form-control" value="{{ $candidateName }}" maxlength="1000" placeholder="Proposed standard name">
                <button type="submit" class="btn btn-outline-primary" title="Check duplicate candidates"><i class="bx bx-search"></i></button>
            </div>
        </div>
    </form>

    @if ($candidateName !== '')
        <div class="card mb-4">
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead><tr><th>Possible duplicate</th><th>Category / Group</th><th>Status</th><th class="text-end"></th></tr></thead>
                    <tbody>
                        @forelse ($duplicateCandidates as $candidate)
                            <tr>
                                <td><span class="d-block fw-semibold">{{ $candidate->catalog_code }}</span><small>{{ $candidate->standard_name }}</small></td>
                                <td>{{ $candidate->category?->name ?? 'Uncategorized' }} / {{ $candidate->group->name }}</td>
                                <td><span class="badge bg-label-secondary">{{ $candidate->status->value }}</span></td>
                                <td class="text-end"><a href="{{ route('catalog.show', $candidate) }}" class="btn btn-sm btn-icon btn-outline-secondary" title="Open candidate"><i class="bx bx-link-external"></i></a></td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="text-center text-muted py-4">No likely duplicates found.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    <form method="POST" action="{{ route('catalog.store') }}" class="card">
        @csrf
        <div class="card-body">
            @include('catalog._form')
        </div>
    </form>
@endsection
