<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Aumenta el tamaño de session_token para tokens de Izipay
     */
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            // Eliminar el índice existente si lo hay
            $table->dropIndex(['session_token']);
        });

        Schema::table('payments', function (Blueprint $table) {
            // Cambiar a TEXT para soportar tokens largos de Izipay
            $table->text('session_token')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->string('session_token', 500)->nullable()->change();
            $table->index(['session_token']);
        });
    }
};
