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
            $table->foreignId('customer_id')->nullable()->after('created_by')->constrained('customers')->nullOnDelete();
        });

        $this->backfillCustomers();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customer_orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('customer_id');
        });
    }

    /**
     * Create one customer per distinct email (or name + phone when the email is empty) and link their orders.
     */
    private function backfillCustomers(): void
    {
        $customerIds = [];

        DB::table('customer_orders')->orderBy('id')->each(function (object $order) use (&$customerIds): void {
            $email = filled($order->customer_email) ? strtolower(trim($order->customer_email)) : null;
            $key = $email ?? 'name:'.strtolower(trim($order->customer_name)).'|phone:'.trim((string) $order->customer_phone);

            $customerIds[$key] ??= DB::table('customers')->insertGetId([
                'name' => $order->customer_name,
                'email' => $email,
                'phone' => filled($order->customer_phone) ? $order->customer_phone : null,
                'created_by' => $order->created_by,
                'created_at' => $order->created_at ?? now(),
                'updated_at' => $order->updated_at ?? now(),
            ]);

            DB::table('customer_orders')->where('id', $order->id)->update(['customer_id' => $customerIds[$key]]);
        });
    }
};
