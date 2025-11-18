<?php

namespace App\Services\Payment;

use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use MercadoPago\MercadoPagoConfig;
use MercadoPago\Client\Preference\PreferenceClient;
use MercadoPago\Client\Payment\PaymentClient;
use MercadoPago\Exceptions\MPApiException;
use Illuminate\Support\Facades\Log;
use Exception;

class MercadoPagoService
{
    private $accessToken;
    private $publicKey;
    private $environment;

    public function __construct()
    {
        $this->accessToken = config('services.mercadopago.access_token');
        $this->publicKey = config('services.mercadopago.public_key');
        $this->environment = config('services.mercadopago.environment', 'test');

        // Configurar el SDK de Mercado Pago
        MercadoPagoConfig::setAccessToken($this->accessToken);

        Log::info('MercadoPago Configuration', [
            'environment' => $this->environment,
            'public_key' => substr($this->publicKey, 0, 15) . '...',
            'access_token' => substr($this->accessToken, 0, 15) . '...'
        ]);
    }

    /**
     * Crear una preferencia de pago
     */
    public function createPaymentPreference(Order $order, User $user): array
    {
        try {
            Log::info('Creating MercadoPago payment preference', [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'amount' => $order->total
            ]);

            $client = new PreferenceClient();

            // Preparar los items del pedido
            $items = [];
            foreach ($order->orderItems as $item) {
                $items[] = [
                    "id" => $item->flower_id,
                    "title" => $item->flower_name,
                    "description" => "Flores - " . $item->flower_name,
                    "quantity" => $item->quantity,
                    "currency_id" => "PEN",
                    "unit_price" => (float) $item->price
                ];
            }

            // Configurar la preferencia
            $preference = [
                "items" => $items,
                "payer" => [
                    "name" => $user->name,
                    "email" => $user->email,
                    "phone" => [
                        "number" => $user->phone ?? $order->shipping_address['phone'] ?? ''
                    ],
                    "address" => [
                        "street_name" => $order->shipping_address['address'] ?? '',
                        "city" => $order->shipping_address['city'] ?? '',
                        "postal_code" => $order->shipping_address['postal_code'] ?? ''
                    ]
                ],
                "payment_methods" => [
                    "excluded_payment_methods" => [],
                    "excluded_payment_types" => [],
                    "installments" => 12
                ],
                "back_urls" => [
                    "success" => config('app.frontend_url') . "/checkout/success",
                    "failure" => config('app.frontend_url') . "/checkout/failure",
                    "pending" => config('app.frontend_url') . "/checkout/pending"
                ],
                "auto_return" => "approved",
                "external_reference" => $order->order_number,
                "notification_url" => config('app.url') . "/api/v1/webhooks/mercadopago",
                "statement_descriptor" => "Flores D' Jazmin",
                "binary_mode" => false
            ];

            $response = $client->create($preference);

            Log::info('MercadoPago preference created successfully', [
                'preference_id' => $response->id,
                'init_point' => $response->init_point
            ]);

            // Crear el registro de pago
            $payment = Payment::create([
                'order_id' => $order->id,
                'payment_method' => 'mercadopago',
                'transaction_id' => $response->id,
                'amount' => $order->total,
                'currency' => 'PEN',
                'payment_status' => 'pending',
                'payment_details' => [
                    'preference_id' => $response->id,
                    'init_point' => $response->init_point,
                    'sandbox_init_point' => $response->sandbox_init_point,
                    'environment' => $this->environment
                ]
            ]);

            return [
                'success' => true,
                'payment_id' => $payment->id,
                'preference_id' => $response->id,
                'init_point' => $this->environment === 'test' ? $response->sandbox_init_point : $response->init_point,
                'public_key' => $this->publicKey,
                'order_number' => $order->order_number,
                'amount' => $order->total
            ];

        } catch (MPApiException $e) {
            Log::error('MercadoPago API Error', [
                'message' => $e->getMessage(),
                'status_code' => $e->getStatusCode(),
                'order_id' => $order->id
            ]);

            return [
                'success' => false,
                'error' => 'Error al crear la preferencia de pago: ' . $e->getMessage()
            ];

        } catch (Exception $e) {
            Log::error('Exception creating MercadoPago payment preference', [
                'message' => $e->getMessage(),
                'order_id' => $order->id
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Verificar estado de un pago
     */
    public function verifyPayment(string $paymentId): array
    {
        try {
            $client = new PaymentClient();
            $payment = $client->get($paymentId);

            Log::info('MercadoPago payment verification', [
                'payment_id' => $paymentId,
                'status' => $payment->status,
                'status_detail' => $payment->status_detail
            ]);

            return [
                'success' => true,
                'data' => [
                    'id' => $payment->id,
                    'status' => $payment->status,
                    'status_detail' => $payment->status_detail,
                    'transaction_amount' => $payment->transaction_amount,
                    'external_reference' => $payment->external_reference,
                    'payment_method_id' => $payment->payment_method_id,
                    'date_created' => $payment->date_created,
                    'date_approved' => $payment->date_approved
                ]
            ];

        } catch (MPApiException $e) {
            Log::error('MercadoPago payment verification error', [
                'payment_id' => $paymentId,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Procesar webhook de Mercado Pago
     */
    public function processWebhook(array $data): array
    {
        try {
            Log::info('Processing MercadoPago webhook', $data);

            if (!isset($data['type']) || !isset($data['data']['id'])) {
                return [
                    'success' => false,
                    'error' => 'Invalid webhook data'
                ];
            }

            $type = $data['type'];
            $paymentId = $data['data']['id'];

            if ($type === 'payment') {
                // Verificar el pago
                $paymentData = $this->verifyPayment($paymentId);

                if ($paymentData['success']) {
                    // Buscar el pago en nuestra base de datos usando external_reference
                    $externalReference = $paymentData['data']['external_reference'];
                    $order = Order::where('order_number', $externalReference)->first();

                    if ($order) {
                        $payment = Payment::where('order_id', $order->id)->first();

                        if ($payment) {
                            // Actualizar el estado del pago
                            $payment->update([
                                'payment_status' => $this->mapMercadoPagoStatus($paymentData['data']['status']),
                                'transaction_id' => $paymentId,
                                'paid_at' => $paymentData['data']['date_approved'] ? now() : null,
                                'payment_details' => array_merge($payment->payment_details ?? [], [
                                    'mp_payment_id' => $paymentId,
                                    'mp_status' => $paymentData['data']['status'],
                                    'mp_status_detail' => $paymentData['data']['status_detail'],
                                    'payment_method' => $paymentData['data']['payment_method_id']
                                ])
                            ]);

                            // Actualizar el estado de la orden si el pago fue aprobado
                            if ($paymentData['data']['status'] === 'approved') {
                                $order->update(['status' => 'confirmed']);
                            }
                        }
                    }
                }
            }

            return [
                'success' => true,
                'message' => 'Webhook processed successfully'
            ];

        } catch (Exception $e) {
            Log::error('Error processing MercadoPago webhook', [
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
     * Mapear estados de Mercado Pago a nuestros estados
     */
    private function mapMercadoPagoStatus(string $status): string
    {
        $statusMap = [
            'pending' => 'pending',
            'approved' => 'completed',
            'authorized' => 'processing',
            'in_process' => 'processing',
            'in_mediation' => 'processing',
            'rejected' => 'failed',
            'cancelled' => 'cancelled',
            'refunded' => 'refunded',
            'charged_back' => 'refunded'
        ];

        return $statusMap[$status] ?? 'pending';
    }
}
