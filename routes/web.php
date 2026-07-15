<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Catalog\CatalogItemController;
use App\Http\Controllers\Catalog\CatalogItemMergeController;
use App\Http\Controllers\Catalog\CatalogItemStatusController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('dashboard');
});

Route::middleware('guest')->group(function (): void {
    Route::get('login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('login', [AuthenticatedSessionController::class, 'store']);
});

Route::middleware('auth')->group(function (): void {
    Route::view('dashboard', 'dashboard')->name('dashboard');
    Route::resource('catalog', CatalogItemController::class)
        ->parameters(['catalog' => 'catalogItem'])
        ->except('destroy');
    Route::post('catalog/{catalogItem}/status', CatalogItemStatusController::class)->name('catalog.status');
    Route::post('catalog/{catalogItem}/merge', CatalogItemMergeController::class)->name('catalog.merge');
    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
});
