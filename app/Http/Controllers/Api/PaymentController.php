<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Payment;
use App\Services\Payment\IzipayService;
use App\Helpers\DomainHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class PaymentController extends Controller
{
    protected $izipayService;

    public function __construct(IzipayService $izipayService)
    {
        $this->izipayService = $izipayService;
    }

    /**
     * Get payment token for an order (secure method)
     */
    public function getPaymentToken(Request $request, $orderNumber)
    {
        try {
            Log::info('PaymentController@getPaymentToken - Start', [
                'order_number' => $orderNumber,
                'has_auth_header' => $request->hasHeader('Authorization'),
                'user_agent' => $request->userAgent()
            ]);

            $user = $request->user();

            if (!$user) {
                Log::warning('PaymentController@getPaymentToken - No authenticated user', [
                    'order_number' => $orderNumber
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'Authentication required'
                ], 401);
            }

            Log::info('PaymentController@getPaymentToken - User authenticated', [
                'order_number' => $orderNumber,
                'user_id' => $user->id,
                'user_email' => $user->email
            ]);

            // Verificar si es un draft order (empieza con DRAFT-)
            if (str_starts_with($orderNumber, 'DRAFT-')) {
                return $this->getPaymentTokenForDraft($request, $orderNumber, $user);
            }

            // Find order by order number and verify ownership
            $order = Order::where('order_number', $orderNumber)
                ->where('user_id', $user->id)
                ->first();

            if (!$order) {
                Log::warning('Order not found or unauthorized access', [
                    'order_number' => $orderNumber,
                    'user_id' => $user->id
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'Order not found or unauthorized'
                ], 404);
            }

            // Check if order already has a payment
            $existingPayment = Payment::where('order_id', $order->id)
                ->where('payment_method', 'izipay')
                ->where('status', 'pending')
                ->first();

            if ($existingPayment && $existingPayment->session_token) {
                Log::info('Returning existing payment token', [
                    'order_number' => $orderNumber,
                    'payment_id' => $existingPayment->id
                ]);

                $metadata = $existingPayment->metadata ?? [];
                return response()->json([
                    'success' => true,
                    'data' => [
                        'form_token' => $existingPayment->session_token,
                        'public_key' => $metadata['public_key'] ?? $this->izipayService->getCurrentPublicKey(),
                        'payment_id' => $existingPayment->id
                    ]
                ]);
            }

            // Create new payment session
            Log::info('Creating new payment session for order', [
                'order_number' => $orderNumber,
                'order_id' => $order->id
            ]);

            $paymentSession = $this->izipayService->createPaymentSession($order, $user);

            if (!$paymentSession['success']) {
                Log::error('Failed to create payment session', [
                    'order_number' => $orderNumber,
                    'error' => $paymentSession['error'] ?? 'Unknown error'
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to create payment session'
                ], 500);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'form_token' => $paymentSession['form_token'],
                    'public_key' => $paymentSession['public_key'],
                    'payment_id' => $paymentSession['payment_id'] ?? null
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Exception in getPaymentToken', [
                'order_number' => $orderNumber,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Internal server error'
            ], 500);
        }
    }

    /**
     * Get payment token for a draft order
     */
    private function getPaymentTokenForDraft(Request $request, string $draftNumber, User $user)
    {
        try {
            Log::info('PaymentController@getPaymentTokenForDraft - Start', [
                'draft_number' => $draftNumber,
                'user_id' => $user->id,
                'user_email' => $user->email
            ]);

            // Find draft order by draft number and verify ownership
            $draftOrder = \App\Models\DraftOrder::where('draft_number', $draftNumber)
                ->where('user_id', $user->id)
                ->first();

            if (!$draftOrder) {
                Log::warning('Draft order not found or unauthorized access', [
                    'draft_number' => $draftNumber,
                    'user_id' => $user->id
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'Draft order not found or unauthorized'
                ], 404);
            }

            // Check if draft has already been converted to order
            if ($draftOrder->converted_to_order_id) {
                Log::info('Draft already converted to order, redirecting to order payment', [
                    'draft_number' => $draftNumber,
                    'order_id' => $draftOrder->converted_to_order_id
                ]);

                $realOrder = $draftOrder->convertedOrder;
                if ($realOrder) {
                    // Redirect to order payment
                    return $this->getPaymentToken($request, $realOrder->order_number);
                }
            }

            // Check if draft has expired
            if ($draftOrder->hasExpired()) {
                Log::warning('Draft order has expired', [
                    'draft_number' => $draftNumber,
                    'expires_at' => $draftOrder->expires_at
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'Draft order has expired'
                ], 410);
            }

            // Check for existing cached payment session
            $cachedPayment = cache()->get("draft_payment_{$draftNumber}");
            if ($cachedPayment) {
                Log::info('Returning cached payment token for draft', [
                    'draft_number' => $draftNumber,
                    'transaction_id' => $cachedPayment['transaction_id']
                ]);

                return response()->json([
                    'success' => true,
                    'data' => [
                        'form_token' => $cachedPayment['form_token'] ?? $cachedPayment['session_token'],
                        'public_key' => $this->izipayService->getCurrentPublicKey(),
                        'transaction_id' => $cachedPayment['transaction_id']
                    ]
                ]);
            }

            // Create new payment session from draft
            Log::info('Creating new payment session for draft', [
                'draft_number' => $draftNumber,
                'draft_id' => $draftOrder->id
            ]);

            $paymentSession = $this->izipayService->createPaymentSessionFromDraft($draftOrder, $user);

            if (!$paymentSession['success']) {
                Log::error('Failed to create payment session for draft', [
                    'draft_number' => $draftNumber,
                    'error' => $paymentSession['error'] ?? 'Unknown error'
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to create payment session'
                ], 500);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'form_token' => $paymentSession['form_token'],
                    'public_key' => $paymentSession['public_key'],
                    'transaction_id' => $paymentSession['transaction_id'] ?? null
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Exception in getPaymentTokenForDraft', [
                'draft_number' => $draftNumber,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Internal server error'
            ], 500);
        }
    }

    /**
     * Create payment session
     */
    public function createSession(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'order_id' => 'required|integer',
            'payment_method' => 'required|in:izipay'
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

            // First try to find a real order
            $order = Order::where('id', $request->order_id)
                ->where('user_id', $user->id)
                ->first();

            // If not found, try to find a draft order
            if (!$order) {
                $draftOrder = \App\Models\DraftOrder::where('id', $request->order_id)
                    ->where('user_id', $user->id)
                    ->first();

                if ($draftOrder) {
                    // Create a temporary order object for payment processing
                    $order = new Order();
                    $order->id = $draftOrder->id;
                    $order->order_number = $draftOrder->draft_number;
                    $order->total = $draftOrder->total;
                    $order->user_id = $draftOrder->user_id;
                    $order->status = 'draft'; // Special status for draft orders

                    Log::info('Using draft order for payment session', [
                        'draft_id' => $draftOrder->id,
                        'draft_number' => $draftOrder->draft_number
                    ]);
                } else {
                    return response()->json([
                        'success' => false,
                        'message' => 'Order not found or unauthorized'
                    ], 404);
                }
            }

            if ($order->status === 'delivered') {
                return response()->json([
                    'success' => false,
                    'message' => 'Order already completed'
                ], 400);
            }

            // Usar la API REST correcta como en el ejemplo
            $formToken = $this->createFormToken($order, $user);

            if (!$formToken) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to create payment token'
                ], 500);
            }

            // Crear registro de pago
            $payment = Payment::updateOrCreate(
                ['order_id' => $order->id, 'payment_method' => 'izipay'],
                [
                    'amount' => $order->total,
                    'currency' => 'PEN',
                    'status' => 'pending',
                    'payment_status' => 'pending',
                    'transaction_id' => $order->order_number,
                    'session_token' => $formToken,
                    'metadata' => json_encode([
                        'form_token' => $formToken,
                        'public_key' => config('izipay.credentials.public_key'),
                        'environment' => config('izipay.environment')
                    ])
                ]
            );

            return response()->json([
                'success' => true,
                'data' => [
                    'payment_id' => $payment->id,
                    'form_token' => $formToken,
                    'public_key' => config('izipay.credentials.public_key'),
                    'endpoint' => 'https://static.micuentaweb.pe/static/js/krypton-client/V4.0/stable/kr-payment-form.min.js',
                    'order_number' => $order->order_number,
                    'amount' => $order->total,
                    'currency' => 'PEN',
                    'checkout_url' => route('izipay.checkout'),
                    'origin' => DomainHelper::getPunycodeOrigin(request())
                ]
            ])->withHeaders([
                'Access-Control-Allow-Origin' => DomainHelper::getPunycodeOrigin(request()),
                'Access-Control-Allow-Credentials' => 'true',
                'Access-Control-Allow-Methods' => 'GET, POST, PUT, DELETE, OPTIONS',
                'Access-Control-Allow-Headers' => 'Content-Type, Authorization, X-Requested-With'
            ]);

        } catch (\Exception $e) {
            Log::error('Payment session creation failed', [
                'order_id' => $request->order_id,
                'user_id' => $request->user()->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Internal server error: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create payment session by order number (for testing and frontend compatibility)
     */
    public function createSessionByOrderNumber(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'order_number' => 'required|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation errors',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Debug: Log that we're starting
            Log::info('PaymentController: Starting createSessionByOrderNumber', [
                'order_number' => $request->order_number
            ]);

            // Find order by order number
            $order = Order::where('order_number', $request->order_number)->first();

            if (!$order) {
                Log::warning('Order not found', ['order_number' => $request->order_number]);
                return response()->json([
                    'success' => false,
                    'message' => 'Order not found with number: ' . $request->order_number
                ], 404);
            }

            Log::info('Order found, preparing payment data', [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'amount' => $order->total
            ]);

            // Prepare customer data
            $customerData = [
                'email' => $order->customer_email,
                'phone' => $order->customer_phone,
                'name' => $order->customer_name
            ];

            Log::info('Calling Izipay createFormToken', [
                'order_id' => $order->id,
                'amount_cents' => $order->total * 100,
                'order_number' => $order->order_number,
                'customer_data' => $customerData
            ]);

            // Try to create form token using Izipay service
            try {
                $formToken = $this->izipayService->createFormToken(
                    $order->id,
                    $order->total * 100, // Convert to cents
                    $order->order_number,
                    $customerData
                );

                if (!$formToken) {
                    throw new \Exception('Izipay service returned empty token');
                }

                Log::info('Payment token created successfully', [
                    'order_number' => $order->order_number,
                    'token_length' => strlen($formToken)
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Payment token created successfully',
                    'formToken' => $formToken,
                    'order_number' => $order->order_number,
                    'amount' => $order->total,
                    'currency' => 'PEN',
                    'origin' => DomainHelper::getPunycodeOrigin(request())
                ])->withHeaders([
                    'Access-Control-Allow-Origin' => '*',
                    'Access-Control-Allow-Credentials' => 'true',
                    'Access-Control-Allow-Methods' => 'GET, POST, PUT, DELETE, OPTIONS',
                    'Access-Control-Allow-Headers' => 'Content-Type, Authorization, X-Requested-With'
                ]);

            } catch (\Exception $izipayError) {
                Log::error('Izipay service error', [
                    'error' => $izipayError->getMessage(),
                    'order_number' => $order->order_number
                ]);

                // Return a fallback response for testing
                return response()->json([
                    'success' => false,
                    'message' => 'Payment service temporarily unavailable. Error: ' . $izipayError->getMessage(),
                    'order_number' => $order->order_number,
                    'amount' => $order->total,
                    'currency' => 'PEN',
                    'debug_mode' => true
                ])->withHeaders([
                    'Access-Control-Allow-Origin' => '*',
                    'Access-Control-Allow-Credentials' => 'true',
                    'Access-Control-Allow-Methods' => 'GET, POST, PUT, DELETE, OPTIONS',
                    'Access-Control-Allow-Headers' => 'Content-Type, Authorization, X-Requested-With'
                ]);
            }

        } catch (\Exception $e) {
            Log::error('Payment token creation failed', [
                'order_number' => $request->order_number,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to create payment token: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Crear form token usando la API del NUEVO SDK v1 (compatible con checkout.izipay.pe)
     */
    private function createFormToken(Order $order, $user): ?string
    {
        try {
            // URL correcta para el NUEVO SDK v1 (misma que usa tu frontend)
            $url = "https://checkout.izipay.pe/api/v1/sessions";

            $auth = env('IZIPAY_USERNAME') . ":" . env('IZIPAY_PROD_PASSWORD');

            $headers = [
                "Authorization: Basic " . base64_encode($auth),
                "Content-Type: application/json",
                "User-Agent: FloresDJazmin/1.0 Laravel"
            ];

            // Preparar datos del cliente
            $customerName = explode(' ', $user->name ?? '', 2);
            $firstName = $customerName[0] ?? 'Cliente';
            $lastName = $customerName[1] ?? $customerName[0] ?? 'Test';

            // Generar transaction ID único
            $transactionId = 'TXN-' . time() . '-' . substr(md5($order->order_number), 0, 8);

            // Estructura exacta para el nuevo SDK v1 con múltiples métodos de pago
            $body = [
                "transactionId" => $transactionId,
                "action" => "pay",
                "merchantCode" => env('IZIPAY_USERNAME'),
                "order" => [
                    "orderNumber" => $order->order_number,
                    "currency" => "PEN",
                    "amount" => number_format($order->total, 2, '.', ''), // String format
                    "processType" => "AT",
                    "merchantBuyerId" => env('IZIPAY_USERNAME'),
                    "dateTimeTransaction" => now()->toISOString(),
                    // Habilitar múltiples métodos de pago
                    "payMethod" => "CARD,PAGO_PUSH,YAPE_CODE"
                ],
                "billing" => [
                    "firstName" => $firstName,
                    "lastName" => $lastName,
                    "email" => $user->email ?? 'cliente@test.com',
                    "phoneNumber" => $user->phone ?? '987654321',
                    "street" => $user->address ?? 'Lima, Perú',
                    "city" => "Lima",
                    "state" => "Lima",
                    "country" => "PE",
                    "postalCode" => "15000",
                    "documentType" => "DNI",
                    "document" => substr(preg_replace('/\D/', '', $user->phone ?? '12345678'), 0, 8) ?: '12345678'
                ],
                "shipping" => [
                    "firstName" => $firstName,
                    "lastName" => $lastName,
                    "email" => $user->email ?? 'cliente@test.com',
                    "phoneNumber" => $user->phone ?? '987654321',
                    "street" => $user->address ?? 'Lima, Perú',
                    "city" => "Lima",
                    "state" => "Lima",
                    "country" => "PE",
                    "postalCode" => "15000",
                    "documentType" => "DNI",
                    "document" => substr(preg_replace('/\D/', '', $user->phone ?? '12345679'), 0, 8) ?: '12345679'
                ],
                "appearance" => [
                    "customize" => [
                        "elements" => [
                            [
                                "paymentMethod" => "CARD",
                                "fields" => [
                                    [
                                        "name" => "firstName",
                                        "order" => 1,
                                        "groupName" => "name"
                                    ],
                                    [
                                        "name" => "lastName",
                                        "order" => 1,
                                        "groupName" => "name"
                                    ],
                                    [
                                        "name" => "email",
                                        "order" => 2
                                    ],
                                    [
                                        "name" => "cardNumber",
                                        "order" => 3
                                    ],
                                    [
                                        "name" => "expirationDate",
                                        "order" => 4,
                                        "groupName" => "cardDetails"
                                    ],
                                    [
                                        "name" => "securityCode",
                                        "order" => 4,
                                        "groupName" => "cardDetails"
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            ];

            Log::info('Creating Izipay NEW SDK v1 token', [
                'url' => $url,
                'transactionId' => $transactionId,
                'order_number' => $order->order_number,
                'amount' => $body['order']['amount']
            ]);

            $curl = curl_init($url);
            curl_setopt($curl, CURLOPT_HEADER, false);
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($curl, CURLOPT_POST, true);
            curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($body));
            curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
            curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($curl, CURLOPT_TIMEOUT, 30);

            $raw_response = curl_exec($curl);
            $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
            $curl_error = curl_error($curl);
            curl_close($curl);

            Log::info('Izipay API Response', [
                'http_code' => $http_code,
                'response' => $raw_response,
                'curl_error' => $curl_error
            ]);

            if ($curl_error || $http_code !== 200) {
                Log::error('Izipay API Error', [
                    'curl_error' => $curl_error,
                    'http_code' => $http_code,
                    'response' => $raw_response
                ]);
                return null;
            }

            $response = json_decode($raw_response, true);

            if (isset($response["answer"]["formToken"])) {
                return $response["answer"]["formToken"];
            }

            Log::error('Invalid Izipay response structure', [
                'response' => $response
            ]);

            return null;

        } catch (\Exception $e) {
            Log::error('Form token creation failed', [
                'error' => $e->getMessage(),
                'order_id' => $order->id
            ]);
            return null;
        }
    }

    /**
     * Verify payment status
     */
    public function verifyPayment(Request $request, $paymentId)
    {
        try {
            $user = $request->user();
            $payment = Payment::where('id', $paymentId)
                ->whereHas('order', function($q) use ($user) {
                    $q->where('user_id', $user->id);
                })
                ->first();

            if (!$payment) {
                return response()->json([
                    'success' => false,
                    'message' => 'Payment not found'
                ], 404);
            }

            $result = $this->izipayService->verifyPayment($payment->transaction_id);

            if ($result['success']) {
                $transactionData = $result['data'];
                $status = $transactionData['transactionDetails']['status'] ?? 'UNKNOWN';

                return response()->json([
                    'success' => true,
                    'data' => [
                        'payment_status' => $payment->payment_status,
                        'transaction_status' => $status,
                        'order_status' => $payment->order->status,
                        'amount' => $payment->amount
                    ]
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'Failed to verify payment'
            ], 500);

        } catch (\Exception $e) {
            Log::error('Payment verification failed', [
                'payment_id' => $paymentId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Internal server error'
            ], 500);
        }
    }

    /**
     * Webhook para recibir notificaciones de Izipay
     */
    public function webhook(Request $request)
    {
        try {
            Log::info('Izipay webhook received', $request->all());

            if (empty($request->all())) {
                return response()->json(['error' => 'No data received'], 400);
            }

            // Verificar firma según el ejemplo de Izipay
            if (!$this->checkHash($request, env("IZIPAY_PROD_HMAC_KEY"))) {
                Log::warning('Invalid webhook signature', [
                    'kr-hash' => $request->input('kr-hash'),
                    'calculated_hash' => $this->calculateHash($request, env("IZIPAY_PROD_HMAC_KEY"))
                ]);
                return response()->json(['error' => 'Invalid signature'], 403);
            }

            $answer = json_decode($request->input('kr-answer'), true);

            if (!$answer) {
                Log::error('Invalid kr-answer format');
                return response()->json(['error' => 'Invalid answer format'], 400);
            }

            $orderStatus = $answer['orderStatus'] ?? 'UNKNOWN';
            $orderId = $answer['orderDetails']['orderId'] ?? null;
            $transactionUuid = $answer['transactions'][0]['uuid'] ?? null;

            Log::info('Processing webhook', [
                'orderStatus' => $orderStatus,
                'orderId' => $orderId,
                'transactionUuid' => $transactionUuid
            ]);

            if (!$orderId) {
                Log::error('Order ID not found in webhook');
                return response()->json(['error' => 'Order ID not found'], 400);
            }

            // Buscar el pago por order_number
            $payment = Payment::whereHas('order', function($q) use ($orderId) {
                $q->where('order_number', $orderId);
            })->first();

            if (!$payment) {
                Log::error('Payment not found for order', ['order_id' => $orderId]);
                return response()->json(['error' => 'Payment not found'], 404);
            }

            // Mapear estados según Izipay
            $paymentStatus = match($orderStatus) {
                'PAID' => 'completed',
                'CANCELLED', 'ABANDONED' => 'cancelled',
                'REFUSED' => 'failed',
                default => 'pending'
            };

            // Actualizar pago
            $payment->update([
                'payment_status' => $paymentStatus,
                'status' => $paymentStatus,
                'payment_details' => $answer,
                'paid_at' => $paymentStatus === 'completed' ? now() : null
            ]);

            // Actualizar orden si el pago fue exitoso
            if ($paymentStatus === 'completed') {
                $payment->order->update([
                    'status' => 'confirmed'
                ]);

                Log::info('Payment completed successfully', [
                    'payment_id' => $payment->id,
                    'order_id' => $payment->order_id,
                    'transaction_uuid' => $transactionUuid
                ]);
            }

            return response('OK! OrderStatus is ' . $orderStatus, 200);

        } catch (\Exception $e) {
            Log::error('Izipay webhook processing failed', [
                'error' => $e->getMessage(),
                'data' => $request->all()
            ]);

            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Verificar hash según el ejemplo de Izipay
     */
    private function checkHash($request, $key): bool
    {
        $krAnswer = str_replace('\/', '/', $request->input("kr-answer"));

        $calculateHash = hash_hmac("sha256", $krAnswer, $key);
        $receivedHash = $request->input("kr-hash");

        Log::info('Hash verification', [
            'calculated' => $calculateHash,
            'received' => $receivedHash,
            'valid' => $calculateHash === $receivedHash
        ]);

        return ($calculateHash === $receivedHash);
    }

    /**
     * Calcular hash para debug
     */
    private function calculateHash($request, $key): string
    {
        $krAnswer = str_replace('\/', '/', $request->input("kr-answer"));
        return hash_hmac("sha256", $krAnswer, $key);
    }

    /**
     * Get payment history
     */
    public function history(Request $request)
    {
        try {
            $user = $request->user();

            $payments = Payment::whereHas('order', function($q) use ($user) {
                    $q->where('user_id', $user->id);
                })
                ->with('order')
                ->orderBy('created_at', 'desc')
                ->paginate(15);

            return response()->json([
                'success' => true,
                'data' => $payments
            ]);

        } catch (\Exception $e) {
            Log::error('Payment history fetch failed', [
                'user_id' => $request->user()->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Internal server error'
            ], 500);
        }
    }
}
