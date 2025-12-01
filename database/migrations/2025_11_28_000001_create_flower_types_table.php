<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Tabla de tipos de flores (Rosa, Girasol, Tulipán, etc.)
        Schema::create('flower_types', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // Ej: Rosa, Girasol, Tulipán, Lirio
            $table->string('slug')->unique();
            $table->string('icon')->nullable(); // Emoji o icono
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        // Tabla pivot para relación muchos a muchos (un ramo puede tener múltiples tipos de flores)
        Schema::create('flower_flower_type', function (Blueprint $table) {
            $table->id();
            $table->foreignId('flower_id')->constrained()->onDelete('cascade');
            $table->foreignId('flower_type_id')->constrained()->onDelete('cascade');
            $table->timestamps();

            $table->unique(['flower_id', 'flower_type_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('flower_flower_type');
        Schema::dropIfExists('flower_types');
    }
};
