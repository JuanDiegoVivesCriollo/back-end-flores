<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WebAuthnCredential extends Model
{
    use HasFactory;

    protected $table = 'webauthn_credentials';

    protected $fillable = [
        'user_id',
        'credential_id',
        'public_key',
        'aaguid',
        'counter',
        'device_name',
        'device_type',
        'user_agent',
        'transports',
        'is_discoverable',
        'backup_eligible',
        'backup_state',
        'last_used_at',
        'last_used_ip',
        'use_count',
    ];

    protected $casts = [
        'counter' => 'integer',
        'use_count' => 'integer',
        'is_discoverable' => 'boolean',
        'backup_eligible' => 'boolean',
        'backup_state' => 'boolean',
        'transports' => 'array',
        'last_used_at' => 'datetime',
    ];

    /**
     * User relationship
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Update usage statistics
     */
    public function recordUsage(?string $ip = null)
    {
        $this->update([
            'last_used_at' => now(),
            'last_used_ip' => $ip,
            'use_count' => $this->use_count + 1,
        ]);
    }

    /**
     * Increment counter (anti-cloning protection)
     */
    public function incrementCounter()
    {
        $this->increment('counter');
    }

    /**
     * Check if this is a platform authenticator (built-in biometric)
     */
    public function isPlatformAuthenticator(): bool
    {
        return in_array('internal', $this->transports ?? []);
    }

    /**
     * Check if this is a cross-platform authenticator (security key)
     */
    public function isCrossPlatformAuthenticator(): bool
    {
        return !$this->isPlatformAuthenticator();
    }

    /**
     * Get human-readable device type
     */
    public function getDeviceTypeLabelAttribute(): string
    {
        return match($this->device_type) {
            'fingerprint' => 'Huella Dactilar',
            'face' => 'Reconocimiento Facial',
            'security_key' => 'Llave de Seguridad',
            default => 'Biométrico',
        };
    }
}
