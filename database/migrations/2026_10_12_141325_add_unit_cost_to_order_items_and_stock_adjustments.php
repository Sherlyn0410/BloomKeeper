<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->decimal('unit_cost', 8, 2)->nullable()->after('unit_price');
        });

        Schema::table('stock_adjustments', function (Blueprint $table) {
            $table->decimal('unit_cost', 8, 2)->nullable()->after('quantity_change');
        });

        $this->backfillCosts();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn('unit_cost');
        });

        Schema::table('stock_adjustments', function (Blueprint $table) {
            $table->dropColumn('unit_cost');
        });
    }

    /**
     * Existing rows start with today's inventory cost price, the best figure available for past records.
     */
    private function backfillCosts(): void
    {
        $costByFlower = DB::table('inventory_items')->orderBy('date_received')->pluck('cost_price', 'flower_type');

        foreach ($costByFlower as $flowerType => $costPrice) {
            DB::table('order_items')->where('flower_type', $flowerType)->whereNull('unit_cost')->update(['unit_cost' => $costPrice]);
        }

        DB::table('inventory_items')->select('id', 'cost_price')->orderBy('id')->each(function (object $item): void {
            DB::table('stock_adjustments')->where('inventory_item_id', $item->id)->whereNull('unit_cost')->update(['unit_cost' => $item->cost_price]);
        });
    }
};
