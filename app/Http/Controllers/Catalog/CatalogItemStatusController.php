<?php

namespace App\Http\Controllers\Catalog;

use App\Domain\Catalog\Actions\ChangeCatalogItemStatus;
use App\Domain\Catalog\Enums\CatalogStatus;
use App\Domain\Catalog\Models\CatalogItem;
use App\Http\Controllers\Controller;
use App\Http\Requests\Catalog\ChangeCatalogItemStatusRequest;
use DomainException;
use Illuminate\Http\RedirectResponse;

class CatalogItemStatusController extends Controller
{
    public function __invoke(ChangeCatalogItemStatusRequest $request, CatalogItem $catalogItem, ChangeCatalogItemStatus $changeStatus): RedirectResponse
    {
        try {
            $changeStatus->handle(
                $request->user(),
                $catalogItem,
                CatalogStatus::from($request->validated('status')),
                $request->validated('reason'),
            );
        } catch (DomainException $exception) {
            return back()->withErrors(['catalog' => $exception->getMessage()]);
        }

        return back()->with('status', 'Catalog lifecycle status updated.');
    }
}
