<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Databases created before the users migration included remember_token are missing the column,
     * which breaks "Remember me" and password resets. Fresh installs already have it, so this is skipped there.
     */
    public function up(): void
    {
        if (Schema::hasColumn('users', 'remember_token')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->rememberToken()->after('password');
        });
    }

    /**
     * The column belongs to the original users table, so it is left in place on rollback.
     */
    public function down(): void
    {
        //
    }
};
