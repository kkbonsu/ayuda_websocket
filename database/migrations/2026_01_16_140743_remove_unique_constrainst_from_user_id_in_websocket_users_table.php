<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('websocket_users', function (Blueprint $table) {
            // Drop the unique index on user_id
            $table->dropUnique(['user_id']);

            $table->index('user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('websocket_users', function (Blueprint $table) {
            // Drop the regular index
            $table->dropIndex(['user_id']);
            
            // Restore the unique constraint
            $table->unique('user_id');
        });
    }
};
