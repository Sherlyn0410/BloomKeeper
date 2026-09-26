<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('users')->where('role', 'florist')->update(['role' => 'staff']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Role normalization is intentionally irreversible because staff records may already use this value.
    }
};
