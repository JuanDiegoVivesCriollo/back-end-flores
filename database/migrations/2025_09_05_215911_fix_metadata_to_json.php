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
        // Use raw SQL to ensure proper JSON column type
        DB::statement('ALTER TABLE payments MODIFY COLUMN metadata JSON NULL');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert to longtext if needed
        DB::statement('ALTER TABLE payments MODIFY COLUMN metadata LONGTEXT NULL');
    }
};
