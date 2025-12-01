<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('order_number')->unique();
            $table->string('status')->default('pending');
            $table->string('payment_status')->default('pending');
            $table->decimal('total', 10, 2);
            $table->decimal('subtotal', 10, 2);
            $table->decimal('tax', 10, 2)->default(0);
            $table->decimal('shipping_cost', 10, 2)->default(0);
            $table->string('shipping_type')->default('pickup'); // pickup, delivery
            $table->json('shipping_address')->nullable();
            $table->json('billing_address')->nullable();
            $table->json('pickup_store')->nullable();
            $table->string('customer_name');
            $table->string('customer_email');
            $table->string('customer_phone', 20);
            $table->json('customer_info')->nullable();
            $table->text('notes')->nullable();
            $table->datetime('delivery_date')->nullable();
            $table->string('delivery_time_slot', 100)->nullable();
            $table->string('recipient_name')->nullable();
            $table->string('recipient_phone', 20)->nullable();
            $table->string('recipient_relationship', 100)->nullable();
            $table->text('special_instructions')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['order_number']);
            $table->index(['created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
