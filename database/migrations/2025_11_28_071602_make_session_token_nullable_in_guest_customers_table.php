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
        Schema::table('guest_customers', function (Blueprint $table) {
            // Hacer session_token nullable y quitar el índice único
            $table->string('session_token', 64)->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('guest_customers', function (Blueprint $table) {
            $table->string('session_token', 64)->nullable(false)->change();
        });
    }
};
