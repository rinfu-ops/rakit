@extends('layouts.app')

@section('title', 'Catalog Item')

@section('content')
    @php
        $statusClass = match ($catalogItem->status) {
            App\Domain\Catalog\Enums\CatalogStatus::Active => 'success',
            App\Domain\Catalog\Enums\CatalogStatus::Deprecated => 'warning',
            App\Domain\Catalog\Enums\CatalogStatus::Merged => 'info',
            App\Domain\Catalog\Enums\CatalogStatus::Inactive => 'secondary',
        };
    @endphp

    @if ($readOnly)
        <div class="alert alert-warning" role="alert">RAKIT is in READ_ONLY mode. Catalog mutations are blocked.</div>
    @endif

    <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 mb-4">
        <div>
            <div class="d-flex align-items-center gap-2 mb-1">
                <h5 class="mb-0">{{ $catalogItem->catalog_code }}</h5>
                <span class="badge bg-label-{{ $statusClass }}">{{ $catalogItem->status->value }}</span>
            </div>
            <span class="text-muted">{{ $catalogItem->standard_name }}</span>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('catalog.index') }}" class="btn btn-outline-secondary"><i class="bx bx-arrow-back me-1"></i>Catalog</a>
            @can('update', $catalogItem)
                @if ($catalogItem->status !== App\Domain\Catalog\Enums\CatalogStatus::Merged)
                    <a href="{{ route('catalog.edit', $catalogItem) }}" class="btn btn-primary {{ $readOnly ? 'disabled' : '' }}" @if ($readOnly) aria-disabled="true" @endif><i class="bx bx-edit me-1"></i>Edit metadata</a>
                @endif
            @endcan
        </div>
    </div>

    @if ($catalogItem->successor)
        <div class="alert alert-info" role="alert">
            Successor: <a class="alert-link" href="{{ route('catalog.show', $catalogItem->successor) }}">{{ $catalogItem->successor->catalog_code }}</a>
        </div>
    @endif

    <div class="row g-4 mb-4">
        <div class="col-lg-8">
            <div class="card h-100">
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-sm-4 mb-2">Standard name</dt><dd class="col-sm-8 mb-2">{{ $catalogItem->standard_name }}</dd>
                        <dt class="col-sm-4 mb-2">Description</dt><dd class="col-sm-8 mb-2">{{ $catalogItem->standard_description ?: 'Not set' }}</dd>
                        <dt class="col-sm-4 mb-2">Category</dt><dd class="col-sm-8 mb-2">{{ $catalogItem->category?->name ?? 'Uncategorized' }}</dd>
                        <dt class="col-sm-4 mb-2">Group</dt><dd class="col-sm-8 mb-2">{{ $catalogItem->group->name }}</dd>
                        <dt class="col-sm-4 mb-2">Unit</dt><dd class="col-sm-8 mb-2">{{ strtoupper($catalogItem->normalized_unit) }}</dd>
                        <dt class="col-sm-4 mb-0">Family</dt><dd class="col-sm-8 mb-0">{{ $catalogItem->discipline_code }} / {{ $catalogItem->item_type_code }} / {{ $catalogItem->group->code }}</dd>
                    </dl>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card h-100">
                <div class="card-header"><h6 class="mb-0">Specifications</h6></div>
                <div class="card-body">
                    @forelse ($catalogItem->specifications as $key => $value)
                        <div class="d-flex justify-content-between gap-3 border-bottom py-2">
                            <span class="text-muted">{{ str($key)->headline() }}</span>
                            <span class="text-end">{{ is_scalar($value) ? $value : json_encode($value, JSON_UNESCAPED_SLASHES) }}</span>
                        </div>
                    @empty
                        <span class="text-muted">No specifications recorded.</span>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h6 class="mb-0">Approved aliases</h6>
            <span class="badge bg-label-secondary">{{ $catalogItem->aliases->count() }}</span>
        </div>
        <div class="table-responsive">
            <table class="table mb-0">
                <thead><tr><th>Description</th><th>Approved</th></tr></thead>
                <tbody>
                    @forelse ($catalogItem->aliases as $alias)
                        <tr><td>{{ $alias->raw_description }}</td><td>{{ $alias->approved_at->format('d M Y') }}</td></tr>
                    @empty
                        <tr><td colspan="2" class="text-center text-muted py-4">No approved aliases.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if ($catalogItem->mergedPredecessors->isNotEmpty())
        <div class="card mb-4">
            <div class="card-header"><h6 class="mb-0">Merged predecessors</h6></div>
            <div class="list-group list-group-flush">
                @foreach ($catalogItem->mergedPredecessors as $predecessor)
                    <a href="{{ route('catalog.show', $predecessor) }}" class="list-group-item list-group-item-action d-flex justify-content-between"><span>{{ $predecessor->catalog_code }}</span><span>{{ $predecessor->standard_name }}</span></a>
                @endforeach
            </div>
        </div>
    @endif

    @can('changeStatus', $catalogItem)
        @if ($catalogItem->status !== App\Domain\Catalog\Enums\CatalogStatus::Merged)
            <div class="row g-4">
                <div class="col-lg-6">
                    <form method="POST" action="{{ route('catalog.status', $catalogItem) }}" class="card h-100">
                        @csrf
                        <div class="card-header"><h6 class="mb-0">Lifecycle</h6></div>
                        <div class="card-body">
                            <div class="mb-3">
                                <label for="status" class="form-label">Status</label>
                                <select id="status" name="status" class="form-select" required @disabled($readOnly)>
                                    @foreach ([App\Domain\Catalog\Enums\CatalogStatus::Active, App\Domain\Catalog\Enums\CatalogStatus::Deprecated, App\Domain\Catalog\Enums\CatalogStatus::Inactive] as $status)
                                        <option value="{{ $status->value }}" @selected($catalogItem->status === $status)>{{ $status->value }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="mb-3"><label for="status_reason" class="form-label">Reason</label><textarea id="status_reason" name="reason" class="form-control" rows="2" minlength="5" maxlength="500" required @disabled($readOnly)></textarea></div>
                            <button type="submit" class="btn btn-outline-primary" @disabled($readOnly)><i class="bx bx-refresh me-1"></i>Change status</button>
                        </div>
                    </form>
                </div>
                <div class="col-lg-6">
                    <form method="POST" action="{{ route('catalog.merge', $catalogItem) }}" class="card h-100">
                        @csrf
                        <div class="card-header"><h6 class="mb-0">Merge</h6></div>
                        <div class="card-body">
                            <div class="mb-3"><label for="successor_catalog_code" class="form-label">Successor Catalog ID</label><input id="successor_catalog_code" name="successor_catalog_code" type="text" class="form-control" maxlength="255" required @disabled($readOnly)></div>
                            <div class="mb-3"><label for="merge_reason" class="form-label">Reason</label><textarea id="merge_reason" name="reason" class="form-control" rows="2" minlength="5" maxlength="500" required @disabled($readOnly)></textarea></div>
                            <button type="submit" class="btn btn-outline-danger" @disabled($readOnly)><i class="bx bx-git-merge me-1"></i>Merge into successor</button>
                        </div>
                    </form>
                </div>
            </div>
        @endif
    @endcan
@endsection
