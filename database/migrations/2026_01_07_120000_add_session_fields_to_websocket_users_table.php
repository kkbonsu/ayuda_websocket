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
            $table->string('session_id')->after('token');
            $table->string('type')->nullable()->after('session_id');
            $table->string('name')->nullable()->after('type');
            $table->index(['token', 'session_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('websocket_users', function (Blueprint $table) {
            $table->dropIndex(['token', 'session_id']);
            $table->dropColumn(['session_id', 'type', 'name']);
        });
    }
};

