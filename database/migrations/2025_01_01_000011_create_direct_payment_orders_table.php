<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('direct_payment_orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_number')->unique();
            $table->string('status')->default('pending');
            $table->string('payment_status')->default('pending');
            $table->decimal('total', 10, 2);
            $table->decimal('subtotal', 10, 2);
            $table->decimal('shipping_cost', 10, 2)->default(0);
            $table->string('shipping_type')->default('pickup');
            $table->json('shipping_address')->nullable();
            $table->string('customer_name');
            $table->string('customer_email');
            $table->string('customer_phone', 20);
            $table->string('customer_document_type', 10)->default('DNI');
            $table->string('customer_document_number', 20);
            $table->string('recipient_name')->nullable();
            $table->string('recipient_phone', 20)->nullable();
            $table->datetime('delivery_date')->nullable();
            $table->string('delivery_time_slot', 100)->nullable();
            $table->text('notes')->nullable();
            $table->json('items');
            $table->string('payment_code')->nullable();
            $table->text('google_maps_link')->nullable();
            $table->text('session_token')->nullable();
            $table->timestamps();

            $table->index(['order_number']);
            $table->index(['customer_email']);
            $table->index(['created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('direct_payment_orders');
    }
};
