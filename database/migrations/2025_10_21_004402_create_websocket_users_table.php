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
        Schema::create('websocket_users', function (Blueprint $table) {
            $table->id();
            $table->string('user_id')->unique(); // External user ID (e.g., from auth server)
            $table->string('token'); // JWT or API token
            $table->timestamp('expires_at'); // Token expiration
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('websocket_users');
    }
};
