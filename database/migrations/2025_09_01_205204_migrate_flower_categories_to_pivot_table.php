<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Migrar datos existentes de la columna category_id a la tabla pivot
        $flowers = DB::table('flowers')->whereNotNull('category_id')->get();

        foreach ($flowers as $flower) {
            DB::table('flower_categories')->insert([
                'flower_id' => $flower->id,
                'category_id' => $flower->category_id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Hacer el campo category_id nullable (mantenerlo por compatibilidad temporal)
        Schema::table('flowers', function (Blueprint $table) {
            $table->foreignId('category_id')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Restaurar datos desde la tabla pivot a category_id
        $pivotData = DB::table('flower_categories')
            ->select('flower_id', DB::raw('MIN(category_id) as category_id'))
            ->groupBy('flower_id')
            ->get();

        foreach ($pivotData as $data) {
            DB::table('flowers')
                ->where('id', $data->flower_id)
                ->update(['category_id' => $data->category_id]);
        }

        // Hacer el campo category_id requerido nuevamente
        Schema::table('flowers', function (Blueprint $table) {
            $table->foreignId('category_id')->nullable(false)->change();
        });
    }
};
