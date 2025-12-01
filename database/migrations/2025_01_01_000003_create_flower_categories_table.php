<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('flower_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('flower_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['flower_id', 'category_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('flower_categories');
    }
};
