<?php

namespace Tests\Feature;

use App\Domain\Audit\Models\AuditEvent;
use App\Domain\Catalog\Actions\GenerateCatalogCode;
use App\Domain\Catalog\Actions\ImportBaselineCatalog;
use App\Domain\Catalog\Imports\ReadBaselineCatalogWorkbook;
use App\Domain\Catalog\Models\CatalogAlias;
use App\Domain\Catalog\Models\CatalogCategory;
use App\Domain\Catalog\Models\CatalogGroup;
use App\Domain\Catalog\Models\CatalogIdCounter;
use App\Domain\Catalog\Models\CatalogItem;
use App\Domain\Pricing\Models\PriceObservation;
use App\Domain\System\Actions\ChangeOperationalMode;
use App\Domain\System\Enums\OperationalMode;
use App\Domain\System\Enums\UserRole;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\MassAssignmentException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Log\Logger as LaravelLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Mockery;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\TestHandler;
use Monolog\Logger as MonologLogger;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class BaselineCatalogImportTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function tearDown(): void
    {
        Storage::disk('local')->deleteDirectory('phase-three-tests');
        File::delete(storage_path('logs/phase-three-sanitized-test.log'));
        parent::tearDown();
    }

    public function test_approved_synthetic_baseline_import_reconciles_and_reruns_idempotently(): void
    {
        $admin = User::factory()->withRole(UserRole::Admin)->create();
        $longApprovedName = str_repeat('Synthetic approved scope ', 20);
        $path = $this->writeWorkbook([
            $this->row('EL-PKG-KBL-0041', 'LINE-1', 'Synthetic cable package', 'Cable package wording', 125000, '2026-01-15'),
            $this->row('EL-PKG-KBL-0043', 'LINE-2', $longApprovedName, 'Termination service wording'),
        ]);

        $first = app(ImportBaselineCatalog::class)->handle($admin, $path, 'SYNTHETIC-V1');

        $this->assertSame(2, $first->sourceRows);
        $this->assertSame(2, $first->catalogItemsCreated);
        $this->assertSame(2, $first->aliasesCreated);
        $this->assertSame(1, $first->pricesCreated);
        $this->assertSame(2, CatalogItem::query()->count());
        $this->assertSame(2, CatalogAlias::query()->count());
        $this->assertSame(1, PriceObservation::query()->count());
        $this->assertSame(2, AuditEvent::query()->where('event_name', 'CATALOG_ITEM_CREATED')->count());
        $this->assertSame('Synthetic cable package', CatalogAlias::query()->where('raw_description', 'Cable package wording')->firstOrFail()->catalogItem->standard_name);
        $this->assertSame(trim($longApprovedName), CatalogItem::query()->where('catalog_code', 'EL-PKG-KBL-0043')->value('standard_name'));

        $second = app(ImportBaselineCatalog::class)->handle($admin, $path, 'SYNTHETIC-V1');

        $this->assertSame(0, $second->catalogItemsCreated);
        $this->assertSame(2, $second->sourceRowsReconciled);
        $this->assertSame(0, $second->aliasesCreated);
        $this->assertSame(0, $second->pricesCreated);
        $this->assertSame(2, CatalogItem::query()->count());
        $this->assertSame(1, PriceObservation::query()->count());
    }

    public function test_malformed_or_formula_baseline_fails_without_partial_trusted_writes(): void
    {
        $admin = User::factory()->withRole(UserRole::Admin)->create();
        $rows = [
            $this->row('EL-PKG-KBL-0001', 'LINE-1', 'Valid synthetic item'),
            $this->row('EL-PKG-KBL-0002', 'LINE-2', 'Invalid synthetic item', unitPrice: -1, observedAt: '2026-01-15'),
        ];
        $path = $this->writeWorkbook($rows);

        try {
            app(ImportBaselineCatalog::class)->handle($admin, $path, 'SYNTHETIC-BAD');
            $this->fail('Malformed baseline was accepted.');
        } catch (InvalidArgumentException) {
            $this->addToAssertionCount(1);
        }

        $this->assertSame(0, CatalogItem::query()->count());
        $this->assertSame(0, PriceObservation::query()->count());

        $formulaPath = $this->writeWorkbook([$this->row('EL-PKG-KBL-0001', 'LINE-1', 'Formula synthetic item')], formulaCell: true);
        $this->expectException(InvalidArgumentException::class);
        app(ImportBaselineCatalog::class)->handle($admin, $formulaPath, 'SYNTHETIC-FORMULA');
    }

    public function test_viewer_is_denied_and_read_only_blocks_authorized_import(): void
    {
        $viewer = User::factory()->create();
        $path = $this->writeWorkbook([$this->row('EL-PKG-KBL-0001', 'LINE-1', 'Synthetic item')]);

        try {
            app(ImportBaselineCatalog::class)->handle($viewer, $path, 'SYNTHETIC-AUTH');
            $this->fail('Viewer imported a baseline.');
        } catch (AuthorizationException) {
            $this->addToAssertionCount(1);
        }

        $admin = User::factory()->withRole(UserRole::Admin)->create();
        app(ChangeOperationalMode::class)->handle($admin, OperationalMode::ReadOnly, 'Synthetic test');

        $this->expectException(\DomainException::class);
        app(ImportBaselineCatalog::class)->handle($admin, $path, 'SYNTHETIC-AUTH');
    }

    public function test_catalog_identity_is_unique_immutable_and_allocated_from_counter_not_maximum(): void
    {
        $admin = User::factory()->withRole(UserRole::Admin)->create();
        $path = $this->writeWorkbook([$this->row('EL-PKG-KBL-0042', 'LINE-1', 'Synthetic item')]);
        app(ImportBaselineCatalog::class)->handle($admin, $path, 'SYNTHETIC-ID');
        $item = CatalogItem::query()->sole();

        $this->assertSame('EL-PKG-KBL-0042', $item->catalog_code);
        $this->assertSame(42, $item->sequence_number);
        $this->assertSame(42, CatalogIdCounter::query()->sole()->last_sequence);
        $this->assertDatabaseRejects(fn () => DB::table((new CatalogItem)->getTable())->where('id', $item->id)->update(['catalog_code' => 'EL-PKG-KBL-9999']));

        CatalogIdCounter::query()->where('discipline_code', 'EL')->where('item_type_code', 'PKG')->where('group_code', 'KBL')->update(['last_sequence' => 2]);
        (new CatalogItem)->forceFill([
            'catalog_code' => 'EL-PKG-KBL-0999', 'discipline_code' => 'EL', 'item_type_code' => 'PKG',
            'catalog_group_id' => CatalogGroup::query()->sole()->id, 'catalog_category_id' => CatalogCategory::query()->sole()->id,
            'sequence_number' => 999, 'standard_name' => 'Synthetic maximum decoy', 'normalized_name' => 'synthetic maximum decoy',
            'normalized_unit' => 'ls', 'specifications' => [], 'status' => 'ACTIVE', 'approved_at' => now(),
        ])->save();

        $allocation = DB::transaction(fn () => app(GenerateCatalogCode::class)->handle('EL', 'PKG', 'KBL'));
        $this->assertSame(3, $allocation->sequenceNumber);
        $this->assertSame('EL-PKG-KBL-0003', $allocation->catalogCode);
        $this->assertDatabaseRejects(fn () => DB::table((new CatalogItem)->getTable())->insert([
            'catalog_code' => $item->catalog_code, 'discipline_code' => 'EL', 'item_type_code' => 'PKG',
            'catalog_group_id' => $item->catalog_group_id, 'sequence_number' => 50, 'standard_name' => 'Duplicate',
            'normalized_name' => 'duplicate', 'normalized_unit' => 'ls', 'status' => 'ACTIVE', 'approved_at' => now(),
            'created_at' => now(), 'updated_at' => now(),
        ]));
    }

    public function test_oversized_locked_catalog_sequences_are_rejected_without_trusted_writes(): void
    {
        $admin = User::factory()->withRole(UserRole::Admin)->create();

        foreach (['9223372036854775808', '999999999999999999999999999'] as $suffix) {
            $path = $this->writeWorkbook([
                $this->row("EL-PKG-KBL-{$suffix}", 'LINE-'.$suffix, 'Synthetic oversized sequence'),
            ]);

            try {
                app(ImportBaselineCatalog::class)->handle($admin, $path, 'SYNTHETIC-SEQUENCE');
                $this->fail('An oversized locked Catalog sequence was accepted.');
            } catch (InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }

        try {
            DB::transaction(fn () => app(GenerateCatalogCode::class)->reserveLocked(
                'EL-PKG-KBL-9223372036854775808',
                'EL',
                'PKG',
                'KBL',
            ));
            $this->fail('The allocator accepted an oversized locked Catalog sequence.');
        } catch (\LogicException) {
            $this->addToAssertionCount(1);
        }

        $this->assertSame(0, CatalogItem::query()->count());
        $this->assertSame(0, CatalogIdCounter::query()->count());
        $this->assertSame(0, AuditEvent::query()->count());
        $this->assertDatabaseCount('baseline_catalog_item_sources', 0);
    }

    public function test_future_catalog_allocation_fails_cleanly_when_counter_is_exhausted(): void
    {
        $maximum = '9223372036854775807';
        (new CatalogIdCounter)->forceFill([
            'discipline_code' => 'EL',
            'item_type_code' => 'PKG',
            'group_code' => 'KBL',
            'last_sequence' => $maximum,
        ])->save();
        $updatedAt = CatalogIdCounter::query()->sole()->updated_at;

        try {
            DB::transaction(fn () => app(GenerateCatalogCode::class)->handle('EL', 'PKG', 'KBL'));
            $this->fail('An exhausted Catalog sequence counter was incremented.');
        } catch (\DomainException) {
            $this->addToAssertionCount(1);
        }

        $this->assertSame($maximum, (string) CatalogIdCounter::query()->sole()->last_sequence);
        $this->assertTrue($updatedAt->equalTo(CatalogIdCounter::query()->sole()->updated_at));
        $this->assertSame(0, CatalogItem::query()->count());
        $this->assertSame(0, AuditEvent::query()->count());
    }

    public function test_source_drift_rolls_back_items_created_earlier_in_the_same_rerun(): void
    {
        $admin = User::factory()->withRole(UserRole::Admin)->create();
        $original = $this->writeWorkbook([$this->row('EL-PKG-KBL-0001', 'LINE-1', 'Original synthetic item')]);
        app(ImportBaselineCatalog::class)->handle($admin, $original, 'SYNTHETIC-DRIFT');

        $changed = $this->writeWorkbook([
            $this->row('EL-PKG-KBL-0002', 'LINE-2', 'New synthetic item'),
            $this->row('EL-PKG-KBL-0001', 'LINE-1', 'Changed synthetic item'),
        ]);

        try {
            app(ImportBaselineCatalog::class)->handle($admin, $changed, 'SYNTHETIC-DRIFT');
            $this->fail('Changed source identity was accepted.');
        } catch (\DomainException) {
            $this->addToAssertionCount(1);
        }

        $this->assertSame(1, CatalogItem::query()->count());
        $this->assertSame(1, AuditEvent::query()->where('event_name', 'CATALOG_ITEM_CREATED')->count());
        $this->assertSame(1, CatalogIdCounter::query()->sole()->last_sequence);
    }

    public function test_catalog_identity_fields_cannot_be_generically_mass_assigned(): void
    {
        $admin = User::factory()->withRole(UserRole::Admin)->create();
        $path = $this->writeWorkbook([$this->row('EL-PKG-KBL-0001', 'LINE-1', 'Synthetic item')]);
        app(ImportBaselineCatalog::class)->handle($admin, $path, 'SYNTHETIC-MASS');

        $this->expectException(MassAssignmentException::class);
        CatalogItem::query()->sole()->update(['catalog_code' => 'EL-PKG-KBL-9999']);
    }

    public function test_invalid_source_identity_is_rejected_without_writes(): void
    {
        $admin = User::factory()->withRole(UserRole::Admin)->create();
        $path = $this->writeWorkbook([$this->row('EL-PKG-KBL-0001', 'LINE-1', 'Synthetic item')]);

        $this->expectException(InvalidArgumentException::class);
        try {
            app(ImportBaselineCatalog::class)->handle($admin, $path, '../PRIVATE');
        } finally {
            $this->assertSame(0, CatalogItem::query()->count());
        }
    }

    public function test_unexpected_command_failure_logs_only_safe_metadata(): void
    {
        $admin = User::factory()->withRole(UserRole::Admin)->create();
        $confidentialMarker = 'CONFIDENTIAL-WORKBOOK-MARKER-7D6D7E';
        $import = Mockery::mock(ImportBaselineCatalog::class);
        $import->shouldReceive('handle')->once()->andThrow(new \RuntimeException($confidentialMarker));
        $this->app->instance(ImportBaselineCatalog::class, $import);
        $logPath = storage_path('logs/phase-three-sanitized-test.log');
        $testHandler = new TestHandler;
        Log::swap(new LaravelLogger(new MonologLogger('phase-three-test', [
            $testHandler,
            new StreamHandler($logPath),
        ])));

        $this->artisan('catalog:import-baseline', [
            'path' => 'private-source.xlsx',
            '--source' => 'SYNTHETIC-FAILURE',
            '--actor' => $admin->id,
        ])->expectsOutputToContain('Baseline import failed. Reference:')
            ->doesntExpectOutputToContain($confidentialMarker)
            ->doesntExpectOutputToContain('private-source.xlsx')
            ->assertFailed();

        $this->assertCount(1, $testHandler->getRecords());
        $record = $testHandler->getRecords()[0];
        $this->assertSame('Baseline import failed.', $record->message);
        $this->assertSame(\RuntimeException::class, $record->context['exception_class']);
        $this->assertSame(['failure_id', 'exception_class'], array_keys($record->context));
        $this->assertMatchesRegularExpression('/^[0-9a-f-]{36}$/', $record->context['failure_id']);

        $serializedContext = json_encode($record->context, JSON_THROW_ON_ERROR);
        $logOutput = file_get_contents($logPath);
        $this->assertIsString($logOutput);
        $this->assertStringNotContainsString($confidentialMarker, $serializedContext);
        $this->assertStringNotContainsString($confidentialMarker, $logOutput);
        $this->assertStringNotContainsString('private-source.xlsx', $serializedContext);
        $this->assertStringNotContainsString('private-source.xlsx', $logOutput);
    }

    public function test_quantity_reconciliation_uses_exact_canonical_decimals(): void
    {
        $admin = User::factory()->withRole(UserRole::Admin)->create();
        $equivalent = $this->writeWorkbook([
            $this->row('EL-PKG-KBL-0001', 'LINE-1', 'Synthetic exact quantity', unitPrice: 1000, observedAt: '2026-01-15', quantity: '1.23'),
        ]);
        app(ImportBaselineCatalog::class)->handle($admin, $equivalent, 'SYNTHETIC-DECIMAL');
        $equivalentRerun = $this->writeWorkbook([
            $this->row('EL-PKG-KBL-0001', 'LINE-1', 'Synthetic exact quantity', unitPrice: 1000, observedAt: '2026-01-15', quantity: '1.2300'),
        ]);

        $report = app(ImportBaselineCatalog::class)->handle($admin, $equivalentRerun, 'SYNTHETIC-DECIMAL');

        $this->assertSame(1, $report->sourceRowsReconciled);
        $this->assertSame('1.2300', PriceObservation::query()->where('source_id', 'SYNTHETIC-DECIMAL')->value('quantity'));

        $changed = $this->writeWorkbook([
            $this->row('EL-PKG-KBL-0001', 'LINE-1', 'Synthetic exact quantity', unitPrice: 1000, observedAt: '2026-01-15', quantity: '1.2301'),
        ]);
        $this->assertQuantityDriftRejected($admin, $changed, 'SYNTHETIC-DECIMAL');

        $large = $this->writeWorkbook([
            $this->row('EL-PKG-KBL-0002', 'LINE-2', 'Synthetic large quantity', unitPrice: 2000, observedAt: '2026-01-15', quantity: '9999999999999999.0001'),
        ]);
        app(ImportBaselineCatalog::class)->handle($admin, $large, 'SYNTHETIC-LARGE');
        $largeChanged = $this->writeWorkbook([
            $this->row('EL-PKG-KBL-0002', 'LINE-2', 'Synthetic large quantity', unitPrice: 2000, observedAt: '2026-01-15', quantity: '9999999999999999.0002'),
        ]);
        $this->assertQuantityDriftRejected($admin, $largeChanged, 'SYNTHETIC-LARGE');
    }

    public function test_invalid_quantities_are_rejected_exactly(): void
    {
        $admin = User::factory()->withRole(UserRole::Admin)->create();

        foreach (['1.00001', '-0.0001', -0.0001, '1e3', 'NaN', 'INF', '10000000000000000.0000'] as $index => $quantity) {
            $path = $this->writeWorkbook([
                $this->row('EL-PKG-KBL-'.str_pad((string) ($index + 1), 4, '0', STR_PAD_LEFT), 'LINE-'.$index, 'Synthetic invalid quantity', unitPrice: 1000, observedAt: '2026-01-15', quantity: $quantity),
            ]);

            try {
                app(ImportBaselineCatalog::class)->handle($admin, $path, 'SYNTHETIC-INVALID');
                $this->fail("Invalid quantity case {$index} was accepted.");
            } catch (InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }

        $this->assertSame(0, PriceObservation::query()->count());
    }

    public function test_unit_price_accepts_the_exact_postgresql_bigint_maximum(): void
    {
        $admin = User::factory()->withRole(UserRole::Admin)->create();
        $maximum = '9223372036854775807';
        $path = $this->writeWorkbook([
            $this->row('EL-PKG-KBL-0001', 'LINE-1', 'Synthetic maximum exact price', unitPrice: $maximum, observedAt: '2026-01-15'),
        ]);

        $first = app(ImportBaselineCatalog::class)->handle($admin, $path, 'SYNTHETIC-MONEY-MAX');
        $second = app(ImportBaselineCatalog::class)->handle($admin, $path, 'SYNTHETIC-MONEY-MAX');

        $this->assertSame(1, $first->pricesCreated);
        $this->assertSame(0, $second->pricesCreated);
        $this->assertSame(1, $second->sourceRowsReconciled);
        $this->assertSame($maximum, (string) PriceObservation::query()->sole()->unit_price_rupiah);
    }

    public function test_invalid_or_inexact_unit_prices_are_rejected_without_clamping(): void
    {
        $admin = User::factory()->withRole(UserRole::Admin)->create();
        $invalidPrices = [
            'bigint overflow' => '9223372036854775808',
            'much larger digits' => '999999999999999999999999999',
            'negative integer' => -1,
            'fractional string' => '125000.50',
            'fractional float' => 125000.5,
            'scientific notation' => '1e6',
            'surrounding whitespace' => ' 125000 ',
            'not a number' => 'NaN',
            'infinity' => 'INF',
            'unsafe large float' => 9_223_372_036_854_776_000.0,
        ];

        foreach ($invalidPrices as $label => $unitPrice) {
            $path = $this->writeWorkbook([
                $this->row('EL-PKG-KBL-0001', 'LINE-'.$label, 'Synthetic invalid exact price', unitPrice: $unitPrice, observedAt: '2026-01-15'),
            ]);

            try {
                app(ImportBaselineCatalog::class)->handle($admin, $path, 'SYNTHETIC-MONEY-BAD');
                $this->fail("Invalid unit price case {$label} was accepted.");
            } catch (InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }

        $this->assertSame(0, PriceObservation::query()->count());
    }

    /** @param array<int, array<int, mixed>> $rows */
    private function writeWorkbook(array $rows, bool $formulaCell = false): string
    {
        $path = 'phase-three-tests/baseline-'.uniqid().'.xlsx';
        Storage::disk('local')->makeDirectory('phase-three-tests');
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Catalog');
        $sheet->fromArray(ReadBaselineCatalogWorkbook::HEADERS, null, 'A1');
        foreach ($rows as $index => $row) {
            $sheet->fromArray($row, null, 'A'.($index + 2));
            if (is_string($row[12])) {
                $sheet->getCell('M'.($index + 2))->setValueExplicit($row[12], DataType::TYPE_STRING);
            }
            if (is_string($row[13])) {
                $sheet->getCell('N'.($index + 2))->setValueExplicit($row[13], DataType::TYPE_STRING);
            }
        }
        if ($formulaCell) {
            $sheet->getCell('I2')->setValueExplicit('=1+1', DataType::TYPE_FORMULA);
        }
        (new Xlsx($spreadsheet))->save(Storage::disk('local')->path($path));

        return $path;
    }

    /** @return array<int, mixed> */
    private function row(string $itemId, string $lineId, string $name, ?string $alias = null, int|float|string|null $unitPrice = null, ?string $observedAt = null, int|float|string|null $quantity = null): array
    {
        return [$itemId, $lineId, 'EL', 'PKG', 'ELEC', 'Electrical', 'KBL', 'Cable', $name, null, 'LS', $alias,
            $unitPrice, $quantity, $observedAt, $unitPrice === null ? null : 'RAP_COST', $unitPrice === null ? null : 'UNKNOWN'];
    }

    private function assertQuantityDriftRejected(User $admin, string $path, string $sourceId): void
    {
        try {
            app(ImportBaselineCatalog::class)->handle($admin, $path, $sourceId);
            $this->fail('Changed exact quantity was accepted.');
        } catch (\DomainException) {
            $this->addToAssertionCount(1);
        }
    }

    private function assertDatabaseRejects(\Closure $operation): void
    {
        try {
            DB::transaction($operation);
            $this->fail('PostgreSQL accepted an invalid Catalog identity mutation.');
        } catch (QueryException) {
            $this->addToAssertionCount(1);
        }
    }
}
