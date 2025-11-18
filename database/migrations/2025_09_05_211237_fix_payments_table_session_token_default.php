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
        Schema::table('payments', function (Blueprint $table) {
            // Make session_token nullable with a proper default
            $table->text('session_token')->nullable()->default(null)->change();

            // Also ensure status has a proper default
            $table->string('status')->default('pending')->change();

            // Ensure metadata is nullable
            $table->json('metadata')->nullable()->default(null)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            // Revert changes if needed
            $table->text('session_token')->nullable()->change();
            $table->string('status')->change();
            $table->json('metadata')->nullable()->change();
        });
    }
};
