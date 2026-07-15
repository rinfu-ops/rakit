<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
            ALTER TABLE catalog_items
            ADD CONSTRAINT catalog_items_specifications_shape_check
            CHECK (
                CASE jsonb_typeof(specifications)
                    WHEN 'object' THEN true
                    WHEN 'array' THEN jsonb_array_length(specifications) = 0
                    ELSE false
                END
            )
            SQL);

        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION enforce_catalog_merge_acyclic()
            RETURNS trigger
            LANGUAGE plpgsql
            AS $$
            DECLARE
                creates_cycle boolean;
                successor_status text;
            BEGIN
                IF TG_OP = 'UPDATE' AND OLD.status = 'MERGED' THEN
                    IF NEW.status IS DISTINCT FROM OLD.status
                        OR NEW.merged_into_catalog_item_id IS DISTINCT FROM OLD.merged_into_catalog_item_id THEN
                        RAISE EXCEPTION 'A merged Catalog Item and its successor are immutable';
                    END IF;

                    RETURN NEW;
                END IF;

                IF NEW.merged_into_catalog_item_id IS NULL THEN
                    RETURN NEW;
                END IF;

                IF NEW.merged_into_catalog_item_id = NEW.id THEN
                    RAISE EXCEPTION 'A Catalog Item cannot be merged into itself';
                END IF;

                SELECT status
                INTO successor_status
                FROM catalog_items
                WHERE id = NEW.merged_into_catalog_item_id
                FOR KEY SHARE;

                IF NOT FOUND THEN
                    RAISE EXCEPTION 'The Catalog merge successor does not exist';
                END IF;

                IF successor_status <> 'ACTIVE' THEN
                    RAISE EXCEPTION 'The Catalog merge successor must be ACTIVE';
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
            SQL);
    }

    public function down(): void
    {
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
            SQL);

        DB::statement('ALTER TABLE catalog_items DROP CONSTRAINT IF EXISTS catalog_items_specifications_shape_check');
    }
};
