<?php

namespace App\Console\Commands;

use App\Domain\Catalog\Actions\ImportBaselineCatalog;
use App\Models\User;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Throwable;

#[Signature('catalog:import-baseline
    {path : Relative path on the private local disk}
    {--source= : Stable approved baseline source ID}
    {--actor= : User ID performing the administrative import}')]
#[Description('Import an approved private Catalog baseline workbook')]
class ImportBaselineCatalogCommand extends Command
{
    public function handle(ImportBaselineCatalog $importBaselineCatalog): int
    {
        $actor = User::query()->find($this->option('actor'));
        $sourceId = $this->option('source');

        if ($actor === null || ! is_string($sourceId)) {
            $this->error('Valid --actor and --source options are required.');

            return self::FAILURE;
        }

        try {
            $report = $importBaselineCatalog->handle($actor, $this->argument('path'), $sourceId);
        } catch (AuthorizationException|DomainException|InvalidArgumentException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        } catch (Throwable $exception) {
            $failureId = (string) Str::uuid();
            Log::error('Baseline import failed.', [
                'failure_id' => $failureId,
                'exception_class' => $exception::class,
            ]);
            $this->error("Baseline import failed. Reference: {$failureId}");

            return self::FAILURE;
        }

        $this->table(['Metric', 'Count'], [
            ['Source rows', $report->sourceRows],
            ['Catalog Items created', $report->catalogItemsCreated],
            ['Source rows reconciled to existing Catalog Items', $report->sourceRowsReconciled],
            ['Aliases created', $report->aliasesCreated],
            ['Prices created', $report->pricesCreated],
        ]);

        return self::SUCCESS;
    }
}
