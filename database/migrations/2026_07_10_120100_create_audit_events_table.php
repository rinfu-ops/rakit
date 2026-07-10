<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('actor_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('event_name')->index();
            $table->string('subject_type');
            $table->string('subject_id');
            $table->jsonb('before_data')->nullable();
            $table->jsonb('after_data')->nullable();
            $table->jsonb('context')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['subject_type', 'subject_id']);
        });

        DB::statement("ALTER TABLE audit_events ADD CONSTRAINT audit_events_event_name_check CHECK (event_name IN ('CATALOG_ITEM_CREATED', 'CATALOG_ITEM_UPDATED', 'CATALOG_ITEMS_MERGED', 'IMPORT_MAPPING_APPROVED', 'IMPORT_FINALIZED', 'RAP_SUBMITTED', 'RAP_RETURNED', 'RAP_APPROVED', 'RAP_FINALIZED', 'RAP_REVISION_CREATED', 'PRICE_OBSERVATION_VOIDED', 'TEMPLATE_VERSION_CREATED', 'TEMPLATE_ACTIVATED', 'SYSTEM_OPERATIONAL_MODE_CHANGED'))");
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION prevent_audit_event_mutation()
            RETURNS trigger
            LANGUAGE plpgsql
            AS $$
            BEGIN
                RAISE EXCEPTION 'Audit Events are append-only';
            END;
            $$;

            CREATE TRIGGER audit_events_append_only
            BEFORE UPDATE OR DELETE ON audit_events
            FOR EACH ROW
            EXECUTE FUNCTION prevent_audit_event_mutation();
            SQL);
    }

    public function down(): void
    {
        DB::statement('DROP TRIGGER IF EXISTS audit_events_append_only ON audit_events');
        Schema::dropIfExists('audit_events');
        DB::statement('DROP FUNCTION IF EXISTS prevent_audit_event_mutation()');
    }
};
