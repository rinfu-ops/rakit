<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('catalog_aliases', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('catalog_item_id')->constrained()->restrictOnDelete();
            $table->text('raw_description');
            $table->text('normalized_description');
            $table->string('source_type', 30);
            $table->string('source_reference', 191);
            $table->foreignId('approved_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('approved_at');
            $table->timestamps();
            $table->unique(['source_type', 'source_reference']);
        });

        DB::statement("ALTER TABLE catalog_aliases ADD CONSTRAINT catalog_aliases_source_type_check CHECK (source_type = 'BASELINE_IMPORT')");
    }

    public function down(): void
    {
        Schema::dropIfExists('catalog_aliases');
    }
};
