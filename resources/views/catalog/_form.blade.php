@php($editing = isset($catalogItem))

@if ($editing)
    <div class="row g-3 mb-3">
        <div class="col-md-4">
            <label class="form-label">Catalog ID</label>
            <input type="text" class="form-control" value="{{ $catalogItem->catalog_code }}" disabled>
        </div>
        <div class="col-md-4">
            <label class="form-label">Discipline / Item type</label>
            <input type="text" class="form-control" value="{{ $catalogItem->discipline_code }} / {{ $catalogItem->item_type_code }}" disabled>
        </div>
        <div class="col-md-4">
            <label class="form-label">Group</label>
            <input type="text" class="form-control" value="{{ $catalogItem->group->name }}" disabled>
        </div>
    </div>
@else
    <div class="row g-3 mb-3">
        <div class="col-md-4">
            <label for="discipline_code" class="form-label">Discipline code</label>
            <input id="discipline_code" name="discipline_code" type="text" maxlength="20" class="form-control @error('discipline_code') is-invalid @enderror" value="{{ old('discipline_code') }}" required>
            @error('discipline_code')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-4">
            <label for="item_type_code" class="form-label">Item type code</label>
            <input id="item_type_code" name="item_type_code" type="text" maxlength="20" class="form-control @error('item_type_code') is-invalid @enderror" value="{{ old('item_type_code') }}" required>
            @error('item_type_code')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-4">
            <label for="catalog_group_id" class="form-label">Group</label>
            <select id="catalog_group_id" name="catalog_group_id" class="form-select @error('catalog_group_id') is-invalid @enderror" required>
                <option value="">Select group</option>
                @foreach ($groups as $group)
                    <option value="{{ $group->id }}" @selected((string) old('catalog_group_id') === (string) $group->id)>{{ $group->code }} - {{ $group->name }}</option>
                @endforeach
            </select>
            @error('catalog_group_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
    </div>
@endif

<div class="row g-3 mb-3">
    <div class="col-md-8">
        <label for="standard_name" class="form-label">Standard name</label>
        <input id="standard_name" name="standard_name" type="text" maxlength="1000" class="form-control @error('standard_name') is-invalid @enderror" value="{{ old('standard_name', $catalogItem->standard_name ?? $candidateName ?? '') }}" required>
        @error('standard_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-4">
        <label for="normalized_unit" class="form-label">Unit</label>
        <input id="normalized_unit" name="normalized_unit" type="text" maxlength="100" class="form-control @error('normalized_unit') is-invalid @enderror" value="{{ old('normalized_unit', $catalogItem->normalized_unit ?? '') }}" required>
        @error('normalized_unit')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-md-4">
        <label for="catalog_category_id" class="form-label">Category</label>
        <select id="catalog_category_id" name="catalog_category_id" class="form-select @error('catalog_category_id') is-invalid @enderror">
            <option value="">Uncategorized</option>
            @foreach ($categories as $category)
                <option value="{{ $category->id }}" @selected((string) old('catalog_category_id', $catalogItem->catalog_category_id ?? '') === (string) $category->id)>{{ $category->code }} - {{ $category->name }}</option>
            @endforeach
        </select>
        @error('catalog_category_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-8">
        <label for="standard_description" class="form-label">Standard description</label>
        <textarea id="standard_description" name="standard_description" rows="3" maxlength="5000" class="form-control @error('standard_description') is-invalid @enderror">{{ old('standard_description', $catalogItem->standard_description ?? '') }}</textarea>
        @error('standard_description')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
</div>

<div class="mb-4">
    <label for="specifications_json" class="form-label">Specifications (JSON)</label>
    <textarea id="specifications_json" name="specifications_json" rows="5" maxlength="20000" class="form-control font-monospace @error('specifications_json') is-invalid @enderror">{{ old('specifications_json', isset($catalogItem) ? json_encode($catalogItem->specifications, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) : '{}') }}</textarea>
    @error('specifications_json')<div class="invalid-feedback">{{ $message }}</div>@enderror
</div>

@unless ($editing)
    <div class="form-check mb-4">
        <input id="duplicate_reviewed" name="duplicate_reviewed" value="1" type="checkbox" class="form-check-input" @checked(old('duplicate_reviewed'))>
        <label for="duplicate_reviewed" class="form-check-label">I reviewed possible duplicates and confirmed this is a distinct Catalog identity.</label>
    </div>
@endunless

<div class="d-flex justify-content-end gap-2">
    <a href="{{ $editing ? route('catalog.show', $catalogItem) : route('catalog.index') }}" class="btn btn-outline-secondary">Cancel</a>
    <button type="submit" class="btn btn-primary" @disabled($readOnly)>
        <i class="bx bx-save me-1"></i>
        {{ $editing ? 'Save metadata' : 'Create Catalog Item' }}
    </button>
</div>
