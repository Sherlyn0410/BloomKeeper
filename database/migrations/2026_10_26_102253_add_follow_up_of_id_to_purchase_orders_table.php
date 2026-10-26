<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Link a "Re-order missing" purchase order to the short delivery it covers.
     * Re-orders made before this column existed are matched through the note they were created with.
     */
    public function up(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->foreignId('follow_up_of_id')->nullable()->after('received_quantity')->constrained('purchase_orders')->nullOnDelete();
        });

        DB::table('purchase_orders')->where('notes', 'like', 'Follow-up to PO-%')->get(['id', 'notes'])
            ->each(function (object $purchaseOrder): void {
                if (preg_match('/^Follow-up to PO-0*(\d+)/', $purchaseOrder->notes, $matches)
                    && DB::table('purchase_orders')->where('id', (int) $matches[1])->exists()) {
                    DB::table('purchase_orders')->where('id', $purchaseOrder->id)->update(['follow_up_of_id' => (int) $matches[1]]);
                }
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('follow_up_of_id');
        });
    }
};
