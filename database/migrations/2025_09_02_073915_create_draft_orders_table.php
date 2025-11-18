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
        Schema::create('draft_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('draft_number')->unique();
            $table->json('cart_data'); // Items in cart
            $table->json('customer_info')->nullable();
            $table->json('shipping_address')->nullable();
            $table->json('billing_address')->nullable();
            $table->string('shipping_type')->default('standard');
            $table->datetime('delivery_date')->nullable();
            $table->string('delivery_time_slot')->nullable();
            $table->text('customer_notes')->nullable();
            $table->decimal('subtotal', 10, 2);
            $table->decimal('tax', 10, 2)->default(0);
            $table->decimal('shipping_cost', 10, 2)->default(0);
            $table->decimal('total', 10, 2);
            $table->datetime('expires_at')->nullable(); // When this draft expires
            $table->foreignId('converted_to_order_id')->nullable()->constrained('orders')->onDelete('set null');
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index('expires_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('draft_orders');
    }
};
