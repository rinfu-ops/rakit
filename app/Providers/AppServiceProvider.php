<?php

namespace App\Providers;

use App\Domain\Catalog\Models\CatalogItem;
use App\Domain\Catalog\Normalization\CatalogTextNormalizer;
use App\Domain\Catalog\Normalization\CatalogUnitNormalizer;
use App\Domain\Shared\Normalization\NormalizesText;
use App\Domain\Shared\Normalization\NormalizesUnit;
use App\Domain\System\Models\SystemOperationalMode;
use App\Policies\CatalogItemPolicy;
use App\Policies\SystemOperationalModePolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(NormalizesText::class, CatalogTextNormalizer::class);
        $this->app->bind(NormalizesUnit::class, CatalogUnitNormalizer::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(CatalogItem::class, CatalogItemPolicy::class);
        Gate::policy(SystemOperationalMode::class, SystemOperationalModePolicy::class);
    }
}
