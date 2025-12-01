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
        Schema::create('guest_customers', function (Blueprint $table) {
            $table->id();
            $table->string('full_name');
            $table->string('email')->nullable();
            $table->string('phone');
            $table->string('dni')->nullable();
            $table->string('session_token', 64)->unique();
            $table->boolean('is_registered')->default(false);
            $table->timestamp('last_order_at')->nullable();
            $table->integer('total_orders')->default(0);
            $table->decimal('total_spent', 10, 2)->default(0);
            $table->timestamps();
            
            $table->index(['email', 'phone']);
        });

        // Add guest_customer_id to orders table
        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('guest_customer_id')->nullable()->after('user_id')->constrained('guest_customers')->nullOnDelete();
            $table->string('payment_method')->nullable()->after('payment_status');
            $table->string('payment_proof_image')->nullable()->after('payment_method');
            $table->text('payment_proof_notes')->nullable()->after('payment_proof_image');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['guest_customer_id']);
            $table->dropColumn(['guest_customer_id', 'payment_method', 'payment_proof_image', 'payment_proof_notes']);
        });
        
        Schema::dropIfExists('guest_customers');
    }
};
