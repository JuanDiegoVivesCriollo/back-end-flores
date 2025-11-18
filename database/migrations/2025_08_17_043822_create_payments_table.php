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
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->onDelete('cascade');
            $table->string('payment_method'); // izipay, cash, etc.
            $table->string('transaction_id')->nullable(); // ID de Izipay
            $table->string('payment_status'); // pending, completed, failed, refunded
            $table->decimal('amount', 10, 2);
            $table->string('currency', 3)->default('PEN');
            $table->json('payment_details')->nullable(); // Respuesta completa de Izipay
            $table->timestamp('paid_at')->nullable();
            $table->text('failure_reason')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
