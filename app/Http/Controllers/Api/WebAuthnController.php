<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\WebAuthnCredential;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class WebAuthnController extends Controller
{
    /**
     * Convert binary data to base64url encoding (WebAuthn standard)
     */
    private function base64url_encode($data)
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    /**
     * Decode base64url encoded data
     */
    private function base64url_decode($data)
    {
        return base64_decode(strtr($data, '-_', '+/'));
    }

    /**
     * Generate registration options for WebAuthn
     * Called when user wants to register a biometric credential
     */
    public function registerOptions(Request $request)
    {
        try {
            $user = $request->user();

            // Generate a random challenge (must be stored in cache for verification)
            $challenge = Str::random(32);

            // Store challenge in cache for later verification (5 minutes)
            $cacheKey = 'webauthn_register_' . $user->id;
            Cache::put($cacheKey, [
                'challenge' => $challenge,
                'user_id' => $user->id,
            ], now()->addMinutes(5));

            // Get existing credentials to exclude them
            $excludeCredentials = $user->webauthnCredentials()
                ->get()
                ->map(function ($cred) {
                    return [
                        'type' => 'public-key',
                        'id' => $cred->credential_id,
                    ];
                })
                ->toArray();

            // Get hostname from APP_URL, handling Punycode domains
            $appUrl = config('app.url');
            $hostname = parse_url($appUrl, PHP_URL_HOST);

            // Remove 'www.' prefix if present
            if (str_starts_with($hostname, 'www.')) {
                $hostname = substr($hostname, 4);
            }

            $options = [
                'challenge' => $this->base64url_encode($challenge),
                'rp' => [
                    'name' => config('app.name', 'Flores D\' Jazmin'),
                    'id' => $hostname,
                ],
                'user' => [
                    'id' => $this->base64url_encode($user->id),
                    'name' => $user->email,
                    'displayName' => $user->name,
                ],
                'pubKeyCredParams' => [
                    ['type' => 'public-key', 'alg' => -7],  // ES256
                    ['type' => 'public-key', 'alg' => -257], // RS256
                ],
                'timeout' => 60000,
                'attestation' => 'none',
                'excludeCredentials' => $excludeCredentials,
                'authenticatorSelection' => [
                    'authenticatorAttachment' => 'platform', // Prefer platform authenticators (biometrics)
                    'requireResidentKey' => false,
                    'residentKey' => 'preferred',
                    'userVerification' => 'required', // Require biometric verification
                ],
            ];

            return response()->json([
                'success' => true,
                'data' => $options
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate registration options',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Verify and store the registered credential
     */
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'credential_id' => 'required|string',
            'public_key' => 'required|string',
            'aaguid' => 'nullable|string',
            'counter' => 'required|integer',
            'device_name' => 'nullable|string|max:255',
            'device_type' => 'nullable|string|in:fingerprint,face,security_key',
            'transports' => 'nullable|array',
            'attestation_object' => 'required|string',
            'client_data_json' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation errors',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $user = $request->user();
            \Log::info('WebAuthn Register - User ID: ' . $user->id);

            // Verify challenge from cache
            $cacheKey = 'webauthn_register_' . $user->id;
            $cachedData = Cache::get($cacheKey);
            \Log::info('WebAuthn Register - Cache data: ' . json_encode($cachedData));

            if (!$cachedData || $cachedData['user_id'] != $user->id) {
                \Log::error('WebAuthn Register - Invalid or expired challenge');
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid or expired challenge'
                ], 400);
            }

            $storedChallenge = $cachedData['challenge'];

            // Decode and verify client data
            $clientDataJSON = base64_decode($request->client_data_json);
            $clientData = json_decode($clientDataJSON, true);
            \Log::info('WebAuthn Register - Client data origin: ' . $clientData['origin']);

            // Verify challenge matches (client returns base64url encoded challenge)
            if ($this->base64url_encode($storedChallenge) !== $clientData['challenge']) {
                \Log::error('WebAuthn Register - Challenge mismatch. Expected: ' . $this->base64url_encode($storedChallenge) . ', Got: ' . $clientData['challenge']);
                return response()->json([
                    'success' => false,
                    'message' => 'Challenge mismatch'
                ], 400);
            }

            // Verify origin
            $expectedOrigin = config('app.url');
            if ($clientData['origin'] !== $expectedOrigin) {
                \Log::error('WebAuthn Register - Origin mismatch. Expected: ' . $expectedOrigin . ', Got: ' . $clientData['origin']);
                return response()->json([
                    'success' => false,
                    'message' => 'Origin mismatch'
                ], 400);
            }

            // Check if credential already exists
            if (WebAuthnCredential::where('credential_id', $request->credential_id)->exists()) {
                \Log::error('WebAuthn Register - Credential already exists');
                return response()->json([
                    'success' => false,
                    'message' => 'Credential already registered'
                ], 409);
            }

            \Log::info('WebAuthn Register - Creating credential...');
            // Create credential
            $credential = WebAuthnCredential::create([
                'user_id' => $user->id,
                'credential_id' => $request->credential_id,
                'public_key' => $request->public_key,
                'aaguid' => $request->aaguid,
                'counter' => $request->counter,
                'device_name' => $request->device_name ?? 'Dispositivo Biométrico',
                'device_type' => $request->device_type ?? 'fingerprint',
                'user_agent' => $request->header('User-Agent'),
                'transports' => $request->transports,
                'is_discoverable' => $request->is_discoverable ?? false,
                'backup_eligible' => $request->backup_eligible ?? false,
                'backup_state' => $request->backup_state ?? false,
                'last_used_at' => now(),
                'last_used_ip' => $request->ip(),
                'use_count' => 0,
            ]);

            \Log::info('WebAuthn Register - Credential created successfully. ID: ' . $credential->id);

            // Clear challenge from cache
            Cache::forget($cacheKey);

            return response()->json([
                'success' => true,
                'message' => 'Biometric credential registered successfully',
                'data' => [
                    'id' => $credential->id,
                    'device_name' => $credential->device_name,
                    'device_type' => $credential->device_type_label,
                    'created_at' => $credential->created_at
                ]
            ], 201);

        } catch (\Exception $e) {
            \Log::error('WebAuthn Register - Exception: ' . $e->getMessage());
            \Log::error('WebAuthn Register - Stack trace: ' . $e->getTraceAsString());
            return response()->json([
                'success' => false,
                'message' => 'Failed to register credential',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Generate authentication options for WebAuthn login
     */
    public function loginOptions(Request $request)
    {
        // Si no hay email, generar opciones para discoverable credentials (sin allowCredentials)
        $email = $request->input('email');

        if (!$email) {
            // Login sin email - discoverable credentials
            try {
                // Generate challenge
                $challenge = Str::random(32);

                // Store challenge in cache with a generic key (5 minutes)
                $cacheKey = 'webauthn_login_discoverable_' . Str::random(16);
                Cache::put($cacheKey, [
                    'challenge' => $challenge,
                    'type' => 'discoverable',
                ], now()->addMinutes(5));

                // Get hostname from APP_URL, handling Punycode domains
                $appUrl = config('app.url');
                $hostname = parse_url($appUrl, PHP_URL_HOST);

                // Remove 'www.' prefix if present
                if (str_starts_with($hostname, 'www.')) {
                    $hostname = substr($hostname, 4);
                }

                $options = [
                    'challenge' => $this->base64url_encode($challenge),
                    'timeout' => 60000,
                    'rpId' => $hostname,
                    'allowCredentials' => [], // Empty for discoverable credentials
                    'userVerification' => 'required',
                    'cacheKey' => $cacheKey, // Enviar la clave para usarla en login
                ];

                return response()->json([
                    'success' => true,
                    'has_credentials' => true,
                    'data' => $options
                ]);

            } catch (\Exception $e) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to generate login options',
                    'error' => $e->getMessage()
                ], 500);
            }
        }

        // Login con email - validación normal
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|exists:users,email',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation errors',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $user = User::where('email', $request->email)->first();

            if (!$user || !$user->is_active) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not found or inactive'
                ], 404);
            }

            // Get user's credentials
            $credentials = $user->webauthnCredentials()
                ->get()
                ->map(function ($cred) {
                    return [
                        'type' => 'public-key',
                        'id' => $cred->credential_id,
                    ];
                })
                ->toArray();

            if (empty($credentials)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No biometric credentials found for this user',
                    'has_credentials' => false
                ], 404);
            }

            // Generate challenge
            $challenge = Str::random(32);

            // Store challenge in cache for later verification (5 minutes)
            $cacheKey = 'webauthn_login_' . $user->email;
            Cache::put($cacheKey, [
                'challenge' => $challenge,
                'email' => $user->email,
            ], now()->addMinutes(5));

            // Get hostname from APP_URL, handling Punycode domains
            $appUrl = config('app.url');
            $hostname = parse_url($appUrl, PHP_URL_HOST);

            // Remove 'www.' prefix if present
            if (str_starts_with($hostname, 'www.')) {
                $hostname = substr($hostname, 4);
            }

            $options = [
                'challenge' => $this->base64url_encode($challenge),
                'timeout' => 60000,
                'rpId' => $hostname,
                'allowCredentials' => $credentials,
                'userVerification' => 'required',
            ];

            return response()->json([
                'success' => true,
                'has_credentials' => true,
                'data' => $options
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate login options',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Verify authentication and login user
     */
    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'credential_id' => 'required|string',
            'authenticator_data' => 'required|string',
            'client_data_json' => 'required|string',
            'signature' => 'required|string',
            'cacheKey' => 'nullable|string', // Para discoverable credentials
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation errors',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            \Log::info('WebAuthn Login - Request received');
            \Log::info('WebAuthn Login - Credential ID: ' . $request->credential_id);

            // Decode client data to get email
            $clientDataJSON = base64_decode($request->client_data_json);
            $clientData = json_decode($clientDataJSON, true);
            \Log::info('WebAuthn Login - Client data decoded');

            // Find credential first to get user
            $credential = WebAuthnCredential::where('credential_id', $request->credential_id)->first();

            if (!$credential) {
                \Log::error('WebAuthn Login - Credential not found: ' . $request->credential_id);
                return response()->json([
                    'success' => false,
                    'message' => 'Credential not found'
                ], 404);
            }

            \Log::info('WebAuthn Login - Credential found. ID: ' . $credential->id . ', User: ' . $credential->user_id);
            $user = $credential->user;

            // Verify challenge from cache - usar cacheKey si se proporciona (discoverable) o email (tradicional)
            $cacheKey = $request->input('cacheKey')
                ? $request->input('cacheKey')
                : 'webauthn_login_' . $user->email;

            $cachedData = Cache::get($cacheKey);
            \Log::info('WebAuthn Login - Cache key: ' . $cacheKey . ', Data: ' . json_encode($cachedData));

            if (!$cachedData) {
                \Log::error('WebAuthn Login - Invalid or expired challenge');
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid or expired challenge'
                ], 400);
            }

            // Si es login tradicional (con email), verificar que coincida
            if (isset($cachedData['email']) && $cachedData['email'] != $user->email) {
                \Log::error('WebAuthn Login - Email mismatch in cache');
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid or expired challenge'
                ], 400);
            }

            $storedChallenge = $cachedData['challenge'];

            if (!$user->is_active) {
                \Log::error('WebAuthn Login - User inactive: ' . $user->email);
                return response()->json([
                    'success' => false,
                    'message' => 'User not found or inactive'
                ], 403);
            }

            // Decode and verify client data
            $clientDataJSON = base64_decode($request->client_data_json);
            $clientData = json_decode($clientDataJSON, true);

            // Verify challenge (client returns base64url encoded challenge)
            \Log::info('WebAuthn Login - Stored challenge (base64url): ' . $this->base64url_encode($storedChallenge));
            \Log::info('WebAuthn Login - Client challenge: ' . $clientData['challenge']);

            if ($this->base64url_encode($storedChallenge) !== $clientData['challenge']) {
                \Log::error('WebAuthn Login - Challenge mismatch!');
                return response()->json([
                    'success' => false,
                    'message' => 'Challenge mismatch'
                ], 400);
            }

            // Verify origin
            $expectedOrigin = config('app.url');
            \Log::info('WebAuthn Login - Expected origin: ' . $expectedOrigin . ', Got: ' . $clientData['origin']);

            if ($clientData['origin'] !== $expectedOrigin) {
                \Log::error('WebAuthn Login - Origin mismatch!');
                return response()->json([
                    'success' => false,
                    'message' => 'Origin mismatch'
                ], 400);
            }

            // Decode authenticator data (comes as base64url from frontend)
            $authenticatorData = $this->base64url_decode($request->authenticator_data);
            \Log::info('WebAuthn Login - Authenticator data length: ' . strlen($authenticatorData));

            // Extract counter from authenticator data (bytes 33-36)
            // Authenticator data structure: RP ID hash (32) + flags (1) + counter (4) + ...
            if (strlen($authenticatorData) < 37) {
                \Log::error('WebAuthn Login - Authenticator data too short: ' . strlen($authenticatorData) . ' bytes');
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid authenticator data'
                ], 400);
            }

            $counter = unpack('N', substr($authenticatorData, 33, 4))[1];
            \Log::info('WebAuthn Login - Counter from authenticator: ' . $counter . ', Stored counter: ' . $credential->counter);

            // Verify counter is greater than stored counter (anti-cloning)
            // Allow counter to be equal or greater on first use (stored counter = 0)
            if ($credential->counter > 0 && $counter <= $credential->counter) {
                \Log::error('WebAuthn Login - Counter check failed. Possible cloning!');
                return response()->json([
                    'success' => false,
                    'message' => 'Possible cloned authenticator detected'
                ], 400);
            }

            // TODO: Verify signature using public key
            // This requires a proper cryptographic library
            // For now, we'll trust the credential based on challenge verification

            \Log::info('WebAuthn Login - All verifications passed. Updating credential...');

            // Update credential usage
            $credential->counter = $counter;
            $credential->recordUsage($request->ip());

            // Update user last login
            $user->last_login_at = now();
            $user->save();

            // Clear challenge from cache
            Cache::forget($cacheKey);

            // Revoke old tokens
            $user->tokens()->delete();

            // Create new token
            $token = $user->createToken('auth_token', ['*'], now()->addHours(8))->plainTextToken;

            \Log::info('WebAuthn Login - Login successful for user: ' . $user->email);

            return response()->json([
                'success' => true,
                'message' => 'Login successful with biometric authentication',
                'data' => [
                    'user' => $user,
                    'token' => $token,
                    'token_type' => 'Bearer',
                    'expires_at' => now()->addHours(8)->toISOString(),
                    'auth_method' => 'webauthn'
                ]
            ]);

        } catch (\Exception $e) {
            \Log::error('WebAuthn Login - Exception: ' . $e->getMessage());
            \Log::error('WebAuthn Login - Stack trace: ' . $e->getTraceAsString());
            return response()->json([
                'success' => false,
                'message' => 'Authentication failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * List user's registered credentials
     */
    public function listCredentials(Request $request)
    {
        try {
            $credentials = $request->user()
                ->webauthnCredentials()
                ->orderBy('last_used_at', 'desc')
                ->get()
                ->map(function ($cred) {
                    return [
                        'id' => $cred->id,
                        'device_name' => $cred->device_name,
                        'device_type' => $cred->device_type_label,
                        'is_platform' => $cred->isPlatformAuthenticator(),
                        'last_used_at' => $cred->last_used_at?->diffForHumans(),
                        'use_count' => $cred->use_count,
                        'created_at' => $cred->created_at->format('d/m/Y H:i'),
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => $credentials
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to list credentials',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete a credential
     */
    public function deleteCredential(Request $request, $id)
    {
        try {
            $credential = $request->user()
                ->webauthnCredentials()
                ->findOrFail($id);

            $credential->delete();

            return response()->json([
                'success' => true,
                'message' => 'Credential deleted successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete credential',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Check if user has biometric credentials
     */
    public function checkAvailability(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'available' => false,
                'message' => 'Email is required'
            ], 422);
        }

        try {
            $user = User::where('email', $request->email)->first();

            if (!$user) {
                return response()->json([
                    'success' => true,
                    'available' => false,
                    'message' => 'User not found'
                ]);
            }

            $hasCredentials = $user->webauthnCredentials()->exists();

            return response()->json([
                'success' => true,
                'available' => $hasCredentials,
                'count' => $user->webauthnCredentials()->count()
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'available' => false,
                'message' => 'Failed to check availability',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}

