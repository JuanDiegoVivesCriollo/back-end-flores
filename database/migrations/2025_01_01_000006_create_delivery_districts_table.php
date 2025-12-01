<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('delivery_districts', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('zone', 50)->nullable(); // zona_1, zona_2, etc
            $table->decimal('shipping_cost', 10, 2)->default(0);
            $table->string('estimated_time', 100)->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->index(['is_active', 'zone']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_districts');
    }
};
