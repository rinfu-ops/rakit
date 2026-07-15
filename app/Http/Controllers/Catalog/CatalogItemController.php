<?php

namespace App\Http\Controllers\Catalog;

use App\Domain\Catalog\Actions\CreateCatalogItem;
use App\Domain\Catalog\Actions\UpdateCatalogItemMetadata;
use App\Domain\Catalog\Models\CatalogCategory;
use App\Domain\Catalog\Models\CatalogGroup;
use App\Domain\Catalog\Models\CatalogItem;
use App\Domain\Catalog\Queries\FindCatalogDuplicateCandidates;
use App\Domain\Catalog\Queries\SearchCatalogItems;
use App\Domain\System\Enums\OperationalMode;
use App\Domain\System\Models\SystemOperationalMode;
use App\Http\Controllers\Controller;
use App\Http\Requests\Catalog\CatalogCreateRequest;
use App\Http\Requests\Catalog\CatalogIndexRequest;
use App\Http\Requests\Catalog\StoreCatalogItemRequest;
use App\Http\Requests\Catalog\UpdateCatalogItemRequest;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class CatalogItemController extends Controller
{
    public function index(CatalogIndexRequest $request, SearchCatalogItems $searchCatalogItems): View
    {
        return view('catalog.index', [
            'catalogItems' => $searchCatalogItems->handle($request->validated()),
            'categories' => CatalogCategory::query()->orderBy('name')->get(),
            'groups' => CatalogGroup::query()->orderBy('name')->get(),
            'statusOptions' => SearchCatalogItems::statusOptions(),
            'readOnly' => $this->isReadOnly(),
        ]);
    }

    public function show(CatalogItem $catalogItem): View
    {
        Gate::authorize('view', $catalogItem);
        $catalogItem->load([
            'aliases' => fn ($query) => $query->select(['id', 'catalog_item_id', 'raw_description', 'approved_at'])->orderBy('raw_description'),
            'category',
            'group',
            'successor',
            'mergedPredecessors',
        ]);

        return view('catalog.show', ['catalogItem' => $catalogItem, 'readOnly' => $this->isReadOnly()]);
    }

    public function create(CatalogCreateRequest $request, FindCatalogDuplicateCandidates $findDuplicates): View
    {
        $candidateName = $request->candidateName();

        return view('catalog.create', [
            'categories' => CatalogCategory::query()->orderBy('name')->get(),
            'groups' => CatalogGroup::query()->orderBy('name')->get(),
            'candidateName' => $candidateName,
            'duplicateCandidates' => $candidateName === '' ? collect() : $findDuplicates->handle($candidateName),
            'readOnly' => $this->isReadOnly(),
        ]);
    }

    public function store(StoreCatalogItemRequest $request, CreateCatalogItem $createCatalogItem): RedirectResponse
    {
        try {
            $catalogItem = $createCatalogItem->handle($request->user(), $request->catalogData());
        } catch (DomainException $exception) {
            return back()->withInput()->withErrors(['catalog' => $exception->getMessage()]);
        }

        return redirect()->route('catalog.show', $catalogItem)->with('status', 'Catalog Item created.');
    }

    public function edit(CatalogItem $catalogItem): View
    {
        Gate::authorize('update', $catalogItem);

        return view('catalog.edit', [
            'catalogItem' => $catalogItem,
            'categories' => CatalogCategory::query()->orderBy('name')->get(),
            'readOnly' => $this->isReadOnly(),
        ]);
    }

    public function update(UpdateCatalogItemRequest $request, CatalogItem $catalogItem, UpdateCatalogItemMetadata $updateMetadata): RedirectResponse
    {
        try {
            $updateMetadata->handle($request->user(), $catalogItem, $request->catalogMetadata());
        } catch (DomainException $exception) {
            return back()->withInput()->withErrors(['catalog' => $exception->getMessage()]);
        }

        return redirect()->route('catalog.show', $catalogItem)->with('status', 'Catalog metadata updated.');
    }

    private function isReadOnly(): bool
    {
        return SystemOperationalMode::query()->findOrFail(1)->mode === OperationalMode::ReadOnly;
    }
}
