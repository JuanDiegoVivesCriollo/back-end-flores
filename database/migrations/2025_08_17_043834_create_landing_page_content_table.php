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
        Schema::create('landing_page_content', function (Blueprint $table) {
            $table->id();
            $table->string('section'); // hero, about, services, contact, etc.
            $table->string('key'); // title, subtitle, description, etc.
            $table->text('value'); // El contenido actual
            $table->string('type')->default('text'); // text, html, image, json
            $table->text('description')->nullable(); // Descripción para el admin
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['section', 'key']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('landing_page_content');
    }
};
