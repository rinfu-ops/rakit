<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('system_operational_modes', function (Blueprint $table): void {
            $table->unsignedTinyInteger('id')->primary();
            $table->string('mode')->default('NORMAL');
            $table->text('reason')->nullable();
            $table->foreignId('changed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('changed_at')->nullable();
            $table->timestamps();
        });

        DB::statement('ALTER TABLE system_operational_modes ADD CONSTRAINT system_operational_modes_singleton_check CHECK (id = 1)');
        DB::statement("ALTER TABLE system_operational_modes ADD CONSTRAINT system_operational_modes_mode_check CHECK (mode IN ('NORMAL', 'READ_ONLY'))");
        DB::statement("INSERT INTO system_operational_modes (id, mode, created_at, updated_at) VALUES (1, 'NORMAL', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)");
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION prevent_system_operational_mode_deletion()
            RETURNS trigger
            LANGUAGE plpgsql
            AS $$
            BEGIN
                RAISE EXCEPTION 'The system operational-mode singleton cannot be deleted';
            END;
            $$;

            CREATE TRIGGER system_operational_modes_prevent_delete
            BEFORE DELETE ON system_operational_modes
            FOR EACH ROW
            EXECUTE FUNCTION prevent_system_operational_mode_deletion();
            SQL);
    }

    public function down(): void
    {
        DB::statement('DROP TRIGGER IF EXISTS system_operational_modes_prevent_delete ON system_operational_modes');
        Schema::dropIfExists('system_operational_modes');
        DB::statement('DROP FUNCTION IF EXISTS prevent_system_operational_mode_deletion()');
    }
};
