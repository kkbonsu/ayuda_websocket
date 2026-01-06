<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_notifications', function (Blueprint $table) {
            // Drop existing FK if it exists
            $table->dropForeign(['user_id']);

            // Change user_id to string for UUID support
            $table->string('user_id', 36)->change(); // UUID length
        });
    }

    public function down(): void
    {
        Schema::table('user_notifications', function (Blueprint $table) {
            // Restore FK if needed (adjust to your original)
            $table->unsignedBigInteger('user_id')->change();
            $table->foreign('user_id')->references('id')->on('websocket_users')->onDelete('cascade');
        });
    }
};