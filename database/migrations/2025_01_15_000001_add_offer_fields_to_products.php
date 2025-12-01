<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add is_on_sale to breakfasts
        Schema::table('breakfasts', function (Blueprint $table) {
            if (!Schema::hasColumn('breakfasts', 'is_on_sale')) {
                $table->boolean('is_on_sale')->default(false)->after('is_featured');
            }
        });

        // Add is_on_sale and discount_percentage to complements
        Schema::table('complements', function (Blueprint $table) {
            if (!Schema::hasColumn('complements', 'discount_percentage')) {
                $table->integer('discount_percentage')->default(0)->after('original_price');
            }
            if (!Schema::hasColumn('complements', 'is_on_sale')) {
                $table->boolean('is_on_sale')->default(false)->after('is_featured');
            }
            if (!Schema::hasColumn('complements', 'images')) {
                $table->json('images')->nullable()->after('image');
            }
        });
    }

    public function down(): void
    {
        Schema::table('breakfasts', function (Blueprint $table) {
            if (Schema::hasColumn('breakfasts', 'is_on_sale')) {
                $table->dropColumn('is_on_sale');
            }
        });

        Schema::table('complements', function (Blueprint $table) {
            if (Schema::hasColumn('complements', 'discount_percentage')) {
                $table->dropColumn('discount_percentage');
            }
            if (Schema::hasColumn('complements', 'is_on_sale')) {
                $table->dropColumn('is_on_sale');
            }
            if (Schema::hasColumn('complements', 'images')) {
                $table->dropColumn('images');
            }
        });
    }
};
