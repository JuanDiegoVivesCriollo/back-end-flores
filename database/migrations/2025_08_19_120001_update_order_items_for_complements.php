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
        Schema::table('order_items', function (Blueprint $table) {
            // Hacer flower_id nullable para permitir complementos
            $table->unsignedBigInteger('flower_id')->nullable()->change();

            // Agregar referencia a complementos
            $table->foreignId('complement_id')->nullable()->constrained('complements')->onDelete('cascade');

            // Agregar tipo de item para distinguir entre flores y complementos
            $table->enum('item_type', ['flower', 'complement'])->default('flower');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropForeign(['complement_id']);
            $table->dropColumn(['complement_id', 'item_type']);
            $table->unsignedBigInteger('flower_id')->nullable(false)->change();
        });
    }
};
