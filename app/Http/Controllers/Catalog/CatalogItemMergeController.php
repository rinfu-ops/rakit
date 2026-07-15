<?php

namespace App\Http\Controllers\Catalog;

use App\Domain\Catalog\Actions\MergeCatalogItems;
use App\Domain\Catalog\Models\CatalogItem;
use App\Http\Controllers\Controller;
use App\Http\Requests\Catalog\MergeCatalogItemsRequest;
use DomainException;
use Illuminate\Http\RedirectResponse;

class CatalogItemMergeController extends Controller
{
    public function __invoke(MergeCatalogItemsRequest $request, CatalogItem $catalogItem, MergeCatalogItems $mergeCatalogItems): RedirectResponse
    {
        try {
            $mergeCatalogItems->handle(
                $request->user(),
                $catalogItem,
                $request->successor(),
                $request->validated('reason'),
            );
        } catch (DomainException $exception) {
            return back()->withInput()->withErrors(['catalog' => $exception->getMessage()]);
        }

        return redirect()->route('catalog.show', $catalogItem)->with('status', 'Catalog Items merged.');
    }
}
