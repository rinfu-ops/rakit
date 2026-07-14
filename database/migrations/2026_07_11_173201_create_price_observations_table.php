<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('price_observations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('catalog_item_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('unit_price_rupiah');
            $table->decimal('quantity', 20, 4)->nullable();
            $table->string('normalized_unit', 100);
            $table->char('currency_code', 3)->default('IDR');
            $table->string('price_basis', 30);
            $table->string('tax_context', 20);
            $table->date('observed_at');
            $table->string('source_type', 30);
            $table->string('source_id', 64);
            $table->string('source_line_id', 100);
            $table->boolean('guidance_eligible')->default(true);
            $table->timestamp('voided_at')->nullable();
            $table->foreignId('voided_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->text('void_reason')->nullable();
            $table->timestamps();
            $table->unique(['source_type', 'source_id', 'source_line_id', 'catalog_item_id'], 'price_observations_source_unique');
        });

        DB::statement('ALTER TABLE price_observations ADD CONSTRAINT price_observations_amount_check CHECK (unit_price_rupiah >= 0)');
        DB::statement('ALTER TABLE price_observations ADD CONSTRAINT price_observations_quantity_check CHECK (quantity IS NULL OR quantity >= 0)');
        DB::statement("ALTER TABLE price_observations ADD CONSTRAINT price_observations_currency_check CHECK (currency_code = 'IDR')");
        DB::statement("ALTER TABLE price_observations ADD CONSTRAINT price_observations_basis_check CHECK (price_basis IN ('RAP_COST', 'SELLING_PRICE', 'VENDOR_QUOTE', 'BUDGET_ESTIMATE'))");
        DB::statement("ALTER TABLE price_observations ADD CONSTRAINT price_observations_tax_check CHECK (tax_context IN ('EXCLUSIVE', 'INCLUSIVE', 'UNKNOWN'))");
        DB::statement("ALTER TABLE price_observations ADD CONSTRAINT price_observations_source_check CHECK (source_type = 'BASELINE_IMPORT')");
    }

    public function down(): void
    {
        Schema::dropIfExists('price_observations');
    }
};
