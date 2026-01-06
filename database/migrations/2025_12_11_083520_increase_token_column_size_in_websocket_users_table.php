<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('websocket_users', function (Blueprint $table) {
            $table->text('token')->change(); // ← TEXT = unlimited length
        });
    }

    public function down(): void
    {
        Schema::table('websocket_users', function (Blueprint $table) {
            $table->string('token', 255)->change();
        });
    }
};