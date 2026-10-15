<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Flowers sent back to a supplier because of a problem. The stock movement itself lives in
     * stock_adjustments (type "return", or "wastage" once the supplier rejects it).
     */
    public function up(): void
    {
        Schema::create('stock_returns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventory_item_id')->constrained();
            $table->foreignId('supplier_id')->constrained('users');
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('stock_adjustment_id')->nullable()->constrained()->nullOnDelete();
            $table->string('flower_type');
            $table->unsignedInteger('quantity');
            $table->decimal('unit_cost', 8, 2)->nullable();
            $table->string('reason');
            $table->text('notes')->nullable();
            $table->string('status')->default('requested')->index();
            $table->text('supplier_note')->nullable();
            $table->timestamp('responded_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_returns');
    }
};
