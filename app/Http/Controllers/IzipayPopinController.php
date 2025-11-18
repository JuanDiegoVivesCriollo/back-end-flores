<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Payment;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class IzipayPopinController extends Controller
{
    public function index()
    {
        return view('izipay.index');
    }

    public function checkout(Request $request)
    {
        try {
            // URL de Web Service REST - CORREGIDA
            $url = "https://api.micuentaweb.pe/api-payment/V4/Charge/CreatePayment";

            // Usar credenciales desde .env
            $auth = env('IZIPAY_USERNAME') . ":" . env('IZIPAY_PROD_PASSWORD');

            $headers = array(
                "Authorization: Basic " . base64_encode($auth),
                "Content-Type: application/json"
            );

            // Estructura simplificada como en el ejemplo
            $body = [
                "amount" => $request->input("amount") * 100, // Convertir a centavos
                "currency" => $request->input("currency", "PEN"),
                "orderId" => $request->input("orderId"),
                "customer" => [
                    "email" => $request->input("email"),
                    "billingDetails" => [
                        "firstName" => $request->input("firstName"),
                        "lastName" => $request->input("lastName"),
                        "phoneNumber" => $request->input("phoneNumber"),
                        "identityType" => $request->input("identityType", "DNI"),
                        "identityCode" => $request->input("identityCode"),
                        "address" => $request->input("address"),
                        "country" => $request->input("country", "PE"),
                        "city" => $request->input("city", "Lima"),
                        "state" => $request->input("state", "Lima"),
                        "zipCode" => $request->input("zipCode", "15000"),
                    ]
                ],
            ];

            Log::info('Izipay CreatePayment Request', [
                'url' => $url,
                'body' => $body
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

            Log::info('Izipay CreatePayment Response', [
                'http_code' => $http_code,
                'response' => $raw_response,
                'curl_error' => $curl_error
            ]);

            if ($curl_error) {
                throw new Exception("CURL Error: " . $curl_error);
            }

            if ($http_code !== 200) {
                throw new Exception("HTTP Error: " . $http_code . " - " . $raw_response);
            }

            $response = json_decode($raw_response, true);

            if (!$response || !isset($response["answer"]["formToken"])) {
                throw new Exception("Invalid response structure: " . $raw_response);
            }

            // Obtenemos el formtoken generado
            $formToken = $response["answer"]["formToken"];

            // Obtenemos publicKey desde .env
            $publicKey = env("IZIPAY_PROD_PUBLIC_KEY");

            // Crear/actualizar payment record si se proporciona order_id
            if ($request->has('order_id')) {
                $order = Order::find($request->input('order_id'));
                if ($order) {
                    Payment::updateOrCreate(
                        ['order_id' => $order->id, 'payment_method' => 'izipay'],
                        [
                            'amount' => $order->total,
                            'currency' => 'PEN',
                            'status' => 'pending',
                            'payment_status' => 'pending',
                            'transaction_id' => $request->input("orderId"),
                            'session_token' => $formToken,
                            'metadata' => json_encode([
                                'form_token' => $formToken,
                                'public_key' => $publicKey,
                                'environment' => 'production'
                            ])
                        ]
                    );
                }
            }

            return view('izipay.checkout', compact("publicKey", "formToken"));

        } catch (Exception $e) {
            Log::error('Izipay checkout error', [
                'error' => $e->getMessage(),
                'request_data' => $request->all()
            ]);

            return back()->with('error', 'Error al procesar el pago: ' . $e->getMessage());
        }
    }

    public function result(Request $request)
    {
        try {
            if (empty($request->all())) {
                throw new Exception("No post data received!");
            }

            Log::info('Izipay result received', $request->all());

            // Validación de firma
            if (!$this->checkHash($request, env("IZIPAY_PROD_HMAC_KEY"))) {
                Log::error('Invalid signature in result', [
                    'kr-hash' => $request->input('kr-hash'),
                    'kr-answer' => $request->input('kr-answer')
                ]);
                throw new Exception("Invalid signature");
            }

            $answer = json_decode($request['kr-answer'], true);
            $orderStatus = $answer['orderStatus'];
            $orderId = $answer['orderDetails']['orderId'] ?? null;

            // Actualizar estado del pago
            if ($orderId) {
                $payment = Payment::where('transaction_id', $orderId)->first();
                if ($payment) {
                    $paymentStatus = match($orderStatus) {
                        'PAID' => 'completed',
                        'CANCELLED', 'ABANDONED' => 'cancelled',
                        'REFUSED' => 'failed',
                        default => 'pending'
                    };

                    $payment->update([
                        'payment_status' => $paymentStatus,
                        'status' => $paymentStatus,
                        'payment_details' => $answer,
                        'paid_at' => $paymentStatus === 'completed' ? now() : null
                    ]);

                    if ($paymentStatus === 'completed') {
                        $payment->order->update(['status' => 'confirmed']);
                    }
                }
            }

            return view('izipay.result', compact('orderStatus', 'answer'));

        } catch (Exception $e) {
            Log::error('Izipay result processing error', [
                'error' => $e->getMessage(),
                'request_data' => $request->all()
            ]);

            return view('izipay.result', [
                'orderStatus' => 'ERROR',
                'answer' => ['error' => $e->getMessage()]
            ]);
        }
    }

    public function ipn(Request $request)
    {
        try {
            if (empty($request->all())) {
                throw new Exception("No post data received!");
            }

            Log::info('Izipay IPN received', $request->all());

            // Validación de firma en IPN - usar PASSWORD no HMAC
            if (!$this->checkHash($request, env("IZIPAY_PROD_PASSWORD"))) {
                Log::error('Invalid signature in IPN', [
                    'kr-hash' => $request->input('kr-hash'),
                    'kr-answer' => $request->input('kr-answer')
                ]);
                throw new Exception("Invalid signature");
            }

            $answer = json_decode($request["kr-answer"], true);
            $transaction = $answer['transactions'][0] ?? null;

            // Verifica orderStatus PAID
            $orderStatus = $answer['orderStatus'];
            $orderId = $answer['orderDetails']['orderId'] ?? null;
            $transactionUuid = $transaction['uuid'] ?? null;

            Log::info('IPN Processing', [
                'orderStatus' => $orderStatus,
                'orderId' => $orderId,
                'transactionUuid' => $transactionUuid
            ]);

            // Actualizar estado del pago
            if ($orderId) {
                $payment = Payment::where('transaction_id', $orderId)->first();
                if ($payment) {
                    $paymentStatus = match($orderStatus) {
                        'PAID' => 'completed',
                        'CANCELLED', 'ABANDONED' => 'cancelled',
                        'REFUSED' => 'failed',
                        default => 'pending'
                    };

                    $payment->update([
                        'payment_status' => $paymentStatus,
                        'status' => $paymentStatus,
                        'payment_details' => $answer,
                        'paid_at' => $paymentStatus === 'completed' ? now() : null
                    ]);

                    if ($paymentStatus === 'completed') {
                        $payment->order->update(['status' => 'confirmed']);
                    }
                }
            }

            return response('OK! OrderStatus is ' . $orderStatus, 200);

        } catch (Exception $e) {
            Log::error('Izipay IPN processing error', [
                'error' => $e->getMessage(),
                'request_data' => $request->all()
            ]);

            return response('ERROR: ' . $e->getMessage(), 500);
        }
    }

    private function checkHash($request, $key)
    {
        $krAnswer = str_replace('\/', '/', $request["kr-answer"]);

        $calculateHash = hash_hmac("sha256", $krAnswer, $key);

        $result = ($calculateHash == $request["kr-hash"]);

        Log::info('Hash verification', [
            'calculated' => $calculateHash,
            'received' => $request["kr-hash"],
            'valid' => $result
        ]);

        return $result;
    }
}
