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
        Schema::table('customer_orders', function (Blueprint $table) {
            $table->string('order_number')->nullable()->unique()->after('id');
            $table->time('collection_time')->nullable()->after('collection_date');
            $table->text('notes')->nullable()->after('collection_time');
            $table->string('payment_status')->default('unpaid')->after('status')->index();
        });

        $this->backfillOrderNumbers();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customer_orders', function (Blueprint $table) {
            $table->dropUnique(['order_number']);
            $table->dropIndex(['payment_status']);
            $table->dropColumn(['order_number', 'collection_time', 'notes', 'payment_status']);
        });
    }

    /**
     * Give existing orders sequential numbers per year, e.g. BK-2026-0001.
     */
    private function backfillOrderNumbers(): void
    {
        $sequences = [];

        DB::table('customer_orders')->orderBy('id')->each(function (object $order) use (&$sequences): void {
            $year = $order->created_at ? substr((string) $order->created_at, 0, 4) : now()->format('Y');
            $sequences[$year] = ($sequences[$year] ?? 0) + 1;

            DB::table('customer_orders')->where('id', $order->id)->update([
                'order_number' => sprintf('BK-%s-%04d', $year, $sequences[$year]),
            ]);
        });
    }
};
