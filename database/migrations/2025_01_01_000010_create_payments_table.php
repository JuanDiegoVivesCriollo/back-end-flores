<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('direct_payment_order_id')->nullable();
            $table->string('transaction_id')->nullable();
            $table->string('session_token', 500)->nullable();
            $table->string('payment_method', 50)->nullable(); // card, transfer, yape, plin
            $table->string('payment_gateway', 50)->default('izipay');
            $table->decimal('amount', 10, 2);
            $table->string('currency', 3)->default('PEN');
            $table->string('status')->default('pending');
            $table->json('gateway_response')->nullable();
            $table->json('metadata')->nullable();
            $table->datetime('paid_at')->nullable();
            $table->datetime('expires_at')->nullable();
            $table->timestamps();

            $table->index(['order_id', 'status']);
            $table->index(['transaction_id']);
            $table->index(['session_token']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
