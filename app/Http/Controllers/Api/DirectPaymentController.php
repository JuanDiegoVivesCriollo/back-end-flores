<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DirectPaymentOrder;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class DirectPaymentController extends Controller
{
    /**
     * Create direct payment order (no registration required)
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'items' => 'required|array|min:1',
            'items.*.name' => 'required|string',
            'items.*.price' => 'required|numeric|min:0',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.type' => 'required|in:flower,complement,breakfast',
            'items.*.id' => 'required|integer',
            'customer_name' => 'required|string|max:255',
            'customer_email' => 'required|email',
            'customer_phone' => 'required|string|max:20',
            'customer_document_type' => 'required|in:DNI,CE,RUC',
            'customer_document_number' => 'required|string|max:20',
            'shipping_type' => 'required|in:pickup,delivery',
            'shipping_address' => 'required_if:shipping_type,delivery|array',
            'shipping_cost' => 'sometimes|numeric|min:0',
            'delivery_date' => 'nullable|date',
            'delivery_time_slot' => 'nullable|string',
            'recipient_name' => 'nullable|string|max:255',
            'recipient_phone' => 'nullable|string|max:20',
            'notes' => 'nullable|string|max:1000',
            'google_maps_link' => 'nullable|url',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Error de validación',
                'errors' => $validator->errors()
            ], 422);
        }

        // Calculate totals
        $subtotal = 0;
        foreach ($request->items as $item) {
            $subtotal += $item['price'] * $item['quantity'];
        }

        $shippingCost = $request->get('shipping_cost', 0);
        $total = $subtotal + $shippingCost;

        // Create order
        $order = DirectPaymentOrder::create([
            'status' => 'pending',
            'payment_status' => 'pending',
            'total' => $total,
            'subtotal' => $subtotal,
            'shipping_cost' => $shippingCost,
            'shipping_type' => $request->shipping_type,
            'shipping_address' => $request->shipping_address,
            'customer_name' => $request->customer_name,
            'customer_email' => $request->customer_email,
            'customer_phone' => $request->customer_phone,
            'customer_document_type' => $request->customer_document_type,
            'customer_document_number' => $request->customer_document_number,
            'recipient_name' => $request->recipient_name,
            'recipient_phone' => $request->recipient_phone,
            'delivery_date' => $request->delivery_date,
            'delivery_time_slot' => $request->delivery_time_slot,
            'notes' => $request->notes,
            'items' => $request->items,
            'google_maps_link' => $request->google_maps_link,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Pedido creado exitosamente',
            'data' => [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'total' => $order->total,
            ]
        ], 201);
    }

    /**
     * Get order by number
     */
    public function show($orderNumber)
    {
        $order = DirectPaymentOrder::where('order_number', $orderNumber)->firstOrFail();

        return response()->json([
            'success' => true,
            'data' => $order
        ]);
    }

    /**
     * Confirm payment
     */
    public function confirmPayment(Request $request, $orderNumber)
    {
        $order = DirectPaymentOrder::where('order_number', $orderNumber)->firstOrFail();

        $validator = Validator::make($request->all(), [
            'transaction_id' => 'required|string',
            'payment_method' => 'required|in:card,transfer,yape,plin',
            'payment_code' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        // Create payment record
        $payment = Payment::create([
            'direct_payment_order_id' => $order->id,
            'transaction_id' => $request->transaction_id,
            'payment_method' => $request->payment_method,
            'payment_gateway' => Payment::GATEWAY_IZIPAY,
            'amount' => $order->total,
            'currency' => 'PEN',
            'status' => Payment::STATUS_COMPLETED,
            'paid_at' => now(),
        ]);

        // Update order status
        $order->update([
            'status' => 'confirmed',
            'payment_status' => 'paid',
            'payment_code' => $request->payment_code,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Pago confirmado exitosamente',
            'data' => [
                'order_number' => $order->order_number,
                'payment_id' => $payment->id,
            ]
        ]);
    }
}
