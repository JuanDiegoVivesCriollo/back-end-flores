<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasFactory, Notifiable, HasApiTokens;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'phone',
        'role',
        'address',
        'city',
        'postal_code',
        'is_active',
        'last_login_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Check if user is admin
     */
    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    /**
     * Orders relationship
     */
    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    /**
     * Notifications relationship
     */
    public function notifications()
    {
        return $this->hasMany(Notification::class);
    }

    /**
     * Check if user is customer/user
     */
    public function isCustomer()
    {
        return $this->role === 'user';
    }

    /**
     * Check if user is guest
     */
    public function isGuest()
    {
        return $this->role === 'guest';
    }

    /**
     * WebAuthn credentials relationship
     */
    public function webauthnCredentials()
    {
        return $this->hasMany(WebAuthnCredential::class);
    }

    /**
     * Check if user has biometric authentication enabled
     */
    public function hasBiometricAuth(): bool
    {
        return $this->webauthnCredentials()->exists();
    }
}
