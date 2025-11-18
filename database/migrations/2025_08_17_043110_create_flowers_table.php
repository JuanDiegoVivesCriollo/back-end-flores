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
        Schema::create('flowers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description');
            $table->text('short_description')->nullable();
            $table->decimal('price', 10, 2);
            $table->decimal('original_price', 10, 2)->nullable();
            $table->integer('discount_percentage')->default(0);
            $table->foreignId('category_id')->constrained()->onDelete('cascade');
            $table->string('color');
            $table->string('occasion');
            $table->json('images'); // Array de URLs de imágenes
            $table->decimal('rating', 3, 2)->default(0);
            $table->integer('reviews_count')->default(0);
            $table->integer('stock')->default(0);
            $table->boolean('is_featured')->default(false);
            $table->boolean('is_on_sale')->default(false);
            $table->boolean('is_active')->default(true);
            $table->integer('views')->default(0);
            $table->integer('sort_order')->default(0);
            $table->json('metadata')->nullable(); // Para datos adicionales flexibles
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('flowers');
    }
};
