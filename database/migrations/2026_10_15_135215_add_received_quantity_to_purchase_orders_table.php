<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * How many flowers arrived when the delivery was received. The batch's own quantity keeps
     * changing as flowers are sold or wasted, so it can't be used to tell if a delivery was short.
     */
    public function up(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->unsignedInteger('received_quantity')->nullable()->after('fulfilled_at');
        });

        // Deliveries received before this column existed: rebuild the arrival amount from the batch's
        // current stock plus everything taken out of it since (sold, wasted, or returned).
        DB::table('inventory_items')->whereNotNull('purchase_order_id')->get(['id', 'purchase_order_id', 'quantity'])
            ->each(function (object $batch): void {
                $movedSinceArrival = (int) DB::table('stock_adjustments')->where('inventory_item_id', $batch->id)->sum('quantity_change');

                DB::table('purchase_orders')->where('id', $batch->purchase_order_id)
                    ->update(['received_quantity' => max(0, $batch->quantity - $movedSinceArrival)]);
            });
    }

    public function down(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dropColumn('received_quantity');
        });
    }
};
