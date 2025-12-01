<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\GuestCustomer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\DeliveryDistrict;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class CheckoutController extends Controller
{
    /**
     * Get all delivery districts with shipping costs
     */
    public function getDistricts()
    {
        $districts = DeliveryDistrict::active()
            ->ordered()
            ->get(['id', 'name', 'zone', 'shipping_cost', 'estimated_time']);

        return response()->json([
            'success' => true,
            'data' => $districts
        ]);
    }

    /**
     * Create or get guest customer
     */
    public function createGuestCustomer(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'full_name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'phone' => 'required|string|max:20',
            'dni' => 'nullable|string|max:20',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Datos inválidos',
                'errors' => $validator->errors()
            ], 422);
        }

        $customer = GuestCustomer::findOrCreateByContact($request->only(['full_name', 'email', 'phone', 'dni']));

        return response()->json([
            'success' => true,
            'data' => $customer,
            'message' => 'Cliente creado exitosamente'
        ]);
    }

    /**
     * Create order from checkout
     */
    public function createOrder(Request $request)
    {
        $validator = Validator::make($request->all(), [
            // Customer data
            'customer.full_name' => 'required|string|max:255',
            'customer.email' => 'required|email|max:255',
            'customer.phone' => 'required|string|max:20',
            'customer.dni' => 'nullable|string|max:20',
            
            // Delivery type: pickup, delivery, third_party
            'delivery_type' => 'required|in:pickup,delivery,third_party',
            
            // Delivery address (required for delivery and third_party)
            'delivery.district_id' => 'required_unless:delivery_type,pickup|nullable|exists:delivery_districts,id',
            'delivery.address' => 'required_unless:delivery_type,pickup|nullable|string|max:500',
            'delivery.reference' => 'nullable|string|max:255',
            'delivery.recipient_name' => 'required_if:delivery_type,third_party|nullable|string|max:255',
            'delivery.recipient_phone' => 'required_if:delivery_type,third_party|nullable|string|max:20',
            
            // Schedule
            'delivery.date' => 'required|date|after_or_equal:today',
            'delivery.time_slot' => 'required|string',
            
            // Payment
            'payment_method' => 'required|in:card,yape,plin',
            
            // Items
            'items' => 'required|array|min:1',
            'items.*.type' => 'required|in:flower,complement,breakfast',
            'items.*.id' => 'required|integer',
            'items.*.name' => 'required|string',
            'items.*.price' => 'required|numeric|min:0',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.image_url' => 'nullable|string',
            
            // Optional
            'notes' => 'nullable|string|max:500',
            'dedicatory_message' => 'nullable|string|max:300',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Datos inválidos',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();

            // Create or get guest customer
            $customer = GuestCustomer::findOrCreateByContact($request->input('customer'));

            // Calculate totals
            $subtotal = collect($request->input('items'))->sum(function ($item) {
                return $item['price'] * $item['quantity'];
            });

            // Get shipping cost
            $shippingCost = 0;
            $district = null;
            if ($request->input('delivery_type') !== 'pickup') {
                $district = DeliveryDistrict::find($request->input('delivery.district_id'));
                $shippingCost = $district ? $district->shipping_cost : 0;
            }

            $total = $subtotal + $shippingCost;

            // Build shipping address
            $shippingAddress = null;
            if ($request->input('delivery_type') !== 'pickup') {
                $shippingAddress = [
                    'district_id' => $request->input('delivery.district_id'),
                    'district_name' => $district ? $district->name : null,
                    'address' => $request->input('delivery.address'),
                    'reference' => $request->input('delivery.reference'),
                ];
            }

            // Create order
            $order = Order::create([
                'guest_customer_id' => $customer->id,
                'status' => Order::STATUS_PENDING,
                'payment_status' => Order::PAYMENT_PENDING,
                'payment_method' => $request->input('payment_method'),
                'subtotal' => $subtotal,
                'shipping_cost' => $shippingCost,
                'total' => $total,
                'shipping_type' => $request->input('delivery_type'),
                'shipping_address' => $shippingAddress,
                'customer_name' => $customer->full_name,
                'customer_email' => $customer->email,
                'customer_phone' => $customer->phone,
                'customer_info' => [
                    'dni' => $customer->dni,
                ],
                'recipient_name' => $request->input('delivery.recipient_name'),
                'recipient_phone' => $request->input('delivery.recipient_phone'),
                'delivery_date' => $request->input('delivery.date'),
                'delivery_time_slot' => $request->input('delivery.time_slot'),
                'notes' => $request->input('notes'),
                'special_instructions' => $request->input('dedicatory_message'),
            ]);

            // Create order items
            foreach ($request->input('items') as $item) {
                $orderItemData = [
                    'order_id' => $order->id,
                    'item_type' => $item['type'],
                    'name' => $item['name'],
                    'quantity' => $item['quantity'],
                    'price' => $item['price'],
                    'total' => $item['price'] * $item['quantity'],
                ];
                
                // Set the correct foreign key based on item type
                switch ($item['type']) {
                    case 'flower':
                        $orderItemData['flower_id'] = $item['id'];
                        break;
                    case 'complement':
                        $orderItemData['complement_id'] = $item['id'];
                        break;
                    case 'breakfast':
                        $orderItemData['breakfast_id'] = $item['id'];
                        break;
                }
                
                OrderItem::create($orderItemData);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => [
                    'order_id' => $order->id,
                    'order_number' => $order->order_number,
                    'total' => $order->total,
                    'payment_method' => $order->payment_method,
                ],
                'message' => 'Orden creada exitosamente'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error creating order: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error al crear la orden',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Upload payment proof (for Yape/Plin)
     */
    public function uploadPaymentProof(Request $request, $orderNumber)
    {
        $validator = Validator::make($request->all(), [
            'payment_proof' => 'required|image|max:5120', // 5MB max
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Imagen inválida',
                'errors' => $validator->errors()
            ], 422);
        }

        $order = Order::where('order_number', $orderNumber)->first();

        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'Orden no encontrada'
            ], 404);
        }

        // Store the image
        $path = $request->file('payment_proof')->store('payment-proofs', 'public');

        // Update order
        $order->update([
            'payment_proof_image' => $path,
            'payment_status' => 'pending_verification',
        ]);

        // Update guest customer stats
        if ($order->guest_customer_id) {
            $order->guestCustomer->incrementOrderStats($order->total);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'order_number' => $order->order_number,
                'payment_proof_url' => Storage::url($path),
            ],
            'message' => 'Comprobante subido exitosamente'
        ]);
    }

    /**
     * Get order details by order number
     */
    public function getOrder($orderNumber)
    {
        $order = Order::with(['orderItems', 'guestCustomer'])
            ->where('order_number', $orderNumber)
            ->first();

        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'Orden no encontrada'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $order
        ]);
    }

    /**
     * Get store info for pickup
     */
    public function getStoreInfo()
    {
        // Store information - this could come from a settings table
        return response()->json([
            'success' => true,
            'data' => [
                'name' => "Flores D'Jazmin",
                'address' => 'Av. Principal 123, Lima, Perú',
                'phone' => '+51 999 888 777',
                'hours' => 'Lunes a Sábado: 9:00 AM - 7:00 PM',
                'google_maps_embed' => 'https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3901.5!2d-77.03!3d-12.09!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x0%3A0x0!2zMTLCsDA1JzI0LjAiUyA3N8KwMDEnNDguMCJX!5e0!3m2!1sen!2spe!4v1234567890',
                'coordinates' => [
                    'lat' => -12.09,
                    'lng' => -77.03
                ]
            ]
        ]);
    }

    /**
     * Get payment info (QR codes, phone numbers for Yape/Plin)
     */
    public function getPaymentInfo()
    {
        $yapePhone = env('YAPE_PHONE_NUMBER', '999888777');
        $yapeName = env('YAPE_HOLDER_NAME', "Flores D'Jazmin");
        $plinPhone = env('PLIN_PHONE_NUMBER', '999888777');
        $plinName = env('PLIN_HOLDER_NAME', "Flores D'Jazmin");
        $whatsappNumber = env('OWNER_WHATSAPP_NUMBER', '51999888777');
        
        return response()->json([
            'success' => true,
            'data' => [
                'yape' => [
                    'phone' => $yapePhone,
                    'qr_code' => '/images/yape-qr.png', // Path to QR code image
                    'name' => $yapeName,
                    'instructions' => [
                        '1. Abre tu app de Yape',
                        '2. Escanea el código QR o usa el número de teléfono',
                        '3. Ingresa el monto exacto de tu pedido',
                        '4. Completa el pago',
                        '5. Sube la captura de tu comprobante'
                    ]
                ],
                'plin' => [
                    'phone' => $plinPhone,
                    'qr_code' => '/images/plin-qr.png', // Path to QR code image
                    'name' => $plinName,
                    'instructions' => [
                        '1. Abre tu app bancaria con Plin',
                        '2. Escanea el código QR o usa el número de teléfono',
                        '3. Ingresa el monto exacto de tu pedido',
                        '4. Completa el pago',
                        '5. Sube la captura de tu comprobante'
                    ]
                ],
                'whatsapp' => [
                    'number' => '+' . $whatsappNumber,
                    'message_template' => 'Hola! Acabo de realizar un pedido en Flores D\'Jazmin. Mi número de orden es: {order_number}'
                ]
            ]
        ]);
    }

    /**
     * Send WhatsApp notification to owner
     */
    public function notifyOwnerWhatsApp(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'order_number' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $order = Order::with(['orderItems', 'guestCustomer'])
            ->where('order_number', $request->input('order_number'))
            ->first();

        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'Orden no encontrada'
            ], 404);
        }

        // Build WhatsApp message
        $items = $order->orderItems->map(function($item) {
            return "• {$item->name} x{$item->quantity} - S/ " . number_format($item->total, 2);
        })->join("\n");

        $deliveryInfo = $order->shipping_type === 'pickup' 
            ? 'Recojo en tienda'
            : ($order->shipping_address['address'] ?? 'No especificada') . 
              ($order->shipping_address['district_name'] ? ', ' . $order->shipping_address['district_name'] : '');

        $message = "🌸 *NUEVO PEDIDO* 🌸\n\n";
        $message .= "*Número de Orden:* {$order->order_number}\n";
        $message .= "*Fecha:* " . $order->created_at->format('d/m/Y H:i') . "\n\n";
        $message .= "*👤 CLIENTE:*\n";
        $message .= "Nombre: {$order->customer_name}\n";
        $message .= "Teléfono: {$order->customer_phone}\n";
        $message .= "Email: {$order->customer_email}\n";
        if ($order->customer_info['dni'] ?? null) {
            $message .= "DNI: {$order->customer_info['dni']}\n";
        }
        $message .= "\n*📦 PRODUCTOS:*\n{$items}\n\n";
        $message .= "*🚚 ENTREGA:*\n";
        $message .= "Tipo: " . ucfirst($order->shipping_type) . "\n";
        $message .= "Dirección: {$deliveryInfo}\n";
        $message .= "Fecha: " . ($order->delivery_date ? $order->delivery_date->format('d/m/Y') : 'No especificada') . "\n";
        $message .= "Horario: {$order->delivery_time_slot}\n";
        if ($order->recipient_name) {
            $message .= "\n*👥 DESTINATARIO:*\n";
            $message .= "Nombre: {$order->recipient_name}\n";
            $message .= "Teléfono: {$order->recipient_phone}\n";
        }
        $message .= "\n*💰 TOTALES:*\n";
        $message .= "Subtotal: S/ " . number_format($order->subtotal, 2) . "\n";
        $message .= "Envío: S/ " . number_format($order->shipping_cost, 2) . "\n";
        $message .= "*Total: S/ " . number_format($order->total, 2) . "*\n\n";
        $message .= "*💳 MÉTODO DE PAGO:* " . strtoupper($order->payment_method) . "\n";
        
        if ($order->payment_proof_image) {
            $proofUrl = url('/storage/' . $order->payment_proof_image);
            $message .= "*Comprobante:* {$proofUrl}\n";
        }

        if ($order->special_instructions) {
            $message .= "\n*💝 DEDICATORIA:*\n{$order->special_instructions}\n";
        }

        if ($order->notes) {
            $message .= "\n*📝 NOTAS:*\n{$order->notes}\n";
        }

        // Owner's WhatsApp number (should come from config/settings)
        $ownerPhone = env('OWNER_WHATSAPP_NUMBER', '+51999888777');
        $encodedMessage = urlencode($message);
        $whatsappUrl = "https://wa.me/{$ownerPhone}?text={$encodedMessage}";

        return response()->json([
            'success' => true,
            'data' => [
                'whatsapp_url' => $whatsappUrl,
                'message' => $message
            ]
        ]);
    }
}
