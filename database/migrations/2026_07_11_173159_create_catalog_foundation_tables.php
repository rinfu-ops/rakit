<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('catalog_categories', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('catalog_groups', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('catalog_id_counters', function (Blueprint $table): void {
            $table->id();
            $table->string('discipline_code', 20);
            $table->string('item_type_code', 20);
            $table->string('group_code', 20);
            $table->unsignedBigInteger('last_sequence')->default(0);
            $table->timestamps();
            $table->unique(['discipline_code', 'item_type_code', 'group_code']);
        });

        Schema::create('catalog_items', function (Blueprint $table): void {
            $table->id();
            $table->string('catalog_code')->unique();
            $table->string('discipline_code', 20);
            $table->string('item_type_code', 20);
            $table->foreignId('catalog_category_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('catalog_group_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('sequence_number');
            $table->text('standard_name');
            $table->text('normalized_name');
            $table->text('standard_description')->nullable();
            $table->string('normalized_unit', 100);
            $table->jsonb('specifications')->default('{}');
            $table->string('status', 20)->default('ACTIVE');
            $table->foreignId('merged_into_catalog_item_id')->nullable()->constrained('catalog_items')->restrictOnDelete();
            $table->timestamp('approved_at');
            $table->timestamps();
            $table->unique(['discipline_code', 'item_type_code', 'catalog_group_id', 'sequence_number'], 'catalog_items_family_sequence_unique');
        });

        Schema::create('baseline_catalog_item_sources', function (Blueprint $table): void {
            $table->id();
            $table->string('source_id', 64);
            $table->string('source_item_id', 100);
            $table->foreignId('catalog_item_id')->constrained()->restrictOnDelete();
            $table->string('content_hash', 64);
            $table->timestamps();
            $table->unique(['source_id', 'source_item_id']);
        });

        DB::statement('ALTER TABLE catalog_id_counters ADD CONSTRAINT catalog_id_counters_sequence_check CHECK (last_sequence >= 0)');
        DB::statement('ALTER TABLE catalog_items ADD CONSTRAINT catalog_items_sequence_check CHECK (sequence_number > 0)');
        DB::statement("ALTER TABLE catalog_items ADD CONSTRAINT catalog_items_code_format_check CHECK (catalog_code ~ '^[A-Z0-9]{2,20}-[A-Z0-9]{2,20}-[A-Z0-9]{2,20}-[0-9]{4,}$')");
        DB::statement("ALTER TABLE catalog_items ADD CONSTRAINT catalog_items_status_check CHECK (status IN ('ACTIVE', 'DEPRECATED', 'MERGED', 'INACTIVE'))");
        DB::statement('ALTER TABLE catalog_items ADD CONSTRAINT catalog_items_merge_not_self_check CHECK (merged_into_catalog_item_id IS NULL OR merged_into_catalog_item_id <> id)');
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION protect_catalog_item_identity()
            RETURNS trigger LANGUAGE plpgsql AS $$
            BEGIN
                IF TG_OP = 'DELETE' THEN
                    RAISE EXCEPTION 'Catalog Items cannot be deleted';
                END IF;
                IF NEW.catalog_code IS DISTINCT FROM OLD.catalog_code
                    OR NEW.discipline_code IS DISTINCT FROM OLD.discipline_code
                    OR NEW.item_type_code IS DISTINCT FROM OLD.item_type_code
                    OR NEW.catalog_group_id IS DISTINCT FROM OLD.catalog_group_id
                    OR NEW.sequence_number IS DISTINCT FROM OLD.sequence_number THEN
                    RAISE EXCEPTION 'Catalog identity is immutable';
                END IF;
                RETURN NEW;
            END;
            $$;

            CREATE TRIGGER catalog_items_identity_guard
            BEFORE UPDATE OR DELETE ON catalog_items
            FOR EACH ROW EXECUTE FUNCTION protect_catalog_item_identity();
            SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('baseline_catalog_item_sources');
        DB::statement('DROP TRIGGER IF EXISTS catalog_items_identity_guard ON catalog_items');
        Schema::dropIfExists('catalog_items');
        DB::statement('DROP FUNCTION IF EXISTS protect_catalog_item_identity()');
        Schema::dropIfExists('catalog_id_counters');
        Schema::dropIfExists('catalog_groups');
        Schema::dropIfExists('catalog_categories');
    }
};
