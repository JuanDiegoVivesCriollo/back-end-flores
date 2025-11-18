<?php

namespace App\Services\Payment;

use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

class IzipayService
{
    protected $shopId;
    protected $username;
    protected $testKey;
    protected $prodKey;
    protected $testPassword;
    protected $prodPassword;
    protected $testPublicKey;
    protected $prodPublicKey;
    protected $testHmacKey;
    protected $prodHmacKey;
    protected $environment;
    protected $ctxMode;
    protected $integrationMode;

    public function __construct()
    {
        $config = config('services.izipay');

        $this->shopId = $config['shop_id'];
        $this->username = $config['username'] ?? $config['shop_id'];

        // Cargar configuración del entorno
        $this->environment = $config['environment'] ?? 'production';
        $this->ctxMode = $config['ctx_mode'] ?? 'PRODUCTION';
        $this->integrationMode = $config['integration_mode'] ?? 'form'; // Usar form que funciona

        // Si el modo es 'MOCK', convertir a 'mock' para consistencia
        if (strtoupper($this->environment) === 'MOCK') {
            $this->environment = 'mock';
        }

        // Cargar todas las credenciales
        $this->testKey = $config['test_key'];
        $this->prodKey = $config['prod_key'];
        $this->testPassword = $config['test_password'];
        $this->prodPassword = $config['prod_password'];
        $this->testPublicKey = $config['test_public_key'];
        $this->prodPublicKey = $config['prod_public_key'];
        $this->testHmacKey = $config['test_hmac_key'];
        $this->prodHmacKey = $config['prod_hmac_key'];

        Log::info('IzipayService initialized', [
            'environment' => $this->environment,
            'shop_id' => $this->shopId,
            'ctx_mode' => $this->ctxMode,
            'integration_mode' => $this->integrationMode
        ]);
    }

