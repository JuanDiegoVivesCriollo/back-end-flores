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
        Schema::table('orders', function (Blueprint $table) {
            // Primero eliminar la foreign key constraint
            $table->dropForeign(['user_id']);

            // Modificar la columna para permitir NULL
            $table->foreignId('user_id')->nullable()->change();

            // Volver a agregar la foreign key constraint pero con onDelete('set null')
            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // Eliminar la foreign key constraint
            $table->dropForeign(['user_id']);

            // Primero, actualizar cualquier registro con user_id NULL a un valor por defecto
            // (opcional: puedes ajustar esto según tu lógica de negocio)
            DB::table('orders')->whereNull('user_id')->delete();

            // Modificar la columna para que NO permita NULL
            $table->foreignId('user_id')->nullable(false)->change();

            // Volver a agregar la foreign key constraint original
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }
};
