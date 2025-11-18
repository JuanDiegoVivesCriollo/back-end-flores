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
        Schema::create('delivery_districts', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('slug', 100)->unique();
            $table->decimal('shipping_cost', 8, 2);
            $table->boolean('is_active')->default(true);
            $table->string('zone', 50)->nullable(); // Norte, Sur, Este, Oeste, Centro, Callao
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['is_active', 'zone']);
            $table->index('shipping_cost');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('delivery_districts');
    }
};