    /**
     * Crear sesión de pago - método unificado que decide entre SDK o formulario
     */
    public function createPaymentSession(Order $order, User $user): array
    {
        try {
            Log::info('Creating payment session', [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'integration_mode' => $this->integrationMode,
                'environment' => $this->environment,
                'user_type' => $user ? 'authenticated' : 'guest'
            ]);

            // Si está en modo MOCK, usar método simulado
            if ($this->environment === 'mock') {
                return $this->createMockSession($order, $user);
            }

            // Decidir qué método usar según la configuración
            if ($this->integrationMode === 'form') {
                return $this->createFormPayment($order, $user);
            } else {
                return $this->createSDKPayment($order, $user);
            }

        } catch (\Exception $e) {
            Log::error('Exception during payment session creation', [
                'error' => $e->getMessage(),
                'order_id' => $order->id ?? 'unknown'
            ]);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Crear sesión de pago desde DraftOrder - NO CONVIERTE A ORDEN REAL HASTA PAGO EXITOSO
     */
    public function createPaymentSessionFromDraft(\App\Models\DraftOrder $draftOrder, User $user): array
    {
        try {
            Log::info('Creating payment session from draft', [
                'draft_id' => $draftOrder->id,
                'draft_number' => $draftOrder->draft_number,
                'integration_mode' => $this->integrationMode,
                'environment' => $this->environment,
                'user_type' => $user ? 'authenticated' : 'guest'
            ]);

            // Si está en modo MOCK, usar método simulado
            if ($this->environment === 'mock') {
                return $this->createMockSessionFromDraft($draftOrder, $user);
            }

            // Decidir qué método usar según la configuración
            if ($this->integrationMode === 'form') {
                return $this->createFormPaymentFromDraft($draftOrder, $user);
            } else {
                return $this->createSDKPaymentFromDraft($draftOrder, $user);
            }

        } catch (\Exception $e) {
            Log::error('Exception during draft payment session creation', [
                'error' => $e->getMessage(),
                'draft_id' => $draftOrder->id ?? 'unknown'
            ]);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Crear pago con nuevo SDK
     */
    private function createSDKPayment(Order $order, User $user): array
    {
        $transactionId = 'SDK_' . time() . '_' . $order->id;

        // Generar token con el nuevo SDK approach
        $sessionData = $this->generateNewSDKSession($order, $user, $transactionId);

        if (!$sessionData['success']) {
            Log::error('Failed to generate session with new SDK');
            return ['success' => false, 'error' => 'Failed to generate session token'];
        }

        // Crear registro en payments (ahora siempre es una orden real)
        $metadata = [
            'environment' => $this->environment,
            'shop_id' => $this->shopId,
            'transaction_id' => $transactionId,
            'ctx_mode' => $this->ctxMode,
            'integration_mode' => 'sdk',
            'created_at' => now()->toISOString()
        ];

        $payment = Payment::create([
            'order_id' => $order->id,
            'payment_method' => 'izipay',
            'amount' => $order->total,
            'currency' => 'PEN',
            'status' => 'pending',
            'payment_status' => 'pending',
            'transaction_id' => $transactionId,
            'session_token' => $sessionData['authorization'],
            'metadata' => json_encode($metadata)
        ]);

        Log::info('SDK payment session created successfully', [
            'payment_id' => $payment->id,
            'transaction_id' => $transactionId,
            'order_status' => $order->status
        ]);

        return [
            'success' => true,
            'payment_id' => $payment->id,
            'form_token' => $sessionData['authorization'],
            'public_key' => $this->getCurrentPublicKey(),
            'authorization' => $sessionData['authorization'],
            'keyRSA' => $sessionData['keyRSA'],
            'endpoint' => $this->environment === 'test'
                ? 'https://sandbox-checkout.izipay.pe'
                : 'https://checkout.izipay.pe',
            'transaction_id' => $transactionId,
            'amount' => $order->total,
            'currency' => 'PEN',
            'config' => $sessionData['config'],
            'integration_mode' => 'sdk'
        ];
    }

    /**
     * Generar sesión con el nuevo SDK
     */
    private function generateNewSDKSession(Order $order, ?User $user, string $transactionId): array
    {
        try {
            $apiEndpoint = $this->environment === 'test'
                ? 'https://api.micuentaweb.pe'
                : 'https://api-pw.izipay.pe';

            $url = $apiEndpoint . '/security/v1/Token/Generate';

            // Obtener datos del cliente desde customer_info si están disponibles
            $customerInfo = null;
            if (!empty($order->customer_info)) {
                $customerInfo = json_decode($order->customer_info, true);
                Log::info('Customer info found in order', ['customer_info' => $customerInfo]);
            } else {
                Log::warning('No customer_info found in order, using user data fallback');
            }

            // Usar datos de customer_info si están disponibles, sino usar datos del user
            $firstName = $customerInfo['firstName'] ?? explode(' ', $user->name ?? '', 2)[0] ?? '';
            $lastName = $customerInfo['lastName'] ?? explode(' ', $user->name ?? '', 2)[1] ?? $firstName;
            $email = $customerInfo['email'] ?? $user->email;
            $phoneNumber = $customerInfo['phoneNumber'] ?? $user->phone ?? '999999999';
            $address = $customerInfo['address'] ?? $user->address ?? 'N/A';
            $city = $customerInfo['city'] ?? $user->city ?? 'Lima';
            $state = $customerInfo['state'] ?? $user->city ?? 'Lima';
            $country = $customerInfo['country'] ?? 'PE';
            $zipCode = $customerInfo['zipCode'] ?? $user->postal_code ?? '15000';
            $identityType = $customerInfo['identityType'] ?? 'DNI';
            $identityCode = $customerInfo['identityCode'] ?? $phoneNumber;

            Log::info('Customer data for Izipay', [
                'firstName' => $firstName,
                'lastName' => $lastName,
                'email' => $email,
                'phoneNumber' => $phoneNumber,
                'identityType' => $identityType,
                'identityCode' => $identityCode,
                'address' => $address,
                'city' => $city,
                'country' => $country
            ]);

            // Configuración para el nuevo SDK
            $iziConfig = [
                'config' => [
                    'transactionId' => $transactionId,
                    'action' => 'pay',
                    'merchantCode' => $this->shopId,
                    'order' => [
                        'orderNumber' => $order->order_number,
                        'currency' => 'PEN',
                        'amount' => number_format($order->total, 2, '.', ''),
                        'processType' => 'AT',
                        'merchantBuyerId' => (string)$user->id,
                        'dateTimeTransaction' => now()->format('Y-m-d H:i:s')
                    ],
                    // URLs de retorno para 3D Secure
                    'returnUrl' => config('app.frontend_url') . '/payment/success',
                    'notificationUrl' => config('app.url') . '/api/v1/webhook/izipay',
                    'challengeReturnUrl' => config('app.frontend_url') . '/payment/success',
                    'threeDSRequestorURL' => config('app.frontend_url'),
                    'billing' => [
                        'firstName' => $firstName,
                        'lastName' => $lastName,
                        'email' => $email,
                        'phoneNumber' => $phoneNumber,
                        'street' => $address,
                        'city' => $city,
                        'state' => $state,
                        'country' => $country,
                        'postalCode' => $zipCode,
                        'documentType' => $identityType,
                        'document' => $identityCode
                    ],
                    'shipping' => [
                        'firstName' => $firstName,
                        'lastName' => $lastName,
                        'email' => $email,
                        'phoneNumber' => $phoneNumber,
                        'street' => $address,
                        'city' => $city,
                        'state' => $state,
                        'country' => $country,
                        'postalCode' => $zipCode,
                        'documentType' => $identityType,
                        'document' => $identityCode
                    ]
                ]
            ];

            $payload = [
                'requestSource' => 'ECOMMERCE',
                'merchantCode' => $this->shopId,
                'orderNumber' => $order->order_number,
                'publicKey' => $this->getCurrentPublicKey(),
                'amount' => (int)round($order->total * 100),
                'currency' => 'PEN',
                'transactionId' => $transactionId,
                'config' => $iziConfig
            ];

            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'Authorization' => 'Basic ' . base64_encode($this->getCurrentKey() . ':' . $this->getCurrentPassword())
            ])
            ->withOptions([
                'verify' => config('app.env') === 'production' // Solo verificar SSL en producción
            ])
            ->timeout(30)
            ->post($url, $payload);

            Log::info('SDK token generation response', [
                'status' => $response->status(),
                'response_body' => $response->body()
            ]);

            if ($response->successful()) {
                $data = $response->json();

                if (isset($data['response']['token'])) {
                    return [
                        'success' => true,
                        'authorization' => $data['response']['token'],
                        'keyRSA' => $this->getCurrentPublicKey(),
                        'config' => $iziConfig
                    ];
                }
            }

            return ['success' => false, 'error' => 'Token generation failed'];

        } catch (\Exception $e) {
            Log::error('Exception during SDK token generation', [
                'error' => $e->getMessage(),
                'transaction_id' => $transactionId
            ]);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Crear pago con formulario tradicional API V4 - FUNCIONA CORRECTAMENTE
     */
    private function createFormPayment(Order $order, User $user): array
    {
        $transactionId = 'FORM_' . time() . '_' . $order->id;

        // Preparar datos del cliente con información extendida si está disponible
        $customerData = $this->prepareCustomerData($order, $user);

        // Generar form token con API V4 que funciona
        $formToken = $this->createFormToken($order->id, (int)round($order->total * 100), $order->order_number, $customerData);

        if (!$formToken) {
            \Log::error('Failed to create form token', ['order_id' => $order->id]);
            return ['success' => false, 'error' => 'Failed to create form token'];
        }

        // Crear registro en payments (ahora siempre es una orden real)
        $metadata = [
            'environment' => $this->environment,
            'shop_id' => $this->shopId,
            'transaction_id' => $transactionId,
            'integration_mode' => 'form',
            'customer_data' => $customerData,
            'created_at' => now()->toISOString()
        ];

        $payment = Payment::create([
            'order_id' => $order->id,
            'payment_method' => 'izipay',
            'amount' => $order->total,
            'currency' => 'PEN',
            'status' => 'pending',
            'payment_status' => 'pending',
            'transaction_id' => $transactionId,
            'session_token' => $formToken,
            'metadata' => json_encode($metadata)
        ]);

        Log::info('Form payment created successfully', [
            'payment_id' => $payment->id,
            'transaction_id' => $transactionId,
            'order_status' => $order->status
        ]);

        return [
            'success' => true,
            'payment_id' => $payment->id,
            'form_token' => $formToken,
            'public_key' => $this->getCurrentPublicKey(),
            'transaction_id' => $transactionId,
            'integration_mode' => 'form'
        ];
    }

    /**
     * Preparar datos del cliente utilizando información extendida
     */
    private function prepareCustomerData(Order $order, User $user): array
    {
        // Intentar obtener información del customer_info de la orden (nueva info extendida)
        $customerInfo = [];
        try {
            if (isset($order->customer_info)) {
                $customerInfo = is_array($order->customer_info) ? $order->customer_info : json_decode($order->customer_info, true) ?? [];
            }
        } catch (\Exception $e) {
            Log::info('customer_info field not available, using fallback data', ['error' => $e->getMessage()]);
            $customerInfo = [];
        }

        // Obtener información del shipping_address de la orden
        $shippingAddress = [];
        try {
            if (isset($order->shipping_address)) {
                $shippingAddress = is_array($order->shipping_address) ? $order->shipping_address : json_decode($order->shipping_address, true) ?? [];
            }
        } catch (\Exception $e) {
            Log::info('shipping_address parsing failed, using empty array', ['error' => $e->getMessage()]);
            $shippingAddress = [];
        }

        // Obtener información del billing_address de la orden
        $billingAddress = [];
        try {
            if (isset($order->billing_address)) {
                $billingAddress = is_array($order->billing_address) ? $order->billing_address : json_decode($order->billing_address, true) ?? [];
            }
        } catch (\Exception $e) {
            Log::info('billing_address field not available, using empty array', ['error' => $e->getMessage()]);
            $billingAddress = [];
        }

        // Usar datos de customer_info si están disponibles (prioridad máxima)
        if (!empty($customerInfo)) {
            $firstName = $customerInfo['firstName'] ?? 'Cliente';
            $lastName = $customerInfo['lastName'] ?? 'Anonimo';
            $phone = $customerInfo['phoneNumber'] ?? $user->phone ?? '999999999';
            $address = $customerInfo['address'] ?? $user->address ?? 'Lima';
            $city = $customerInfo['city'] ?? $user->city ?? 'Lima';
            $zipCode = $customerInfo['zipCode'] ?? $user->postal_code ?? '15000';
            $identityType = $customerInfo['identityType'] ?? 'DNI';
            $identityCode = $customerInfo['identityCode'] ?? $user->phone ?? '12345678';
            $email = $customerInfo['email'] ?? $user->email;
        }
        // Si no hay customer_info, usar datos del shipping_address
        else if (!empty($shippingAddress)) {
            $fullName = $shippingAddress['name'] ?? $user->name;
            if ($fullName && str_contains($fullName, ' ')) {
                $nameParts = explode(' ', $fullName, 2);
                $firstName = $nameParts[0];
                $lastName = $nameParts[1];
            } else {
                $firstName = $fullName ?: 'Cliente';
                $lastName = 'Anonimo';
            }

            $phone = $shippingAddress['phone'] ?? ($user ? $user->phone : null) ?? '999999999';
            $address = $shippingAddress['address'] ?? ($user ? $user->address : null) ?? 'Lima';
            $city = $shippingAddress['city'] ?? ($user ? $user->city : null) ?? 'Lima';
            $zipCode = $shippingAddress['postal_code'] ?? ($user ? $user->postal_code : null) ?? '15000';
            $identityType = 'DNI';
            $identityCode = ($user ? $user->phone : null) ?? '12345678';
            $email = $user ? $user->email : null;
        }
        // Datos por defecto del usuario o valores de fallback para invitados
        else {
            if ($user) {
                $firstName = explode(' ', $user->name)[0] ?? 'Cliente';
                $lastName = substr($user->name, strpos($user->name, ' ') + 1) ?: 'Anonimo';
                $phone = $user->phone ?? '999999999';
                $address = $user->address ?? 'Lima';
                $city = $user->city ?? 'Lima';
                $zipCode = $user->postal_code ?? '15000';
                $identityType = 'DNI';
                $identityCode = $user->phone ?? '12345678';
                $email = $user->email;
            } else {
                // Guest user fallback
                $firstName = 'Cliente';
                $lastName = 'Invitado';
                $phone = '999999999';
                $address = 'Lima';
                $city = 'Lima';
                $zipCode = '15000';
                $identityType = 'DNI';
                $identityCode = '12345678';
                $email = null;
            }
        }

        Log::info('Customer data prepared for Izipay', [
            'order_id' => $order->id,
            'has_customer_info' => !empty($customerInfo),
            'customer_data' => [
                'firstName' => $firstName,
                'lastName' => $lastName,
                'email' => $email,
                'phone' => $phone,
                'identityType' => $identityType,
                'identityCode' => $identityCode,
                'address' => $address,
                'city' => $city,
                'zipCode' => $zipCode
            ]
        ]);

        // Preparar datos para Izipay
        return [
            'email' => $email,
            'billingDetails' => [
                'firstName' => $firstName,
                'lastName' => $lastName,
                'phoneNumber' => $phone,
                'identityType' => $identityType,
                'identityCode' => $identityCode,
                'address' => $address,
                'country' => 'PE',
                'city' => $city,
                'state' => $city,
                'zipCode' => $zipCode
            ]
        ];
    }

    /**
     * Crear form token usando API V4 - MÉTODO QUE FUNCIONA
     */
    public function createFormToken($orderId, $amountInCents, $orderNumber, $customerData = [])
    {
        try {
            Log::info('Starting form token creation', [
                'orderId' => $orderId,
                'amountInCents' => $amountInCents,
                'orderNumber' => $orderNumber,
                'environment' => $this->environment,
                'username' => $this->username
            ]);

            // API V4 que funciona correctamente
            $url = ($this->environment === 'test' || $this->environment === 'TEST')
                ? "https://api.micuentaweb.pe/api-payment/V4/Charge/CreatePayment"
                : "https://api.micuentaweb.pe/api-payment/V4/Charge/CreatePayment";

            $auth = $this->username . ":" . $this->getCurrentPassword();

            // Obtener clave pública actual
            $currentPublicKey = $this->getCurrentPublicKey();

            // Preparar datos del cliente desde customerData
            $billingData = $customerData['billingDetails'] ?? [];
            $firstName = $billingData['firstName'] ?? 'Cliente';
            $lastName = $billingData['lastName'] ?? 'Anonimo';
            $email = $customerData['email'] ?? 'cliente@example.com';
            $phone = $billingData['phoneNumber'] ?? '999999999';
            $address = $billingData['address'] ?? 'Lima';
            $city = $billingData['city'] ?? 'Lima';
            $zipCode = $billingData['zipCode'] ?? '15000';
            $document = $billingData['identityCode'] ?? $phone;

            Log::info('Customer data prepared for Izipay API', [
                'firstName' => $firstName,
                'lastName' => $lastName,
                'email' => $email,
                'phone' => $phone,
                'city' => $city
            ]);

            Log::info('Creating Izipay form token with V4 API (CONFIGURED FOR MULTIPLE PAYMENT METHODS)', [
                'url' => $url,
                'environment' => $this->environment,
                'username' => $this->username,
                'body' => [
                    'amount' => $amountInCents,
                    'currency' => 'PEN',
                    'orderId' => $orderNumber,
                    'customer' => [
                        'email' => $email,
                        'billingDetails' => [
                            'firstName' => $firstName,
                            'lastName' => $lastName,
                            'phoneNumber' => $phone,
                            'identityType' => 'DNI',
                            'identityCode' => $document,
                            'address' => $address,
                            'country' => 'PE',
                            'city' => $city,
                            'state' => $city,
                            'zipCode' => $zipCode
                        ]
                    ]
                ]
            ]);

            // Payload para generar form token V4
            $payload = [
                'amount' => $amountInCents,
                'currency' => 'PEN',
                'orderId' => $orderNumber,
                'customer' => [
                    'email' => $email,
                    'billingDetails' => [
                        'firstName' => $firstName,
                        'lastName' => $lastName,
                        'phoneNumber' => $phone,
                        'identityType' => 'DNI',
                        'identityCode' => $document,
                        'address' => $address,
                        'country' => 'PE',
                        'city' => $city,
                        'state' => $city,
                        'zipCode' => $zipCode
                    ]
                ]
            ];

            $response = Http::timeout(30)
                ->retry(2, 1000)
                ->withOptions([
                    'verify' => config('app.env') === 'production' // Solo verificar SSL en producción
                ])
                ->withHeaders([
                    'Authorization' => 'Basic ' . base64_encode($auth),
                    'Content-Type' => 'application/json'
                ])->post($url, $payload);

            Log::info('Izipay API Response', [
                'http_code' => $response->status(),
                'response' => $response->body(),
                'curl_error' => '',
                'public_key_to_return' => substr($currentPublicKey, 0, 40) . '...'
            ]);

            if (!$response->successful()) {
                Log::error('Izipay API request failed', [
                    'status' => $response->status(),
                    'response' => $response->body(),
                    'url' => $url
                ]);
                return null;
            }

            $responseData = $response->json();

            // Para la API V4, el token viene en answer.formToken
            if (isset($responseData['answer']['formToken'])) {
                $formToken = $responseData['answer']['formToken'];
                Log::info('Form token created successfully', [
                    'token_length' => strlen($formToken)
                ]);

                return $formToken;
            }

            Log::error('Form token not found in response', ['response' => $responseData]);
            return null;

        } catch (\Exception $e) {
            Log::error('Exception during form token creation', [
                'error' => $e->getMessage(),
                'url' => $url ?? 'unknown'
            ]);
            return null;
        }
    }

    /**
     * Preparar datos para el formulario tradicional
     */
    private function prepareFormData(Order $order, ?User $user, string $transactionId): array
    {
        $formData = [
            'vads_version' => 'V2',
            'vads_action_mode' => 'INTERACTIVE',
            'vads_amount' => (int)round($order->total * 100),
            'vads_capture_delay' => '0',
            'vads_currency' => '604', // PEN
            'vads_ctx_mode' => strtoupper($this->environment),
            'vads_order_id' => $order->order_number,
            'vads_page_action' => 'PAYMENT',
            'vads_payment_config' => 'SINGLE',
            'vads_site_id' => $this->shopId,
            'vads_trans_date' => gmdate('YmdHis'),
            'vads_trans_id' => $this->generateTransactionId(),
            'vads_url_cancel' => env('FRONTEND_URL') . '/payment/cancelled',
            'vads_url_error' => env('FRONTEND_URL') . '/payment/error',
            'vads_url_refused' => env('FRONTEND_URL') . '/payment/error',
            'vads_url_success' => env('FRONTEND_URL') . '/payment/success',
            'vads_return_mode' => 'GET',
        ];

        // Añadir información del cliente
        if ($user) {
            $customerName = explode(' ', $user->name ?? '', 2);
            $firstName = $customerName[0] ?? '';
            $lastName = $customerName[1] ?? $customerName[0] ?? '';

            $formData = array_merge($formData, [
                'vads_cust_email' => $user->email,
                'vads_cust_first_name' => $firstName,
                'vads_cust_last_name' => $lastName,
                'vads_cust_phone' => $user->phone ?? '',
                'vads_cust_address' => $user->address ?? '',
                'vads_cust_city' => $user->city ?? '',
                'vads_cust_country' => 'PE',
                'vads_language' => 'es'
            ]);
        }

        // Generar firma
        $formData['signature'] = $this->generateSignature($formData);

        return $formData;
    }

    /**
     * Crear sesión simulada para desarrollo
     */
    private function createMockSession(Order $order, User $user): array
    {
        $transactionId = 'MOCK_' . time() . rand(1000, 9999);
        $mockToken = 'MOCK_TOKEN_' . $transactionId;

        // Crear registro en payments (ahora siempre es una orden real)
        $metadata = [
            'transaction_id' => $transactionId,
            'mock_mode' => true,
            'environment' => 'mock',
            'integration_mode' => $this->integrationMode
        ];

        $payment = Payment::create([
            'order_id' => $order->id,
            'payment_method' => 'izipay',
            'transaction_id' => $transactionId,
            'amount' => $order->total,
            'currency' => 'PEN',
            'status' => 'pending',
            'payment_status' => 'pending',
            'session_token' => $mockToken,
            'metadata' => json_encode($metadata)
        ]);

        return [
            'success' => true,
            'payment_id' => $payment->id,
            'form_token' => $mockToken,
            'authorization' => $mockToken,
            'public_key' => 'MOCK_PUBLIC_KEY',
            'keyRSA' => 'MOCK_RSA_KEY',
            'endpoint' => 'mock://payment-endpoint',
            'transaction_id' => $transactionId,
            'amount' => $order->total,
            'currency' => 'PEN',
            'integration_mode' => 'mock'
        ];
    }

    /**
     * Generar ID de transacción de 6 dígitos
     */
    private function generateTransactionId(): string
    {
        return str_pad((string)mt_rand(1, 999999), 6, '0', STR_PAD_LEFT);
    }

    /**
     * Generar firma para el formulario
     */
    private function generateSignature(array $formData): string
    {
        $vadsFields = [];
        foreach ($formData as $key => $value) {
            if (strpos($key, 'vads_') === 0) {
                $vadsFields[$key] = $value;
            }
        }

        ksort($vadsFields);
        $signatureString = implode('+', $vadsFields) . '+' . $this->getCurrentKey();

        return base64_encode(hash('sha1', $signatureString, true));
    }

    /**
     * Verificar estado del pago
     */
    public function verifyPayment(string $transactionId): array
    {
        try {
            Log::info('Verifying payment', ['transaction_id' => $transactionId]);

            if (str_starts_with($transactionId, 'MOCK_')) {
                return [
                    'success' => true,
                    'data' => [
                        'transactionDetails' => [
                            'status' => 'PAID',
                            'amount' => 0,
                            'currency' => 'PEN'
                        ]
                    ]
                ];
            }

            $url = $this->getCurrentApiEndpoint() . '/api-payment/V4/Transaction/Get';
            $credentials = base64_encode($this->getCurrentKey() . ':' . $this->getCurrentPassword());

            $response = Http::withHeaders([
                'Authorization' => 'Basic ' . $credentials,
                'Content-Type' => 'application/json'
            ])
            ->withOptions([
                'verify' => config('app.env') === 'production' // Solo verificar SSL en producción
            ])
            ->post($url, [
                'uuid' => $transactionId
            ]);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'data' => $response->json()
                ];
            }

            return [
                'success' => false,
                'error' => 'Payment verification failed'
            ];

        } catch (\Exception $e) {
            Log::error('Payment verification failed', [
                'transaction_id' => $transactionId,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Procesar webhook de Izipay
     */
    public function processWebhook(array $data): array
    {
        try {
            Log::info('Processing Izipay webhook', $data);

            $transactionId = $data['kr-answer']['orderDetails']['orderNumber'] ?? null;
            if (!$transactionId) {
                throw new \Exception('Transaction ID not found in webhook data');
            }

            $payment = Payment::where('transaction_id', $transactionId)->first();
            if (!$payment) {
                throw new \Exception('Payment not found for transaction: ' . $transactionId);
            }

            $status = $data['kr-answer']['transactionDetails']['status'] ?? 'UNKNOWN';

            $paymentStatus = match($status) {
                'PAID' => 'completed',
                'CANCELLED', 'ABANDONED' => 'cancelled',
                'REFUSED' => 'failed',
                default => 'pending'
            };

            $payment->update([
                'payment_status' => $paymentStatus,
                'status' => $paymentStatus,
                'payment_details' => $data,
                'paid_at' => $paymentStatus === 'completed' ? now() : null
            ]);

            if ($paymentStatus === 'completed') {
                $payment->order->update([
                    'status' => 'confirmed'
                ]);
            }

            return ['success' => true];

        } catch (\Exception $e) {
            Log::error('Webhook processing failed', [
                'error' => $e->getMessage(),
                'data' => $data
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Verificar respuesta del formulario
     */
    public function verifyFormResponse(array $responseData): array
    {
        try {
            $receivedSignature = $responseData['signature'] ?? '';
            unset($responseData['signature']);

            $calculatedSignature = $this->generateSignature($responseData);

            if (!hash_equals($calculatedSignature, $receivedSignature)) {
                throw new \Exception('Invalid signature');
            }

            $transactionStatus = $responseData['vads_trans_status'] ?? '';
            $orderId = $responseData['vads_order_id'] ?? '';

            $payment = Payment::whereHas('order', function($query) use ($orderId) {
                $query->where('order_number', $orderId);
            })->first();

            if (!$payment) {
                throw new \Exception('Payment not found for order: ' . $orderId);
            }

            $paymentStatus = match($transactionStatus) {
                'AUTHORISED' => 'completed',
                'CANCELLED' => 'cancelled',
                'REFUSED' => 'failed',
                default => 'pending'
            };

            $payment->update([
                'payment_status' => $paymentStatus,
                'status' => $paymentStatus,
                'payment_details' => $responseData,
                'paid_at' => $paymentStatus === 'completed' ? now() : null
            ]);

            if ($paymentStatus === 'completed') {
                $payment->order->update([
                    'status' => 'confirmed'
                ]);
            }

            return [
                'success' => true,
                'payment_status' => $paymentStatus,
                'payment_id' => $payment->id
            ];

        } catch (\Exception $e) {
            Log::error('Form response verification failed', [
                'error' => $e->getMessage(),
                'data' => $responseData
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Verificar firma HMAC del webhook
     */
    public function verifyWebhookSignature(array $data, ?string $signature): bool
    {
        if (!$signature) {
            return false;
        }

        $hmacKey = $this->getCurrentHmacKey();
        $calculatedSignature = hash_hmac('sha256', json_encode($data), $hmacKey);

        return hash_equals($calculatedSignature, $signature);
    }

    /**
     * Crear token de sesión para el NUEVO SDK de Izipay (soporta múltiples métodos de pago)
     * Basado en la documentación oficial de Izipay
     */
    public function createSessionToken($orderId, $amountInCents, $orderNumber, $customerData = [])
    {
        try {
            // URL correcta para generar token de sesión (no form token)
            $url = ($this->environment === 'test' || $this->environment === 'TEST')
                ? "https://api.micuentaweb.pe/security/v1/Token/Generate"
                : "https://api-pw.izipay.pe/security/v1/Token/Generate";

            $auth = $this->username . ":" . $this->getCurrentPassword();

            // Obtener clave pública actual
            $currentPublicKey = $this->getCurrentPublicKey();

            // Preparar datos del cliente desde customerData
            $billingData = $customerData['billingDetails'] ?? [];
            $firstName = $billingData['firstName'] ?? 'Cliente';
            $lastName = $billingData['lastName'] ?? 'Anonimo';
            $email = $customerData['email'] ?? 'cliente@example.com';
            $phone = $billingData['phoneNumber'] ?? '999999999';
            $address = $billingData['address'] ?? 'Lima';
            $city = $billingData['city'] ?? 'Lima';
            $zipCode = $billingData['zipCode'] ?? '15000';
            $document = $billingData['identityCode'] ?? $phone;

            // Configuración según la documentación oficial de Izipay
            $iziConfig = [
                'config' => [
                    'transactionId' => 'TXN-' . time() . '-' . $orderId,
                    'action' => 'pay',
                    'merchantCode' => $this->shopId,
                    'order' => [
                        'orderNumber' => $orderNumber,
                        'currency' => 'PEN',
                        'amount' => number_format($amountInCents / 100, 2, '.', ''),
                        'processType' => 'AT',
                        'merchantBuyerId' => $this->shopId,
                        'dateTimeTransaction' => now()->format('Y-m-d H:i:s')
                    ],
                    'billing' => [
                        'firstName' => $firstName,
                        'lastName' => $lastName,
                        'email' => $email,
                        'phoneNumber' => $phone,
                        'street' => $address,
                        'city' => $city,
                        'state' => $city,
                        'country' => 'PE',
                        'postalCode' => $zipCode,
                        'documentType' => 'DNI',
                        'document' => $document
                    ],
                    'shipping' => [
                        'firstName' => $firstName,
                        'lastName' => $lastName,
                        'email' => $email,
                        'phoneNumber' => $phone,
                        'street' => $address,
                        'city' => $city,
                        'state' => $city,
                        'country' => 'PE',
                        'postalCode' => $zipCode,
                        'documentType' => 'DNI',
                        'document' => $document . '9'
                    ]
                ]
            ];

            Log::info('Creating Izipay SESSION TOKEN with NEW SDK API (MULTIPLE PAYMENT METHODS)', [
                'url' => $url,
                'environment' => $this->environment,
                'username' => $this->username,
                'public_key' => substr($currentPublicKey, 0, 40) . '...',
                'merchant_code' => $this->shopId,
                'amount' => $amountInCents,
                'order_number' => $orderNumber,
                'config_preview' => [
                    'transactionId' => $iziConfig['config']['transactionId'],
                    'merchantCode' => $iziConfig['config']['merchantCode'],
                    'amount' => $iziConfig['config']['order']['amount']
                ]
            ]);

            // Payload para generar token de sesión
            $payload = [
                'requestSource' => 'ECOMMERCE',
                'merchantCode' => $this->shopId,
                'orderNumber' => $orderNumber,
                'publicKey' => $currentPublicKey,
                'amount' => $amountInCents,
                'currency' => 'PEN',
                'transactionId' => $iziConfig['config']['transactionId'],
                'config' => $iziConfig
            ];

            $response = Http::timeout(60)
                ->retry(3, 2000)
                ->withOptions([
                    'verify' => config('app.env') === 'production' // Solo verificar SSL en producción
                ])
                ->withHeaders([
                    'Authorization' => 'Basic ' . base64_encode($auth),
                    'Content-Type' => 'application/json'
                ])->post($url, $payload);

            Log::info('Izipay SESSION TOKEN API Response', [
                'http_code' => $response->status(),
                'response' => $response->body(),
                'public_key_sent' => substr($currentPublicKey, 0, 40) . '...'
            ]);

            if (!$response->successful()) {
                Log::error('Izipay SESSION TOKEN API request failed', [
                    'status' => $response->status(),
                    'response' => $response->body(),
                    'url' => $url
                ]);
                return null;
            }

            $responseData = $response->json();

            // Para el nuevo SDK, el token viene en response.token
            if (isset($responseData['response']['token'])) {
                $sessionToken = $responseData['response']['token'];
                Log::info('Session token created successfully', [
                    'token_length' => strlen($sessionToken),
                    'public_key_ready' => substr($currentPublicKey, 0, 40) . '...',
                    'sdk_type' => 'NEW_IZIPAY_SDK_MULTIPLE_METHODS'
                ]);

                return [
                    'session_token' => $sessionToken,
                    'public_key' => $currentPublicKey,
                    'config' => $iziConfig
                ];
            }

            Log::error('Session token not found in response', ['response' => $responseData]);
            return null;

        } catch (\Exception $e) {
            Log::error('Exception during session token creation', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'url' => $url ?? 'unknown'
            ]);
            return null;
        }
    }

    // Métodos helper para obtener credenciales actuales
    private function getCurrentKey(): string
    {
        return $this->environment === 'test' ? $this->testKey : $this->prodKey;
    }

    private function getCurrentPassword(): string
    {
        return ($this->environment === 'test' || $this->environment === 'TEST') ? $this->testPassword : $this->prodPassword;
    }

    public function getCurrentPublicKey(): string
    {
        return ($this->environment === 'test' || $this->environment === 'TEST') ? $this->testPublicKey : $this->prodPublicKey;
    }

    private function getCurrentHmacKey(): string
    {
        return $this->environment === 'test' ? $this->testHmacKey : $this->prodHmacKey;
    }

    private function getCurrentApiEndpoint(): string
    {
        return $this->environment === 'test'
            ? 'https://api.micuentaweb.pe'
            : 'https://api-pw.izipay.pe';
    }

    private function getFormEndpoint(): string
    {
        return $this->environment === 'test'
            ? 'https://sandbox-payment.izipay.pe/vads-payment/'
            : 'https://payment.izipay.pe/vads-payment/';
    }

    /**
     * Crear pago con formulario tradicional desde DraftOrder
     */
    private function createFormPaymentFromDraft(\App\Models\DraftOrder $draftOrder, User $user): array
    {
        $transactionId = 'DRAFT_FORM_' . time() . '_' . $draftOrder->id;

        // Preparar datos del cliente desde el draft
        $customerData = $this->prepareCustomerDataFromDraft($draftOrder, $user);

        // Generar form token con API V4 usando el draft_number
        $formToken = $this->createFormToken($draftOrder->id, (int)round($draftOrder->total * 100), $draftOrder->draft_number, $customerData);

        if (!$formToken) {
            \Log::error('Failed to create form token from draft', ['draft_id' => $draftOrder->id]);
            return ['success' => false, 'error' => 'Failed to create form token'];
        }

        // Crear registro en DraftPayment (tabla temporal) en lugar de Payment
        $metadata = [
            'environment' => $this->environment,
            'shop_id' => $this->shopId,
            'transaction_id' => $transactionId,
            'integration_mode' => 'form',
            'customer_data' => $customerData,
            'draft_id' => $draftOrder->id,
            'draft_number' => $draftOrder->draft_number,
            'created_at' => now()->toISOString()
        ];

        // Almacenar información de pago temporalmente en la sesión o cache
        cache()->put("draft_payment_{$draftOrder->draft_number}", [
            'draft_id' => $draftOrder->id,
            'transaction_id' => $transactionId,
            'form_token' => $formToken,
            'metadata' => $metadata,
            'expires_at' => now()->addHours(2)
        ], 7200); // 2 horas de expiración

        Log::info('Draft payment session created successfully', [
            'draft_id' => $draftOrder->id,
            'draft_number' => $draftOrder->draft_number,
            'transaction_id' => $transactionId
        ]);

        return [
            'success' => true,
            'form_token' => $formToken,
            'public_key' => $this->getCurrentPublicKey(),
            'transaction_id' => $transactionId,
            'integration_mode' => 'form',
            'draft_number' => $draftOrder->draft_number
        ];
    }

    /**
     * Crear pago con SDK desde DraftOrder
     */
    private function createSDKPaymentFromDraft(\App\Models\DraftOrder $draftOrder, User $user): array
    {
        $transactionId = 'DRAFT_SDK_' . time() . '_' . $draftOrder->id;

        // Generar token con el nuevo SDK approach usando draft
        $sessionData = $this->generateNewSDKSessionFromDraft($draftOrder, $user, $transactionId);

        if (!$sessionData['success']) {
            Log::error('Failed to generate session with new SDK from draft');
            return ['success' => false, 'error' => 'Failed to generate session token'];
        }

        // Almacenar información de pago temporalmente
        $metadata = [
            'environment' => $this->environment,
            'shop_id' => $this->shopId,
            'transaction_id' => $transactionId,
            'ctx_mode' => $this->ctxMode,
            'integration_mode' => 'sdk',
            'draft_id' => $draftOrder->id,
            'draft_number' => $draftOrder->draft_number,
            'created_at' => now()->toISOString()
        ];

        cache()->put("draft_payment_{$draftOrder->draft_number}", [
            'draft_id' => $draftOrder->id,
            'transaction_id' => $transactionId,
            'session_token' => $sessionData['authorization'],
            'metadata' => $metadata,
            'expires_at' => now()->addHours(2)
        ], 7200);

        Log::info('Draft SDK payment session created successfully', [
            'draft_id' => $draftOrder->id,
            'draft_number' => $draftOrder->draft_number,
            'transaction_id' => $transactionId
        ]);

        return [
            'success' => true,
            'form_token' => $sessionData['authorization'],
            'public_key' => $this->getCurrentPublicKey(),
            'authorization' => $sessionData['authorization'],
            'keyRSA' => $sessionData['keyRSA'],
            'endpoint' => $this->environment === 'test'
                ? 'https://sandbox-checkout.izipay.pe'
                : 'https://checkout.izipay.pe',
            'transaction_id' => $transactionId,
            'amount' => $draftOrder->total,
            'currency' => 'PEN',
            'config' => $sessionData['config'],
            'integration_mode' => 'sdk',
            'draft_number' => $draftOrder->draft_number
        ];
    }

    /**
     * Crear sesión simulada desde DraftOrder
     */
    private function createMockSessionFromDraft(\App\Models\DraftOrder $draftOrder, User $user): array
    {
        $transactionId = 'DRAFT_MOCK_' . time() . rand(1000, 9999);
        $mockToken = 'MOCK_TOKEN_' . $transactionId;

        // Almacenar información temporalmente
        $metadata = [
            'transaction_id' => $transactionId,
            'mock_mode' => true,
            'environment' => 'mock',
            'integration_mode' => $this->integrationMode,
            'draft_id' => $draftOrder->id,
            'draft_number' => $draftOrder->draft_number
        ];

        cache()->put("draft_payment_{$draftOrder->draft_number}", [
            'draft_id' => $draftOrder->id,
            'transaction_id' => $transactionId,
            'session_token' => $mockToken,
            'metadata' => $metadata,
            'expires_at' => now()->addHours(2)
        ], 7200);

        return [
            'success' => true,
            'form_token' => $mockToken,
            'authorization' => $mockToken,
            'public_key' => 'MOCK_PUBLIC_KEY',
            'keyRSA' => 'MOCK_RSA_KEY',
            'endpoint' => 'mock://payment-endpoint',
            'transaction_id' => $transactionId,
            'amount' => $draftOrder->total,
            'currency' => 'PEN',
            'integration_mode' => 'mock',
            'draft_number' => $draftOrder->draft_number
        ];
    }

    /**
     * Preparar datos del cliente desde DraftOrder
     */
    private function prepareCustomerDataFromDraft(\App\Models\DraftOrder $draftOrder, User $user): array
    {
        // Obtener información del customer_info del draft
        $customerInfo = $draftOrder->customer_info ?? [];
        $shippingAddress = $draftOrder->shipping_address ?? [];
        $billingAddress = $draftOrder->billing_address ?? [];

        // Usar datos de customer_info si están disponibles
        if (!empty($customerInfo)) {
            $firstName = $customerInfo['firstName'] ?? 'Cliente';
            $lastName = $customerInfo['lastName'] ?? 'Anonimo';
            $phone = $customerInfo['phoneNumber'] ?? $user->phone ?? '999999999';
            $email = $customerInfo['email'] ?? $user->email ?? 'cliente@example.com';
            $identityType = $customerInfo['identityType'] ?? 'DNI';
            $identityCode = $customerInfo['identityCode'] ?? $phone;
        } else {
            // Fallback a datos del usuario
            $firstName = explode(' ', $user->name ?? '', 2)[0] ?? 'Cliente';
            $lastName = explode(' ', $user->name ?? '', 2)[1] ?? $firstName;
            $phone = $user->phone ?? '999999999';
            $email = $user->email ?? 'cliente@example.com';
            $identityType = 'DNI';
            $identityCode = $phone;
        }

        // Usar shipping_address para dirección
        $address = $shippingAddress['address'] ?? 'Lima, Perú';
        $city = $shippingAddress['city'] ?? 'Lima';
        $zipCode = $shippingAddress['zipCode'] ?? '15000';

        return [
            'email' => $email,
            'billingDetails' => [
                'firstName' => $firstName,
                'lastName' => $lastName,
                'phoneNumber' => $phone,
                'identityType' => $identityType,
                'identityCode' => $identityCode,
                'address' => $address,
                'city' => $city,
                'zipCode' => $zipCode
            ]
        ];
    }

    /**
     * Generar sesión SDK desde DraftOrder
     */
    private function generateNewSDKSessionFromDraft(\App\Models\DraftOrder $draftOrder, ?User $user, string $transactionId): array
    {
        try {
            $apiEndpoint = $this->environment === 'test'
                ? 'https://api.micuentaweb.pe'
                : 'https://api-pw.izipay.pe';

            $url = $apiEndpoint . '/security/v1/Token/Generate';

            // Obtener datos del cliente desde el draft
            $customerData = $this->prepareCustomerDataFromDraft($draftOrder, $user);
            $billingDetails = $customerData['billingDetails'];

            // Configuración para el nuevo SDK usando draft
            $iziConfig = [
                'config' => [
                    'transactionId' => $transactionId,
                    'action' => 'pay',
                    'merchantCode' => $this->shopId,
                    'order' => [
                        'orderNumber' => $draftOrder->draft_number,
                        'currency' => 'PEN',
                        'amount' => number_format($draftOrder->total, 2, '.', ''),
                        'processType' => 'AT',
                        'merchantBuyerId' => (string)$user->id,
                        'dateTimeTransaction' => now()->format('Y-m-d H:i:s')
                    ],
                    'returnUrl' => config('app.frontend_url') . '/payment/success',
                    'notificationUrl' => config('app.url') . '/api/v1/webhook/izipay',
                    'challengeReturnUrl' => config('app.frontend_url') . '/payment/success',
                    'threeDSRequestorURL' => config('app.frontend_url'),
                    'billing' => $billingDetails,
                    'shipping' => $billingDetails
                ]
            ];

            $payload = [
                'requestSource' => 'ECOMMERCE',
                'merchantCode' => $this->shopId,
                'orderNumber' => $draftOrder->draft_number,
                'publicKey' => $this->getCurrentPublicKey(),
                'amount' => (int)round($draftOrder->total * 100),
                'currency' => 'PEN',
                'transactionId' => $transactionId,
                'config' => $iziConfig
            ];

            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'Authorization' => 'Basic ' . base64_encode($this->getCurrentKey() . ':' . $this->getCurrentPassword())
            ])
            ->withOptions([
                'verify' => config('app.env') === 'production'
            ])
            ->timeout(30)
            ->post($url, $payload);

            Log::info('SDK token generation response for draft', [
                'draft_number' => $draftOrder->draft_number,
                'status' => $response->status(),
                'response_body' => $response->body()
            ]);

            if ($response->successful()) {
                $data = $response->json();

                if (isset($data['response']['token'])) {
                    return [
                        'success' => true,
                        'authorization' => $data['response']['token'],
                        'keyRSA' => $this->getCurrentPublicKey(),
                        'config' => $iziConfig
                    ];
                }
            }

            return ['success' => false, 'error' => 'Token generation failed'];

        } catch (\Exception $e) {
            Log::error('Exception during SDK token generation from draft', [
                'error' => $e->getMessage(),
                'draft_number' => $draftOrder->draft_number,
                'transaction_id' => $transactionId
            ]);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}
