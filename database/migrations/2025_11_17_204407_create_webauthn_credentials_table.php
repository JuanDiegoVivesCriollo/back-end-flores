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
        Schema::create('webauthn_credentials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');

            // WebAuthn credential data
            $table->string('credential_id')->unique(); // Base64URL encoded credential ID
            $table->text('public_key'); // Public key for verification
            $table->string('aaguid')->nullable(); // Authenticator AAGUID
            $table->unsignedInteger('counter')->default(0); // Signature counter (anti-cloning)

            // Device information
            $table->string('device_name')->nullable(); // User-friendly device name
            $table->string('device_type')->nullable(); // fingerprint, face, security_key
            $table->string('user_agent')->nullable(); // Browser/device info

            // Transports (usb, nfc, ble, internal)
            $table->json('transports')->nullable();

            // Security flags
            $table->boolean('is_discoverable')->default(false); // Resident key
            $table->boolean('backup_eligible')->default(false);
            $table->boolean('backup_state')->default(false);

            // Usage tracking
            $table->timestamp('last_used_at')->nullable();
            $table->ipAddress('last_used_ip')->nullable();
            $table->unsignedInteger('use_count')->default(0);

            $table->timestamps();

            // Indexes for performance
            $table->index(['user_id', 'last_used_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('webauthn_credentials');
    }
};
