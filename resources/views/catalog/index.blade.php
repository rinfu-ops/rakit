@extends('layouts.app')

@section('title', 'Catalog')

@section('content')
    @if ($readOnly)
        <div class="alert alert-warning d-flex align-items-center" role="alert">
            <i class="bx bx-lock-alt me-2"></i>
            <span>RAKIT is in READ_ONLY mode. Catalog browsing remains available.</span>
        </div>
    @endif

    <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 mb-4">
        <div>
            <h5 class="mb-1">Catalog Items</h5>
            <span class="text-muted">{{ $catalogItems->total() }} records</span>
        </div>
        @can('create', App\Domain\Catalog\Models\CatalogItem::class)
            <a href="{{ route('catalog.create') }}" class="btn btn-primary {{ $readOnly ? 'disabled' : '' }}" @if ($readOnly) aria-disabled="true" @endif>
                <i class="bx bx-plus me-1"></i>
                New Catalog Item
            </a>
        @endcan
    </div>

    <form method="GET" action="{{ route('catalog.index') }}" class="card mb-4">
        <div class="card-body">
            <div class="row g-3 align-items-end">
                <div class="col-lg-5">
                    <label for="query" class="form-label">Search</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bx bx-search"></i></span>
                        <input id="query" name="query" type="search" class="form-control" value="{{ request('query') }}" placeholder="Catalog code, name, or alias">
                    </div>
                </div>
                <div class="col-sm-6 col-lg-2">
                    <label for="status" class="form-label">Status</label>
                    <select id="status" name="status" class="form-select">
                        <option value="">All</option>
                        @foreach ($statusOptions as $value => $label)
                            <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-sm-6 col-lg-2">
                    <label for="category_id" class="form-label">Category</label>
                    <select id="category_id" name="category_id" class="form-select">
                        <option value="">All</option>
                        @foreach ($categories as $category)
                            <option value="{{ $category->id }}" @selected((string) request('category_id') === (string) $category->id)>{{ $category->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-sm-6 col-lg-2">
                    <label for="group_id" class="form-label">Group</label>
                    <select id="group_id" name="group_id" class="form-select">
                        <option value="">All</option>
                        @foreach ($groups as $group)
                            <option value="{{ $group->id }}" @selected((string) request('group_id') === (string) $group->id)>{{ $group->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-sm-6 col-lg-1 d-grid">
                    <button type="submit" class="btn btn-outline-primary" title="Apply filters">
                        <i class="bx bx-filter-alt"></i>
                    </button>
                </div>
            </div>
        </div>
    </form>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>Catalog ID</th>
                        <th>Name</th>
                        <th>Category / Group</th>
                        <th>Unit</th>
                        <th>Aliases</th>
                        <th>Status</th>
                        <th class="text-end"><span class="visually-hidden">Open</span></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($catalogItems as $catalogItem)
                        @php
                            $statusClass = match ($catalogItem->status) {
                                App\Domain\Catalog\Enums\CatalogStatus::Active => 'success',
                                App\Domain\Catalog\Enums\CatalogStatus::Deprecated => 'warning',
                                App\Domain\Catalog\Enums\CatalogStatus::Merged => 'info',
                                App\Domain\Catalog\Enums\CatalogStatus::Inactive => 'secondary',
                            };
                        @endphp
                        <tr>
                            <td><a class="fw-semibold" href="{{ route('catalog.show', $catalogItem) }}">{{ $catalogItem->catalog_code }}</a></td>
                            <td>
                                <span class="d-block text-body fw-medium">{{ $catalogItem->standard_name }}</span>
                                @if ($catalogItem->status === App\Domain\Catalog\Enums\CatalogStatus::Merged && $catalogItem->successor)
                                    <small class="text-muted">Successor: {{ $catalogItem->successor->catalog_code }}</small>
                                @endif
                            </td>
                            <td>
                                <span class="d-block">{{ $catalogItem->category?->name ?? 'Uncategorized' }}</span>
                                <small class="text-muted">{{ $catalogItem->group->name }}</small>
                            </td>
                            <td>{{ strtoupper($catalogItem->normalized_unit) }}</td>
                            <td>{{ $catalogItem->aliases_count }}</td>
                            <td><span class="badge bg-label-{{ $statusClass }}">{{ $catalogItem->status->value }}</span></td>
                            <td class="text-end">
                                <a href="{{ route('catalog.show', $catalogItem) }}" class="btn btn-sm btn-icon btn-outline-secondary" title="Open Catalog Item">
                                    <i class="bx bx-chevron-right"></i>
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center text-muted py-5">No Catalog Items found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($catalogItems->hasPages())
            <div class="card-footer d-flex justify-content-end">{{ $catalogItems->links() }}</div>
        @endif
    </div>
@endsection
