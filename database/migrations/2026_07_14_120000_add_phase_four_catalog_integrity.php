<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE INDEX catalog_items_normalized_name_trgm_index ON catalog_items USING gin (normalized_name gin_trgm_ops)');
        DB::statement('CREATE INDEX catalog_aliases_normalized_description_trgm_index ON catalog_aliases USING gin (normalized_description gin_trgm_ops)');
        DB::statement('CREATE INDEX catalog_items_status_category_group_index ON catalog_items (status, catalog_category_id, catalog_group_id)');
        DB::statement("ALTER TABLE catalog_items ADD CONSTRAINT catalog_items_merge_state_check CHECK ((status = 'MERGED') = (merged_into_catalog_item_id IS NOT NULL))");

        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION enforce_catalog_merge_acyclic()
            RETURNS trigger
            LANGUAGE plpgsql
            AS $$
            DECLARE
                creates_cycle boolean;
            BEGIN
                IF NEW.merged_into_catalog_item_id IS NULL THEN
                    RETURN NEW;
                END IF;

                WITH RECURSIVE successors AS (
                    SELECT id, merged_into_catalog_item_id
                    FROM catalog_items
                    WHERE id = NEW.merged_into_catalog_item_id

                    UNION ALL

                    SELECT item.id, item.merged_into_catalog_item_id
                    FROM catalog_items item
                    INNER JOIN successors ON item.id = successors.merged_into_catalog_item_id
                )
                SELECT EXISTS (SELECT 1 FROM successors WHERE id = NEW.id)
                INTO creates_cycle;

                IF creates_cycle THEN
                    RAISE EXCEPTION 'Catalog merge cycles are forbidden';
                END IF;

                RETURN NEW;
            END;
            $$;

            CREATE TRIGGER catalog_items_merge_acyclic_guard
            BEFORE INSERT OR UPDATE OF status, merged_into_catalog_item_id ON catalog_items
            FOR EACH ROW
            EXECUTE FUNCTION enforce_catalog_merge_acyclic();
            SQL);
    }

    public function down(): void
    {
        DB::statement('DROP TRIGGER IF EXISTS catalog_items_merge_acyclic_guard ON catalog_items');
        DB::statement('DROP FUNCTION IF EXISTS enforce_catalog_merge_acyclic()');
        DB::statement('ALTER TABLE catalog_items DROP CONSTRAINT IF EXISTS catalog_items_merge_state_check');
        DB::statement('DROP INDEX IF EXISTS catalog_items_status_category_group_index');
        DB::statement('DROP INDEX IF EXISTS catalog_aliases_normalized_description_trgm_index');
        DB::statement('DROP INDEX IF EXISTS catalog_items_normalized_name_trgm_index');
    }
};
