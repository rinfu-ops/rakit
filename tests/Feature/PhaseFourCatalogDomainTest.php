<?php

namespace Tests\Feature;

use App\Domain\Audit\Enums\AuditEventName;
use App\Domain\Audit\Models\AuditEvent;
use App\Domain\Catalog\Actions\ChangeCatalogItemStatus;
use App\Domain\Catalog\Actions\CreateCatalogItem;
use App\Domain\Catalog\Actions\MergeCatalogItems;
use App\Domain\Catalog\Enums\CatalogStatus;
use App\Domain\Catalog\Models\BaselineCatalogItemSource;
use App\Domain\Catalog\Models\CatalogAlias;
use App\Domain\Catalog\Models\CatalogCategory;
use App\Domain\Catalog\Models\CatalogGroup;
use App\Domain\Catalog\Models\CatalogIdCounter;
use App\Domain\Catalog\Models\CatalogItem;
use App\Domain\System\Actions\ChangeOperationalMode;
use App\Domain\System\Enums\OperationalMode;
use App\Domain\System\Enums\UserRole;
use App\Models\User;
use Closure;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PhaseFourCatalogDomainTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_all_authenticated_roles_can_browse_but_only_catalog_roles_can_mutate(): void
    {
        $catalogItem = $this->catalogItem();

        foreach (UserRole::cases() as $role) {
            $user = User::factory()->withRole($role)->create();

            $this->actingAs($user)->get(route('catalog.index'))->assertOk();
            $this->actingAs($user)->get(route('catalog.show', $catalogItem))->assertOk();
            $this->assertSame(
                in_array($role, [UserRole::Admin, UserRole::CatalogManager], true),
                $user->can('create', CatalogItem::class),
            );
        }
    }

    public function test_managed_creation_allocates_a_permanent_unique_code_and_ignores_submitted_identity(): void
    {
        $manager = User::factory()->withRole(UserRole::CatalogManager)->create();
        [$category, $group] = $this->dimensions();

        $response = $this->actingAs($manager)->post(route('catalog.store'), [
            ...$this->validCreateData($category, $group),
            'catalog_code' => 'EL-PKG-KBL-9999',
            'sequence_number' => 9999,
        ]);

        $catalogItem = CatalogItem::query()->sole();
        $response->assertRedirect(route('catalog.show', $catalogItem));
        $this->assertSame('EL-PKG-KBL-0001', $catalogItem->catalog_code);
        $this->assertSame(1, $catalogItem->sequence_number);
        $this->assertSame(CatalogStatus::Active, $catalogItem->status);
        $this->assertSame(1, CatalogIdCounter::query()->sole()->last_sequence);
        $this->assertDatabaseCount('price_observations', 0);

        $auditEvent = AuditEvent::query()->sole();
        $this->assertSame(AuditEventName::CatalogItemCreated, $auditEvent->event_name);
        $this->assertSame($manager->id, $auditEvent->actor_id);
        $this->assertSame(['source_type' => 'CATALOG_MANAGEMENT'], $auditEvent->context);

        $second = app(CreateCatalogItem::class)->handle($manager, [
            ...$this->validActionCreateData($category, $group),
            'standard_name' => 'Second synthetic package',
        ]);
        $this->assertSame('EL-PKG-KBL-0002', $second->catalog_code);
        $this->assertSame(2, CatalogItem::query()->distinct('catalog_code')->count('catalog_code'));
    }

    public function test_duplicate_candidates_require_review_without_counter_or_audit_mutation(): void
    {
        $manager = User::factory()->withRole(UserRole::CatalogManager)->create();
        [$category, $group] = $this->dimensions();
        $this->catalogItem(['standard_name' => 'Synthetic cable installation package']);
        $counter = (new CatalogIdCounter)->forceFill([
            'discipline_code' => 'EL',
            'item_type_code' => 'PKG',
            'group_code' => 'KBL',
            'last_sequence' => 1,
        ]);
        $counter->save();

        $response = $this->actingAs($manager)->from(route('catalog.create'))->post(route('catalog.store'), [
            ...$this->validCreateData($category, $group),
            'standard_name' => 'Synthetic cable installation packages',
            'duplicate_reviewed' => '0',
        ]);

        $response->assertRedirect(route('catalog.create'))->assertSessionHasErrors('catalog');
        $this->assertSame(1, CatalogItem::query()->count());
        $this->assertSame(1, CatalogIdCounter::query()->sole()->last_sequence);
        $this->assertSame(0, AuditEvent::query()->count());
    }

    public function test_postgresql_counter_row_lock_serializes_competing_allocations(): void
    {
        $connection = config('database.default');
        $this->assertSame('rakit_test', config("database.connections.{$connection}.database"));

        $family = 'LOCK'.random_int(100000, 999999);
        config([
            'database.connections.phase4_lock_a' => config("database.connections.{$connection}"),
            'database.connections.phase4_lock_b' => config("database.connections.{$connection}"),
        ]);
        $first = DB::connection('phase4_lock_a');
        $second = DB::connection('phase4_lock_b');

        try {
            $first->table('catalog_id_counters')->insert([
                'discipline_code' => 'EL',
                'item_type_code' => 'PKG',
                'group_code' => $family,
                'last_sequence' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $first->beginTransaction();
            $first->table('catalog_id_counters')->where('group_code', $family)->lockForUpdate()->first();
            $second->statement("SET lock_timeout TO '100ms'");

            try {
                $second->table('catalog_id_counters')->where('group_code', $family)->lockForUpdate()->first();
                $this->fail('A competing allocation bypassed the locked counter row.');
            } catch (QueryException) {
                $this->addToAssertionCount(1);
            }
        } finally {
            if ($first->transactionLevel() > 0) {
                $first->rollBack();
            }
            $first->table('catalog_id_counters')->where('group_code', $family)->delete();
            DB::purge('phase4_lock_a');
            DB::purge('phase4_lock_b');
        }
    }

    public function test_metadata_updates_are_audited_without_changing_catalog_identity(): void
    {
        $manager = User::factory()->withRole(UserRole::CatalogManager)->create();
        $catalogItem = $this->catalogItem();
        $identity = $catalogItem->only([
            'catalog_code', 'discipline_code', 'item_type_code', 'catalog_group_id', 'sequence_number',
        ]);

        $response = $this->actingAs($manager)->put(route('catalog.update', $catalogItem), [
            'catalog_category_id' => $catalogItem->catalog_category_id,
            'standard_name' => 'Updated synthetic package',
            'standard_description' => 'Updated synthetic scope',
            'normalized_unit' => 'LS',
            'specifications_json' => '{"voltage":"400 V"}',
            'catalog_code' => 'EL-PKG-KBL-9999',
            'sequence_number' => 9999,
        ]);

        $response->assertRedirect(route('catalog.show', $catalogItem));
        $catalogItem->refresh();
        $this->assertSame($identity, $catalogItem->only(array_keys($identity)));
        $this->assertSame('Updated synthetic package', $catalogItem->standard_name);
        $this->assertSame('updated synthetic package', $catalogItem->normalized_name);
        $this->assertSame(['voltage' => '400 V'], $catalogItem->specifications);
        $this->assertSame(AuditEventName::CatalogItemUpdated, AuditEvent::query()->sole()->event_name);

        $this->assertDatabaseRejects(fn () => DB::table('catalog_items')->where('id', $catalogItem->id)->update([
            'catalog_code' => 'EL-PKG-KBL-9999',
        ]));
    }

    public function test_exact_code_name_and_alias_search_return_catalog_items_without_private_provenance(): void
    {
        $viewer = User::factory()->withRole(UserRole::Viewer)->create();
        $catalogItem = $this->catalogItem(['standard_name' => 'Synthetic distribution panel']);
        $this->alias($catalogItem, $viewer, 'Alternate switchboard wording', 'PRIVATE-SOURCE-REFERENCE');
        (new BaselineCatalogItemSource)->forceFill([
            'source_id' => 'PRIVATE-SOURCE',
            'source_item_id' => 'PRIVATE-LINE',
            'catalog_item_id' => $catalogItem->id,
            'content_hash' => str_repeat('a', 64),
        ])->save();

        $this->actingAs($viewer)->get(route('catalog.index', ['query' => $catalogItem->catalog_code]))
            ->assertOk()->assertSee($catalogItem->standard_name);
        $this->actingAs($viewer)->get(route('catalog.index', ['query' => 'distribution panel']))
            ->assertOk()->assertSee($catalogItem->catalog_code);
        $this->actingAs($viewer)->get(route('catalog.index', ['query' => 'switchboard wording']))
            ->assertOk()->assertSee($catalogItem->catalog_code);
        $this->actingAs($viewer)->get(route('catalog.show', $catalogItem))
            ->assertOk()
            ->assertSee('Alternate switchboard wording')
            ->assertDontSee('PRIVATE-SOURCE-REFERENCE')
            ->assertDontSee('PRIVATE-SOURCE')
            ->assertDontSee('PRIVATE-LINE');
    }

    public function test_lifecycle_changes_are_explicit_audited_and_cannot_set_merged_status(): void
    {
        $manager = User::factory()->withRole(UserRole::CatalogManager)->create();
        $catalogItem = $this->catalogItem();

        $this->actingAs($manager)->post(route('catalog.status', $catalogItem), [
            'status' => CatalogStatus::Deprecated->value,
            'reason' => 'Synthetic lifecycle reason',
        ])->assertRedirect();

        $this->assertSame(CatalogStatus::Deprecated, $catalogItem->fresh()->status);
        $auditEvent = AuditEvent::query()->sole();
        $this->assertSame(['status' => 'ACTIVE'], $auditEvent->before_data);
        $this->assertSame(['status' => 'DEPRECATED'], $auditEvent->after_data);
        $this->assertSame('Synthetic lifecycle reason', $auditEvent->context['reason']);

        $this->actingAs($manager)->post(route('catalog.status', $catalogItem), [
            'status' => CatalogStatus::Merged->value,
            'reason' => 'Invalid merged transition',
        ])->assertSessionHasErrors('status');
        $this->assertSame(CatalogStatus::Deprecated, $catalogItem->fresh()->status);
        $this->assertSame(1, AuditEvent::query()->count());
    }

    public function test_all_documented_non_merged_lifecycle_transitions_are_allowed(): void
    {
        $manager = User::factory()->withRole(UserRole::CatalogManager)->create();
        $transitions = [
            [CatalogStatus::Active, CatalogStatus::Deprecated],
            [CatalogStatus::Active, CatalogStatus::Inactive],
            [CatalogStatus::Deprecated, CatalogStatus::Active],
            [CatalogStatus::Deprecated, CatalogStatus::Inactive],
            [CatalogStatus::Inactive, CatalogStatus::Active],
            [CatalogStatus::Inactive, CatalogStatus::Deprecated],
        ];

        foreach ($transitions as [$sourceStatus, $targetStatus]) {
            $catalogItem = $this->catalogItem(['status' => $sourceStatus]);

            app(ChangeCatalogItemStatus::class)->handle(
                $manager,
                $catalogItem,
                $targetStatus,
                'Synthetic transition matrix verification',
            );

            $this->assertSame($targetStatus, $catalogItem->fresh()->status);
        }

        $this->assertSame(count($transitions), AuditEvent::query()->count());
    }

    public function test_merge_preserves_source_history_aliases_and_points_to_the_active_successor(): void
    {
        $manager = User::factory()->withRole(UserRole::CatalogManager)->create();
        $source = $this->catalogItem([
            'catalog_code' => 'EL-PKG-KBL-0001',
            'sequence_number' => 1,
            'status' => CatalogStatus::Deprecated,
        ]);
        $successor = $this->catalogItem(['catalog_code' => 'EL-PKG-KBL-0002', 'sequence_number' => 2, 'standard_name' => 'Successor synthetic package']);
        $alias = $this->alias($source, $manager, 'Historical synthetic wording', 'SYNTHETIC:MERGE');

        $this->actingAs($manager)->post(route('catalog.merge', $source), [
            'successor_catalog_code' => $successor->catalog_code,
            'reason' => 'Synthetic duplicate identity',
        ])->assertRedirect(route('catalog.show', $source));

        $source->refresh();
        $this->assertSame(CatalogStatus::Merged, $source->status);
        $this->assertTrue($source->successor->is($successor));
        $this->assertSame($source->id, $alias->fresh()->catalog_item_id);
        $this->assertSame(2, CatalogItem::query()->count());
        $auditEvent = AuditEvent::query()->sole();
        $this->assertSame(AuditEventName::CatalogItemsMerged, $auditEvent->event_name);
        $this->assertSame($manager->id, $auditEvent->actor_id);
        $this->assertSame(['status' => 'DEPRECATED', 'successor_id' => null], $auditEvent->before_data);
        $this->assertSame('MERGED', $auditEvent->after_data['status']);
        $this->assertSame($successor->id, $auditEvent->after_data['successor_id']);
        $this->assertSame('Synthetic duplicate identity', $auditEvent->context['reason']);
    }

    public function test_successor_chain_evolution_preserves_each_immutable_historical_pointer(): void
    {
        $manager = User::factory()->withRole(UserRole::CatalogManager)->create();
        $first = $this->catalogItem();
        $second = $this->catalogItem();
        $ultimate = $this->catalogItem();

        app(MergeCatalogItems::class)->handle($manager, $first, $second, 'First synthetic merge');
        app(MergeCatalogItems::class)->handle($manager, $second, $ultimate, 'Successor synthetic merge');

        $this->assertSame($second->id, $first->fresh()->merged_into_catalog_item_id);
        $this->assertSame($ultimate->id, $second->fresh()->merged_into_catalog_item_id);
        $this->assertSame(CatalogStatus::Merged, $first->fresh()->status);
        $this->assertSame(CatalogStatus::Merged, $second->fresh()->status);
        $this->assertSame(CatalogStatus::Active, $ultimate->fresh()->status);
    }

    public function test_self_merge_and_unauthorized_merge_are_rejected_without_mutation_or_audit(): void
    {
        $manager = User::factory()->withRole(UserRole::CatalogManager)->create();
        $viewer = User::factory()->withRole(UserRole::Viewer)->create();
        $source = $this->catalogItem(['catalog_code' => 'EL-PKG-KBL-0001', 'sequence_number' => 1]);
        $successor = $this->catalogItem(['catalog_code' => 'EL-PKG-KBL-0002', 'sequence_number' => 2]);

        $this->actingAs($manager)->post(route('catalog.merge', $source), [
            'successor_catalog_code' => $source->catalog_code,
            'reason' => 'Synthetic self merge',
        ])->assertSessionHasErrors('catalog');

        $this->actingAs($viewer)->post(route('catalog.merge', $source), [
            'successor_catalog_code' => $successor->catalog_code,
            'reason' => 'Unauthorized synthetic merge',
        ])->assertForbidden();

        $this->assertSame(CatalogStatus::Active, $source->fresh()->status);
        $this->assertNull($source->fresh()->merged_into_catalog_item_id);
        $this->assertSame(0, AuditEvent::query()->count());

        $this->expectException(AuthorizationException::class);
        app(MergeCatalogItems::class)->handle($viewer, $source, $successor, 'Unauthorized direct merge');
    }

    public function test_read_only_mode_blocks_all_catalog_mutations_without_partial_writes(): void
    {
        $admin = User::factory()->withRole(UserRole::Admin)->create();
        $manager = User::factory()->withRole(UserRole::CatalogManager)->create();
        [$category, $group] = $this->dimensions();
        $source = $this->catalogItem(['catalog_code' => 'EL-PKG-KBL-0001', 'sequence_number' => 1]);
        $successor = $this->catalogItem(['catalog_code' => 'EL-PKG-KBL-0002', 'sequence_number' => 2]);
        app(ChangeOperationalMode::class)->handle($admin, OperationalMode::ReadOnly, 'Synthetic emergency mode');

        try {
            app(CreateCatalogItem::class)->handle($manager, $this->validActionCreateData($category, $group));
            $this->fail('READ_ONLY allowed Catalog creation.');
        } catch (DomainException) {
            $this->addToAssertionCount(1);
        }

        $this->actingAs($manager)->put(route('catalog.update', $source), [
            'catalog_category_id' => $category->id,
            'standard_name' => 'Blocked update',
            'normalized_unit' => 'LS',
            'specifications_json' => '{}',
        ])->assertSessionHasErrors('catalog');
        $this->actingAs($manager)->post(route('catalog.status', $source), [
            'status' => CatalogStatus::Inactive->value,
            'reason' => 'Blocked lifecycle change',
        ])->assertSessionHasErrors('catalog');
        $this->actingAs($manager)->post(route('catalog.merge', $source), [
            'successor_catalog_code' => $successor->catalog_code,
            'reason' => 'Blocked Catalog merge',
        ])->assertSessionHasErrors('catalog');

        $this->assertSame('Synthetic cable package', $source->fresh()->standard_name);
        $this->assertSame(CatalogStatus::Active, $source->fresh()->status);
        $this->assertSame(2, CatalogItem::query()->count());
        $this->assertSame(0, CatalogIdCounter::query()->count());
        $this->assertSame(1, AuditEvent::query()->count());
        $this->actingAs($manager)->get(route('catalog.index'))->assertOk()->assertSee('READ_ONLY');
    }

    public function test_postgresql_enforces_merge_finality_and_active_successors_without_mutation(): void
    {
        $mergedSource = $this->catalogItem();
        $originalSuccessor = $this->catalogItem();
        $alternateSuccessor = $this->catalogItem();
        DB::table('catalog_items')->where('id', $mergedSource->id)->update([
            'status' => CatalogStatus::Merged->value,
            'merged_into_catalog_item_id' => $originalSuccessor->id,
        ]);

        $this->assertCatalogMutationRejectedWithoutChanges($mergedSource, fn () => DB::table('catalog_items')
            ->where('id', $mergedSource->id)
            ->update([
                'status' => CatalogStatus::Active->value,
                'merged_into_catalog_item_id' => null,
                'updated_at' => now()->addMinute(),
            ]));
        $this->assertCatalogMutationRejectedWithoutChanges($mergedSource, fn () => DB::table('catalog_items')
            ->where('id', $mergedSource->id)
            ->update([
                'merged_into_catalog_item_id' => $alternateSuccessor->id,
                'updated_at' => now()->addMinute(),
            ]));

        foreach ([CatalogStatus::Deprecated, CatalogStatus::Inactive] as $ineligibleStatus) {
            $source = $this->catalogItem();
            $ineligibleSuccessor = $this->catalogItem(['status' => $ineligibleStatus]);

            $this->assertCatalogMutationRejectedWithoutChanges($source, fn () => DB::table('catalog_items')
                ->where('id', $source->id)
                ->update([
                    'status' => CatalogStatus::Merged->value,
                    'merged_into_catalog_item_id' => $ineligibleSuccessor->id,
                    'updated_at' => now()->addMinute(),
                ]));
        }

        $sourceForMergedSuccessor = $this->catalogItem();
        $mergedSuccessor = $this->catalogItem();
        $ultimateSuccessor = $this->catalogItem();
        DB::table('catalog_items')->where('id', $mergedSuccessor->id)->update([
            'status' => CatalogStatus::Merged->value,
            'merged_into_catalog_item_id' => $ultimateSuccessor->id,
        ]);
        $this->assertCatalogMutationRejectedWithoutChanges($sourceForMergedSuccessor, fn () => DB::table('catalog_items')
            ->where('id', $sourceForMergedSuccessor->id)
            ->update([
                'status' => CatalogStatus::Merged->value,
                'merged_into_catalog_item_id' => $mergedSuccessor->id,
                'updated_at' => now()->addMinute(),
            ]));

        $cycleSource = $this->catalogItem();
        $cycleSuccessor = $this->catalogItem();
        DB::table('catalog_items')->where('id', $cycleSource->id)->update([
            'status' => CatalogStatus::Merged->value,
            'merged_into_catalog_item_id' => $cycleSuccessor->id,
        ]);
        $this->assertCatalogMutationRejectedWithoutChanges($cycleSuccessor, fn () => DB::table('catalog_items')
            ->where('id', $cycleSuccessor->id)
            ->update([
                'status' => CatalogStatus::Merged->value,
                'merged_into_catalog_item_id' => $cycleSource->id,
                'updated_at' => now()->addMinute(),
            ]));
    }

    public function test_specification_json_rejects_scalars_and_nonempty_arrays_over_http(): void
    {
        $manager = User::factory()->withRole(UserRole::CatalogManager)->create();
        [$category, $group] = $this->dimensions();

        foreach (['"scalar"', '42', 'true', 'null', '[{"rating":"synthetic"}]'] as $invalidSpecifications) {
            $this->actingAs($manager)->post(route('catalog.store'), [
                ...$this->validCreateData($category, $group),
                'specifications_json' => $invalidSpecifications,
            ])->assertSessionHasErrors('specifications_json');
        }

        $this->assertSame(0, CatalogItem::query()->count());
        $this->assertSame(0, CatalogIdCounter::query()->count());
        $this->assertSame(0, AuditEvent::query()->count());
    }

    public function test_valid_object_and_legacy_empty_array_specifications_render_safely(): void
    {
        $manager = User::factory()->withRole(UserRole::CatalogManager)->create();
        [$category, $group] = $this->dimensions();

        $this->actingAs($manager)->post(route('catalog.store'), [
            ...$this->validCreateData($category, $group),
            'specifications_json' => '{"rating":"synthetic","details":{"phase":3}}',
        ])->assertRedirect();

        $catalogItem = CatalogItem::query()->sole();
        $this->actingAs($manager)->get(route('catalog.show', $catalogItem))
            ->assertOk()
            ->assertSee('Rating')
            ->assertSee('synthetic');

        $this->actingAs($manager)->put(route('catalog.update', $catalogItem), [
            'catalog_category_id' => $category->id,
            'standard_name' => $catalogItem->standard_name,
            'standard_description' => $catalogItem->standard_description,
            'normalized_unit' => $catalogItem->normalized_unit,
            'specifications_json' => '[]',
        ])->assertRedirect(route('catalog.show', $catalogItem));

        $this->assertSame([], $catalogItem->fresh()->specifications);
        $this->actingAs($manager)->get(route('catalog.show', $catalogItem))
            ->assertOk()
            ->assertSee('No specifications recorded.');
    }

    public function test_postgresql_rejects_scalar_and_nonempty_array_specifications_without_mutation(): void
    {
        $catalogItem = $this->catalogItem();

        foreach ([json_encode('scalar', JSON_THROW_ON_ERROR), json_encode(42, JSON_THROW_ON_ERROR), json_encode([['rating' => 'synthetic']], JSON_THROW_ON_ERROR)] as $invalidSpecifications) {
            $this->assertCatalogMutationRejectedWithoutChanges($catalogItem, fn () => DB::table('catalog_items')
                ->where('id', $catalogItem->id)
                ->update([
                    'specifications' => $invalidSpecifications,
                    'updated_at' => now()->addMinute(),
                ]));
        }

        $this->assertSame([], $catalogItem->fresh()->specifications);
    }

    public function test_duplicate_candidate_input_is_trimmed_bounded_and_never_treats_arrays_as_text(): void
    {
        $manager = User::factory()->withRole(UserRole::CatalogManager)->create();
        $this->catalogItem(['standard_name' => 'Synthetic candidate package']);

        $this->actingAs($manager)->get(route('catalog.create', ['candidate_name' => ['malformed']]))
            ->assertSessionHasErrors('candidate_name');
        $this->actingAs($manager)->get(route('catalog.create', ['candidate_name' => str_repeat('x', 1001)]))
            ->assertSessionHasErrors('candidate_name');

        $executedQueries = [];
        DB::listen(function ($query) use (&$executedQueries): void {
            $executedQueries[] = $query->sql;
        });

        $this->actingAs($manager)->get(route('catalog.create', ['candidate_name' => '  ab  ']))
            ->assertOk()
            ->assertSee('value="ab"', false);

        $this->assertFalse(collect($executedQueries)->contains(
            fn (string $sql): bool => str_contains($sql, 'similarity('),
        ));
    }

    public function test_phase_four_postgresql_trigram_indexes_exist(): void
    {
        $indexes = DB::table('pg_indexes')
            ->where('schemaname', 'public')
            ->whereIn('indexname', [
                'catalog_items_normalized_name_trgm_index',
                'catalog_aliases_normalized_description_trgm_index',
            ])
            ->pluck('indexdef', 'indexname');

        $this->assertCount(2, $indexes);
        $this->assertStringContainsString('gin_trgm_ops', $indexes['catalog_items_normalized_name_trgm_index']);
        $this->assertStringContainsString('gin_trgm_ops', $indexes['catalog_aliases_normalized_description_trgm_index']);

        $mergeFunction = DB::table('pg_proc')->where('proname', 'enforce_catalog_merge_acyclic')->value(DB::raw('pg_get_functiondef(oid)'));
        $this->assertIsString($mergeFunction);
        $this->assertStringContainsString('FOR SHARE', $mergeFunction);
        $this->assertStringNotContainsString('FOR KEY SHARE', $mergeFunction);
        $this->assertDatabaseHas('pg_constraint', ['conname' => 'catalog_items_specifications_shape_check']);
    }

    /** @return array{CatalogCategory, CatalogGroup} */
    private function dimensions(): array
    {
        $category = CatalogCategory::query()->where('code', 'ELEC')->first();
        if ($category === null) {
            $category = new CatalogCategory;
            $category->forceFill(['code' => 'ELEC', 'name' => 'Synthetic electrical'])->save();
        }
        $group = CatalogGroup::query()->where('code', 'KBL')->first();
        if ($group === null) {
            $group = new CatalogGroup;
            $group->forceFill(['code' => 'KBL', 'name' => 'Synthetic cable'])->save();
        }

        return [$category, $group];
    }

    /** @param array<string, mixed> $overrides */
    private function catalogItem(array $overrides = []): CatalogItem
    {
        [$category, $group] = $this->dimensions();
        $sequence = $overrides['sequence_number'] ?? CatalogItem::query()->count() + 1;
        $standardName = $overrides['standard_name'] ?? 'Synthetic cable package';
        $catalogItem = new CatalogItem;
        $catalogItem->forceFill([
            'catalog_code' => $overrides['catalog_code'] ?? 'EL-PKG-KBL-'.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT),
            'discipline_code' => 'EL',
            'item_type_code' => 'PKG',
            'catalog_category_id' => $category->id,
            'catalog_group_id' => $group->id,
            'sequence_number' => $sequence,
            'standard_name' => $standardName,
            'normalized_name' => str($standardName)->squish()->lower()->toString(),
            'standard_description' => 'Synthetic scope',
            'normalized_unit' => 'ls',
            'specifications' => [],
            'status' => $overrides['status'] ?? CatalogStatus::Active,
            'approved_at' => now(),
        ])->save();

        return $catalogItem;
    }

    private function alias(CatalogItem $catalogItem, User $approver, string $description, string $reference): CatalogAlias
    {
        $alias = new CatalogAlias;
        $alias->forceFill([
            'catalog_item_id' => $catalogItem->id,
            'raw_description' => $description,
            'normalized_description' => str($description)->squish()->lower()->toString(),
            'source_type' => 'BASELINE_IMPORT',
            'source_reference' => $reference,
            'approved_by' => $approver->id,
            'approved_at' => now(),
        ])->save();

        return $alias;
    }

    /** @return array<string, mixed> */
    private function validCreateData(CatalogCategory $category, CatalogGroup $group): array
    {
        return [
            'discipline_code' => 'EL',
            'item_type_code' => 'PKG',
            'catalog_category_id' => $category->id,
            'catalog_group_id' => $group->id,
            'standard_name' => 'Managed synthetic package',
            'standard_description' => 'Synthetic managed scope',
            'normalized_unit' => 'LS',
            'specifications_json' => '{"rating":"synthetic"}',
            'duplicate_reviewed' => '1',
        ];
    }

    /** @return array<string, mixed> */
    private function validActionCreateData(CatalogCategory $category, CatalogGroup $group): array
    {
        return [
            'discipline_code' => 'EL',
            'item_type_code' => 'PKG',
            'catalog_category_id' => $category->id,
            'catalog_group_id' => $group->id,
            'standard_name' => 'Managed synthetic package',
            'standard_description' => 'Synthetic managed scope',
            'normalized_unit' => 'LS',
            'specifications' => ['rating' => 'synthetic'],
            'duplicate_reviewed' => true,
        ];
    }

    private function assertDatabaseRejects(Closure $operation): void
    {
        try {
            DB::transaction($operation);
            $this->fail('PostgreSQL accepted an invalid Catalog mutation.');
        } catch (QueryException) {
            $this->addToAssertionCount(1);
        }
    }

    private function assertCatalogMutationRejectedWithoutChanges(CatalogItem $catalogItem, Closure $operation): void
    {
        $before = DB::table('catalog_items')->where('id', $catalogItem->id)->first([
            'status',
            'merged_into_catalog_item_id',
            'specifications',
            'updated_at',
        ]);

        $this->assertDatabaseRejects($operation);

        $after = DB::table('catalog_items')->where('id', $catalogItem->id)->first([
            'status',
            'merged_into_catalog_item_id',
            'specifications',
            'updated_at',
        ]);
        $this->assertEquals($before, $after);
    }
}
