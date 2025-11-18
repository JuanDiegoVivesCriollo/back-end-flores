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
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->onDelete('cascade');
            $table->foreignId('flower_id')->constrained()->onDelete('cascade');
            $table->string('flower_name'); // Snapshot del nombre al momento de la compra
            $table->decimal('price', 10, 2); // Snapshot del precio al momento de la compra
            $table->integer('quantity');
            $table->decimal('total', 10, 2);
            $table->json('flower_snapshot')->nullable(); // Datos completos del producto al momento de la compra
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};
