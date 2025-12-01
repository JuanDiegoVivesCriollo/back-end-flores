<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\DirectPaymentOrder;
use App\Models\Payment;
use App\Models\GuestCustomer;
use App\Models\DeliveryDistrict;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

class PaymentController extends Controller
{
    /**
     * Create payment form token for Izipay (SIN crear orden - solo draft)
     * La orden se crea después del pago exitoso
     */
    public function createFormToken(Request $request)
    {
        $request->validate([
            'amount' => 'required|numeric|min:1',
            'currency' => 'sometimes|string|max:3',
            // Customer data
            'customer_name' => 'required|string',
            'customer_email' => 'required|email',
            'customer_phone' => 'required|string',
            'customer_document' => 'sometimes|string',
            'customer_address' => 'sometimes|string',
            // Checkout data (para guardar en metadata)
            'checkout_data' => 'sometimes|array',
        ]);

        try {
            // Generar número de orden temporal (draft)
            $draftOrderNumber = 'DRAFT-' . strtoupper(Str::random(8)) . '-' . time();
            
            // Prepare customer data
            $customerName = $request->customer_name;
            $nameParts = explode(' ', $customerName);
            $firstName = $nameParts[0] ?? 'Cliente';
            $lastName = count($nameParts) > 1 ? implode(' ', array_slice($nameParts, 1)) : $firstName;
            
            $customerData = [
                'firstName' => $firstName,
                'lastName' => $lastName,
                'email' => $request->customer_email,
                'phoneNumber' => $request->customer_phone ?? '999999999',
                'identityType' => 'DNI',
                'identityCode' => $request->customer_document ?? '12345678',
                'address' => $request->customer_address ?? 'Lima, Perú',
                'city' => 'Lima',
                'state' => 'Lima',
                'country' => 'PE',
                'zipCode' => '15000',
            ];

            // Generate form token using Izipay API
            $formTokenResult = $this->generateIzipayFormToken([
                'amount' => (float) $request->amount,
                'currency' => $request->get('currency', 'PEN'),
                'orderId' => $draftOrderNumber,
                'orderNumber' => $draftOrderNumber,
                'customer' => $customerData
            ]);

            if (!$formTokenResult['success']) {
                throw new \Exception($formTokenResult['error'] ?? 'Error al generar token de Izipay');
            }

            $formToken = $formTokenResult['formToken'];
            $transactionId = $formTokenResult['transactionId'];

            // Guardar el checkout_data completo en metadata para crear la orden después
            $metadata = [
                'transaction_id' => $transactionId,
                'draft_order_number' => $draftOrderNumber,
                'environment' => config('izipay.environment'),
                'created_at' => now()->toISOString(),
                'checkout_data' => $request->checkout_data ?? null,
                'customer' => [
                    'name' => $request->customer_name,
                    'email' => $request->customer_email,
                    'phone' => $request->customer_phone,
                    'document' => $request->customer_document,
                ],
                'amount' => $request->amount,
            ];

            // Create pending payment record (sin orden asociada aún)
            $payment = Payment::create([
                'order_id' => null,
                'direct_payment_order_id' => null,
                'session_token' => $formToken,
                'transaction_id' => $transactionId,
                'payment_method' => Payment::METHOD_CARD,
                'payment_gateway' => Payment::GATEWAY_IZIPAY,
                'amount' => $request->amount,
                'currency' => $request->get('currency', 'PEN'),
                'status' => Payment::STATUS_PENDING,
                'expires_at' => now()->addMinutes(30),
                'metadata' => json_encode($metadata),
            ]);

            return response()->json([
                'success' => true,
                'data' => [
                    'formToken' => $formToken,
                    'publicKey' => $this->getPublicKey(),
                    'payment_id' => $payment->id,
                    'transaction_id' => $transactionId,
                    'draft_order_number' => $draftOrderNumber,
                    'amount' => $request->amount,
                    'currency' => $request->get('currency', 'PEN'),
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error creating Izipay form token', [
                'error' => $e->getMessage(),
                'order_id' => $request->order_id,
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al crear el token de pago',
                'error' => config('app.debug') ? $e->getMessage() : 'Error interno del servidor'
            ], 500);
        }
    }

    /**
     * Confirm payment after client-side form submission
     * CREA LA ORDEN REAL cuando el pago es exitoso
     */
    public function confirmPayment(Request $request)
    {
        $request->validate([
            'payment_id' => 'required|integer',
            'izipay_data' => 'required|array',
            'izipay_data.rawClientAnswer' => 'required|string',
            'izipay_data.hash' => 'required|string',
            // Datos del checkout para crear la orden
            'checkout_data' => 'sometimes|array',
        ]);

        try {
            $izipayData = $request->izipay_data;

            // Verify the hash
            $isValid = $this->verifyPaymentHash(
                $izipayData['rawClientAnswer'],
                $izipayData['hash']
            );

            if (!$isValid) {
                Log::warning('Invalid payment hash', [
                    'payment_id' => $request->payment_id,
                    'hash' => $izipayData['hash']
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'Firma de pago inválida'
                ], 400);
            }

            // Parse the client answer
            $clientAnswer = json_decode($izipayData['rawClientAnswer'], true);
            $orderStatus = $clientAnswer['orderStatus'] ?? null;
            $transactionId = $clientAnswer['transactions'][0]['uuid'] ?? null;

            // Find the payment record
            $payment = Payment::find($request->payment_id);

            if (!$payment) {
                return response()->json([
                    'success' => false,
                    'message' => 'Pago no encontrado'
                ], 404);
            }

            // Update payment status based on Izipay response
            if ($orderStatus === 'PAID') {
                // Obtener checkout_data del request o del metadata del pago
                $checkoutData = $request->checkout_data;
                if (!$checkoutData && $payment->metadata) {
                    $metadata = is_string($payment->metadata) ? json_decode($payment->metadata, true) : $payment->metadata;
                    $checkoutData = $metadata['checkout_data'] ?? null;
                }

                // Crear la orden real
                $order = $this->createOrderFromPayment($payment, $checkoutData, $transactionId);

                if (!$order) {
                    throw new \Exception('Error al crear la orden');
                }

                // Update payment with order reference
                $payment->update([
                    'status' => Payment::STATUS_COMPLETED,
                    'direct_payment_order_id' => $order->id,
                    'transaction_id' => $transactionId,
                    'payment_details' => $clientAnswer,
                    'paid_at' => now(),
                ]);

                Log::info('Payment confirmed and order created', [
                    'order_number' => $order->order_number,
                    'transaction_id' => $transactionId,
                    'amount' => $order->total
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Pago confirmado y orden creada exitosamente',
                    'data' => [
                        'order_number' => $order->order_number,
                        'status' => 'confirmed',
                        'payment_status' => 'paid',
                        'transaction_id' => $transactionId,
                        'total' => $order->total,
                    ]
                ]);
            } else {
                $payment->update([
                    'status' => Payment::STATUS_FAILED,
                    'payment_details' => $clientAnswer,
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'El pago no fue completado',
                    'data' => [
                        'status' => $orderStatus,
                    ]
                ], 400);
            }

        } catch (\Exception $e) {
            Log::error('Error confirming payment', [
                'error' => $e->getMessage(),
                'payment_id' => $request->payment_id,
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al confirmar el pago: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create order from successful payment
     */
    private function createOrderFromPayment(Payment $payment, ?array $checkoutData, string $transactionId): ?DirectPaymentOrder
    {
        try {
            DB::beginTransaction();

            // Get metadata from payment
            $metadata = is_string($payment->metadata) ? json_decode($payment->metadata, true) : ($payment->metadata ?? []);
            $customerData = $metadata['customer'] ?? [];
            
            // Merge with checkout data if available
            if ($checkoutData) {
                $customerData = array_merge($customerData, $checkoutData['customer'] ?? []);
            }

            // Generate real order number
            $orderNumber = 'FDJ-' . date('Ymd') . '-' . strtoupper(Str::random(6));

            // Get delivery info
            $deliveryData = $checkoutData['delivery'] ?? [];
            $districtId = $deliveryData['district_id'] ?? null;
            $deliveryFee = 0;

            if ($districtId) {
                $district = DeliveryDistrict::find($districtId);
                $deliveryFee = $district ? $district->delivery_fee : 0;
            }

            // Create or find guest customer
            $guestCustomer = GuestCustomer::findOrCreateByContact([
                'full_name' => $customerData['name'] ?? $customerData['full_name'] ?? 'Cliente',
                'email' => $customerData['email'] ?? 'cliente@test.com',
                'phone' => $customerData['phone'] ?? '999999999',
                'dni' => $customerData['document'] ?? $customerData['dni'] ?? null,
            ]);

            // Calculate totals
            $subtotal = $payment->amount - $deliveryFee;
            
            // Create the order
            $order = DirectPaymentOrder::create([
                'order_number' => $orderNumber,
                'guest_customer_id' => $guestCustomer->id,
                'customer_name' => $guestCustomer->full_name,
                'customer_email' => $guestCustomer->email,
                'customer_phone' => $guestCustomer->phone,
                'customer_document_number' => $guestCustomer->dni ?? $customerData['document'] ?? $customerData['dni'] ?? null,
                'subtotal' => $subtotal,
                'delivery_fee' => $deliveryFee,
                'total' => $payment->amount,
                'status' => 'confirmed',
                'payment_status' => 'paid',
                'payment_method' => 'card',
                'delivery_type' => $checkoutData['delivery_type'] ?? 'delivery',
                'delivery_district_id' => $districtId,
                'shipping_address' => $deliveryData['address'] ?? null,
                'shipping_reference' => $deliveryData['reference'] ?? null,
                'recipient_name' => $deliveryData['recipient_name'] ?? null,
                'recipient_phone' => $deliveryData['recipient_phone'] ?? null,
                'delivery_date' => $deliveryData['date'] ?? null,
                'delivery_time' => $deliveryData['time_slot'] ?? null,
                'notes' => $checkoutData['notes'] ?? null,
                'dedicatory_message' => $checkoutData['dedicatory_message'] ?? null,
                'items' => json_encode($checkoutData['items'] ?? []),
                'transaction_id' => $transactionId,
            ]);

            DB::commit();

            return $order;

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error creating order from payment', [
                'error' => $e->getMessage(),
                'payment_id' => $payment->id,
            ]);
            return null;
        }
    }

    /**
     * Handle Izipay IPN (Instant Payment Notification)
     */
    public function handleIPN(Request $request)
    {
        Log::info('Izipay IPN received', $request->all());

        try {
            // Verify signature
            if (!$this->verifyIpnSignature($request)) {
                Log::warning('Invalid IPN signature');
                return response()->json(['error' => 'Invalid signature'], 400);
            }

            $krAnswer = json_decode($request->input('kr-answer'), true);
            $transactionId = $krAnswer['transactions'][0]['uuid'] ?? null;
            $orderNumber = $krAnswer['orderDetails']['orderId'] ?? null;
            $status = $krAnswer['orderStatus'] ?? null;

            // Find payment by order number
            $payment = Payment::whereHas('order', function ($q) use ($orderNumber) {
                    $q->where('order_number', $orderNumber);
                })
                ->orWhereHas('directPaymentOrder', function ($q) use ($orderNumber) {
                    $q->where('order_number', $orderNumber);
                })
                ->first();

            if (!$payment) {
                Log::error('Payment not found for IPN', ['order_number' => $orderNumber]);
                return response()->json(['error' => 'Payment not found'], 404);
            }

            // Update payment status based on Izipay response
            if ($status === 'PAID') {
                $payment->update([
                    'status' => Payment::STATUS_COMPLETED,
                    'transaction_id' => $transactionId,
                    'payment_details' => $krAnswer,
                    'paid_at' => now(),
                ]);
                
                // Update order status
                if ($payment->order) {
                    $payment->order->update(['status' => 'confirmed', 'payment_status' => 'paid']);
                }
                if ($payment->directPaymentOrder) {
                    $payment->directPaymentOrder->update(['status' => 'confirmed', 'payment_status' => 'paid']);
                }
            } else {
                $payment->update([
                    'status' => Payment::STATUS_FAILED,
                    'payment_details' => $krAnswer,
                ]);
            }

            return response('OK! OrderStatus is ' . $status, 200);

        } catch (\Exception $e) {
            Log::error('Error processing IPN', ['error' => $e->getMessage()]);
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Verify payment status
     */
    public function verifyPayment(Request $request, $paymentId)
    {
        $payment = Payment::with(['order', 'directPaymentOrder'])->findOrFail($paymentId);

        return response()->json([
            'success' => true,
            'data' => [
                'payment_id' => $payment->id,
                'status' => $payment->status,
                'is_completed' => $payment->status === Payment::STATUS_COMPLETED,
                'transaction_id' => $payment->transaction_id,
                'order_number' => $payment->order?->order_number ?? $payment->directPaymentOrder?->order_number,
            ]
        ]);
    }

    /**
     * Generate Izipay form token using the NEW SDK v1 (checkout.izipay.pe)
     */
    private function generateIzipayFormToken(array $paymentData): array
    {
        try {
            // URL for the NEW SDK v1
            $url = "https://api.micuentaweb.pe/api-payment/V4/Charge/CreatePayment";

            $username = config('izipay.credentials.username');
            $password = config('izipay.credentials.password');

            if (!$username || !$password) {
                throw new \Exception('Izipay credentials not configured');
            }

            $auth = base64_encode($username . ":" . $password);

            // Generate unique transaction ID
            $transactionId = 'TXN-' . time() . '-' . Str::random(8);

            // Build the request body according to Izipay API
            $body = [
                "amount" => (int) ($paymentData['amount'] * 100), // Convert to cents
                "currency" => $paymentData['currency'] ?? 'PEN',
                "orderId" => $paymentData['orderNumber'],
                "customer" => [
                    "email" => $paymentData['customer']['email'] ?? 'cliente@test.com',
                    "billingDetails" => [
                        "firstName" => $paymentData['customer']['firstName'] ?? 'Cliente',
                        "lastName" => $paymentData['customer']['lastName'] ?? 'Test',
                        "phoneNumber" => $paymentData['customer']['phoneNumber'] ?? '999999999',
                        "identityType" => $paymentData['customer']['identityType'] ?? 'DNI',
                        "identityCode" => $paymentData['customer']['identityCode'] ?? '12345678',
                        "address" => $paymentData['customer']['address'] ?? 'Lima',
                        "country" => $paymentData['customer']['country'] ?? 'PE',
                        "city" => $paymentData['customer']['city'] ?? 'Lima',
                        "state" => $paymentData['customer']['state'] ?? 'Lima',
                        "zipCode" => $paymentData['customer']['zipCode'] ?? '15000',
                    ]
                ],
            ];

            Log::info('Creating Izipay form token', [
                'url' => $url,
                'orderId' => $paymentData['orderNumber'],
                'amount' => $body['amount'],
                'transactionId' => $transactionId
            ]);

            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                "Authorization: Basic " . $auth,
                "Content-Type: application/json"
            ]);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);

            Log::info('Izipay API response', [
                'http_code' => $httpCode,
                'response' => $response,
                'curl_error' => $curlError
            ]);

            if ($curlError) {
                throw new \Exception("CURL Error: " . $curlError);
            }

            if ($httpCode !== 200) {
                throw new \Exception("HTTP Error: " . $httpCode . " - " . $response);
            }

            $result = json_decode($response, true);

            if (!$result || !isset($result["answer"]["formToken"])) {
                Log::error('Invalid Izipay response structure', [
                    'response' => $result
                ]);
                throw new \Exception("Invalid response from Izipay: " . ($result['answer']['errorMessage'] ?? 'Unknown error'));
            }

            return [
                'success' => true,
                'formToken' => $result["answer"]["formToken"],
                'transactionId' => $transactionId,
            ];

        } catch (\Exception $e) {
            Log::error('Form token generation failed', [
                'error' => $e->getMessage(),
                'order_id' => $paymentData['orderNumber'] ?? 'unknown'
            ]);
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Verify IPN signature
     */
    private function verifyIpnSignature(Request $request): bool
    {
        $krAnswer = str_replace('\/', '/', $request->input("kr-answer", ''));
        $krHash = $request->input("kr-hash", '');
        
        $hmacKey = config('izipay.credentials.hmac_key');
        
        if (!$hmacKey) {
            Log::warning('HMAC key not configured');
            return false;
        }
        
        $calculatedHash = hash_hmac("sha256", $krAnswer, $hmacKey);

        Log::info('IPN Hash verification', [
            'calculated' => $calculatedHash,
            'received' => $krHash,
            'valid' => $calculatedHash === $krHash
        ]);

        return $calculatedHash === $krHash;
    }

    /**
     * Verify payment hash from client
     */
    private function verifyPaymentHash(string $rawClientAnswer, string $hash): bool
    {
        $hmacKey = config('izipay.credentials.hmac_key');
        
        if (!$hmacKey) {
            Log::warning('HMAC key not configured for payment verification');
            return true; // Allow in development without key
        }
        
        $calculatedHash = hash_hmac("sha256", $rawClientAnswer, $hmacKey);

        return $calculatedHash === $hash;
    }

    /**
     * Get Izipay public key
     */
    private function getPublicKey(): string
    {
        return config('izipay.credentials.public_key', '');
    }
}
