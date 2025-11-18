<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DraftOrder;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Flower;
use App\Models\OrderStatusHistory;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OrderController extends Controller
{
    /**
     * Display all orders (Admin only)
     */
    public function getAllOrders(Request $request)
    {
        try {
            // Verificar que el usuario sea admin
            if (!$request->user() || $request->user()->role !== 'admin') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access'
                ], 403);
            }

            $query = Order::with(['user', 'orderItems.flower'])
                ->orderBy('created_at', 'desc');

            // Filter by status
            if ($request->has('status') && $request->status !== 'all') {
                $query->where('status', $request->status);
            }

            // Search by order number or customer name
            if ($request->has('search') && !empty($request->search)) {
                $search = $request->search;
                $query->where(function($q) use ($search) {
                    $q->where('order_number', 'like', "%{$search}%")
                      ->orWhere('customer_name', 'like', "%{$search}%")
                      ->orWhere('customer_email', 'like', "%{$search}%");
                });
            }

            // Pagination
            $perPage = $request->get('per_page', 12);
            $orders = $query->paginate($perPage);

            // Agregar payment_status a cada order (por ahora será pending)
            $orders->getCollection()->transform(function ($order) {
                $order->payment_status = 'pending'; // Temporal hasta implementar pagos
                $order->total_amount = $order->total; // Alias para compatibilidad con frontend
                return $order;
            });

            return response()->json([
                'success' => true,
                'data' => $orders
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch orders',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display user's orders
     */
    public function index(Request $request)
    {
        try {
            $user = $request->user();

            $query = $user->orders()
                ->with(['orderItems.flower', 'statusHistory'])
                ->orderBy('created_at', 'desc');

            // Filter by status
            if ($request->has('status')) {
                $query->byStatus($request->status);
            }

            // Filter by payment status
            if ($request->has('payment_status')) {
                $query->byPaymentStatus($request->payment_status);
            }

            // Pagination
            $perPage = $request->get('per_page', 10);
            $orders = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => $orders
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch orders',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified order
     */
    public function show(Request $request, $id)
    {
        try {
            $user = $request->user();

            $order = $user->orders()
                ->with(['orderItems.flower.category', 'statusHistory', 'payments'])
                ->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $order
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Order not found'
            ], 404);
        }
    }

    /**
     * Store a newly created order
     */
    public function store(Request $request)
    {
        \Log::info('OrderController@store - Starting request', [
            'request_data' => $request->all(),
            'has_authorization' => $request->hasHeader('Authorization'),
            'payment_method_received' => $request->get('payment_method'),
            'payment_method_type' => gettype($request->get('payment_method'))
        ]);

        // Base validation rules
        $rules = [
            'items' => 'required|array|min:1',
            'items.*.flower_id' => 'required|exists:flowers,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.price' => 'required|numeric|min:0',
            'shipping_address' => 'required|array',
            'shipping_address.name' => 'required|string|max:255',
            'shipping_address.phone' => 'required|string|max:20',
            'shipping_address.address' => 'required|string|max:500',
            'shipping_address.district' => 'nullable|string|max:100',
            'shipping_address.city' => 'required|string|max:100',
            'shipping_address.postal_code' => 'nullable|string|max:20',
            'shipping_type' => 'nullable|string|in:delivery,pickup',
            'customer_notes' => 'nullable|string|max:1000',
            'delivery_date' => 'nullable|date|after_or_equal:today',
            'delivery_time_slot' => 'nullable|string|max:50',
            'payment_method' => 'required|in:izipay,cash_on_delivery'
        ];

        // Check if table has new columns before adding their validation rules
        try {
            $tableName = (new Order())->getTable();
            $columns = \Schema::getColumnListing($tableName);

            if (in_array('billing_address', $columns)) {
                $rules['billing_address'] = 'nullable|array';
            }

            if (in_array('customer_info', $columns)) {
                $rules['customer_info'] = 'nullable|array';
                $rules['customer_info.firstName'] = 'nullable|string|max:255';
                $rules['customer_info.lastName'] = 'nullable|string|max:255';
                $rules['customer_info.email'] = 'nullable|email|max:255';
                $rules['customer_info.phoneNumber'] = 'nullable|string|max:20';
                $rules['customer_info.identityType'] = 'nullable|string|in:DNI,PS,CE';
                $rules['customer_info.identityCode'] = 'nullable|string|max:20';
                $rules['customer_info.address'] = 'nullable|string|max:500';
                $rules['customer_info.country'] = 'nullable|string|max:10';
                $rules['customer_info.state'] = 'nullable|string|max:100';
                $rules['customer_info.city'] = 'nullable|string|max:100';
                $rules['customer_info.zipCode'] = 'nullable|string|max:20';
            }
        } catch (\Exception $e) {
            \Log::warning('Could not check table columns for validation', ['error' => $e->getMessage()]);
        }

        $validator = Validator::make($request->all(), $rules);

        \Log::info('OrderController@store - Validation result', [
            'validator_passes' => $validator->passes(),
            'validator_errors' => $validator->errors()->toArray()
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation errors',
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();

        try {
            // Get user if authenticated, otherwise create a guest user
            $user = null;

            // Try to get authenticated user safely
            try {
                $user = auth('sanctum')->user();
            } catch (\Exception $e) {
                \Log::info('No authenticated user found, proceeding as guest', ['error' => $e->getMessage()]);
            }

            // If no authenticated user, create a guest user based on customer info
            if (!$user) {
                \Log::info('Creating guest user for order');

                $customerInfo = $request->customer_info ?? [];
                $shippingAddress = $request->shipping_address ?? [];

                // Use customer info or shipping address for guest user
                $guestEmail = $customerInfo['email'] ?? $shippingAddress['email'] ?? 'guest_' . time() . '@floresydetalleslima.com';
                $guestName = $customerInfo['firstName'] ?? $shippingAddress['name'] ?? 'Cliente Invitado';
                $guestPhone = $customerInfo['phone'] ?? $shippingAddress['phone'] ?? '';

                // Create or find guest user
                $user = \App\Models\User::firstOrCreate(
                    ['email' => $guestEmail],
                    [
                        'name' => $guestName,
                        'phone' => $guestPhone,
                        'role' => 'guest',
                        'email_verified_at' => now(),
                        'password' => \Hash::make('guest_' . \Str::random(10)) // Random password
                    ]
                );

                \Log::info('Guest user created/found', [
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'name' => $user->name
                ]);
            }

            $items = $request->items;

            \Log::info('OrderController@store - Validation passed', [
                'user_id' => $user->id,
                'user_authenticated' => $request->user() !== null,
                'is_guest' => $user->role === 'guest',
                'items_count' => count($items)
            ]);

            // Calculate totals
            $subtotal = 0;
            $orderItems = [];

            foreach ($items as $item) {
                \Log::info('Processing item', $item);

                // First check if flower exists
                $flower = Flower::find($item['flower_id']);
                if (!$flower) {
                    \Log::error('Flower not found', ['flower_id' => $item['flower_id']]);
                    return response()->json([
                        'success' => false,
                        'message' => 'Producto no encontrado',
                        'errors' => ['flower_id' => "La flor con ID {$item['flower_id']} no existe"]
                    ], 404);
                }

                // Check if flower is active
                if (!$flower->is_active) {
                    \Log::error('Flower not active', ['flower_id' => $item['flower_id'], 'flower_name' => $flower->name]);
                    return response()->json([
                        'success' => false,
                        'message' => 'Producto no disponible',
                        'errors' => ['flower_id' => "La flor '{$flower->name}' no está disponible actualmente"]
                    ], 400);
                }

                // Check if flower is in stock
                if ($flower->stock <= 0) {
                    \Log::error('Flower out of stock', ['flower_id' => $item['flower_id'], 'flower_name' => $flower->name]);
                    return response()->json([
                        'success' => false,
                        'message' => 'Producto sin stock',
                        'errors' => ['flower_id' => "La flor '{$flower->name}' está agotada"]
                    ], 400);
                }

                \Log::info('Flower found', [
                    'flower_id' => $flower->id,
                    'flower_name' => $flower->name,
                    'flower_stock' => $flower->stock,
                    'requested_quantity' => $item['quantity']
                ]);

                // Check if requested quantity is available
                if ($flower->stock < $item['quantity']) {
                    \Log::error('Insufficient stock', [
                        'flower_id' => $flower->id,
                        'flower_name' => $flower->name,
                        'available_stock' => $flower->stock,
                        'requested_quantity' => $item['quantity']
                    ]);

                    return response()->json([
                        'success' => false,
                        'message' => 'Stock insuficiente',
                        'errors' => ['flower_id' => "La flor '{$flower->name}' solo tiene {$flower->stock} unidades disponibles, pero se solicitaron {$item['quantity']}"]
                    ], 400);
                }

                $itemTotal = $item['price'] * $item['quantity'];
                $subtotal += $itemTotal;

                $orderItems[] = [
                    'flower_id' => $flower->id,
                    'quantity' => $item['quantity'],
                    'price' => $item['price'],
                    'total' => $itemTotal
                ];
            }

            \Log::info('Items processed successfully', [
                'subtotal' => $subtotal,
                'order_items_count' => count($orderItems)
            ]);

            // Determine shipping type from request (prefer the explicit shipping_type field)
            $shippingType = $request->shipping_type ??
                          ($request->shipping_address['address'] === 'Recojo en tienda' ? 'pickup' : 'delivery');

            \Log::info('Shipping type determined', [
                'from_request_field' => $request->shipping_type,
                'from_address_detection' => $request->shipping_address['address'] === 'Recojo en tienda' ? 'pickup' : 'delivery',
                'final_shipping_type' => $shippingType
            ]);

            // Calculate additional costs based on shipping type
            $taxAmount = 0; // No IGV aplicado
            $shippingAmount = 0; // Default to 0

            // Calculate shipping cost for delivery using new district-based pricing
            if ($shippingType === 'delivery') {
                $district = $request->shipping_address['district'] ?? '';
                if (!empty($district)) {
                    $shippingAmount = \App\Models\DeliveryDistrict::getShippingCost($district);
                    \Log::info('Shipping cost calculated by district', [
                        'district' => $district,
                        'shipping_cost' => $shippingAmount
                    ]);
                } else {
                    // Fallback: use old system if no district specified
                    $shippingAmount = $subtotal < 100 ? 15.00 : 0;
                    \Log::warning('No district specified, using fallback shipping cost', [
                        'fallback_cost' => $shippingAmount
                    ]);
                }
            }

            $discountAmount = 0; // TODO: Implement discount logic
            $totalAmount = $subtotal + $taxAmount + $shippingAmount - $discountAmount;

            \Log::info('Order totals calculated', [
                'subtotal' => $subtotal,
                'tax' => $taxAmount,
                'shipping' => $shippingAmount,
                'total' => $totalAmount
            ]);

            // Create DRAFT order instead of real order
            \Log::info('About to create DRAFT order (no stock reduction yet)', [
                'user_id' => $user->id,
                'draft_number' => DraftOrder::generateDraftNumber(),
                'shipping_address' => $request->shipping_address
            ]);

            // Prepare draft order data
            $draftData = [
                'user_id' => $user->id,
                'draft_number' => DraftOrder::generateDraftNumber(),
                'cart_data' => $orderItems, // Store cart items as JSON
                'customer_info' => $request->customer_info,
                'shipping_address' => $request->shipping_address,
                'billing_address' => $request->billing_address,
                'shipping_type' => $shippingType,
                'delivery_date' => $request->delivery_date ? now()->parse($request->delivery_date) : null,
                'delivery_time_slot' => $request->delivery_time_slot,
                'customer_notes' => $request->customer_notes,
                'subtotal' => $subtotal,
                'tax' => $taxAmount,
                'shipping_cost' => $shippingAmount,
                'total' => $totalAmount,
                'expires_at' => now()->addHours(2) // Draft expires in 2 hours
            ];

            $draftOrder = DraftOrder::create($draftData);

            \Log::info('Draft order created successfully (NO STOCK REDUCED)', [
                'draft_id' => $draftOrder->id,
                'draft_number' => $draftOrder->draft_number
            ]);

            DB::commit();

            // If payment method is izipay, redirect to payment processing
            if ($request->payment_method === 'izipay') {
                \Log::info('Payment method is izipay, creating payment session with DRAFT (no real order yet)', [
                    'draft_id' => $draftOrder->id,
                    'draft_number' => $draftOrder->draft_number
                ]);

                // Create payment session using IzipayService - API V4 FUNCIONAL
                try {
                    $izipayService = app(\App\Services\Payment\IzipayService::class);

                    // CORRECCIÓN: NO convertir draft a order real hasta que el pago sea exitoso
                    // Crear sesión de pago usando el draft directamente
                    $paymentSession = $izipayService->createPaymentSessionFromDraft($draftOrder, $user);

                    if ($paymentSession['success']) {
                        return response()->json([
                            'success' => true,
                            'message' => 'Draft order created successfully, redirecting to payment',
                            'data' => [
                                'order' => [
                                    'id' => $draftOrder->id,
                                    'order_number' => $draftOrder->draft_number, // Usar draft_number, no order_number
                                    'total' => $draftOrder->total,
                                    'status' => 'draft' // Status is draft, not real order
                                ],
                                'payment' => [
                                    'form_token' => $paymentSession['form_token'], // Token que funciona
                                    'public_key' => $paymentSession['public_key'],
                                    'redirect_url' => config('app.frontend_url') . '/payment/process'
                                ]
                            ]
                        ], 201);
                    } else {
                        \Log::error('Failed to create Izipay payment session', ['draft_id' => $draftOrder->id, 'error' => $paymentSession['error'] ?? 'Unknown error']);
                        return response()->json([
                            'success' => false,
                            'message' => 'Failed to initialize payment, please try again',
                            'error' => 'Payment gateway error'
                        ], 500);
                    }

                } catch (\Exception $e) {
                    \Log::error('Exception while creating Izipay payment session', [
                        'draft_id' => $draftOrder->id,
                        'error' => $e->getMessage()
                    ]);

                    return response()->json([
                        'success' => false,
                        'message' => 'Failed to initialize payment, please try again',
                        'error' => $e->getMessage()
                    ], 500);
                }
            }

            // For cash_on_delivery or other payment methods, convert draft to order immediately
            $realOrder = $draftOrder->convertToOrder();

            return response()->json([
                'success' => true,
                'message' => 'Order created successfully',
                'data' => $realOrder
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to create order',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Cancel an order
     */
    public function cancel(Request $request, $id)
    {
        DB::beginTransaction();

        try {
            $user = $request->user();
            $order = $user->orders()->findOrFail($id);

            if (!$order->canBeCancelled()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Order cannot be cancelled at this stage'
                ], 400);
            }

            // Restore stock
            foreach ($order->orderItems as $item) {
                $flower = $item->flower;
                $flower->increment('stock', $item->quantity);
            }

            // Update order status
            $order->update([
                'status' => Order::STATUS_CANCELLED
            ]);

            // Create status history
            OrderStatusHistory::create([
                'order_id' => $order->id,
                'status' => Order::STATUS_CANCELLED,
                'notes' => $request->get('reason', 'Cancelled by customer'),
                'changed_by' => $user->id
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Order cancelled successfully',
                'data' => $order->load(['orderItems.flower', 'statusHistory'])
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to cancel order',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get order status tracking
     */
    public function tracking(Request $request, $orderNumber)
    {
        try {
            $order = Order::where('order_number', $orderNumber)
                ->with(['statusHistory', 'orderItems.flower'])
                ->first();

            if (!$order) {
                return response()->json([
                    'success' => false,
                    'message' => 'Order not found'
                ], 404);
            }

            // Public tracking - only show basic info
            $trackingData = [
                'order_number' => $order->order_number,
                'status' => $order->status,
                'created_at' => $order->created_at,
                'delivery_date' => $order->delivery_date,
                'status_history' => $order->statusHistory->map(function($history) {
                    return [
                        'status' => $history->status,
                        'notes' => $history->notes,
                        'created_at' => $history->created_at
                    ];
                })
            ];

            return response()->json([
                'success' => true,
                'data' => $trackingData
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch tracking information',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update order status (Admin only)
     */
    public function updateStatus(Request $request, $id)
    {
        try {
            // Verificar que el usuario sea admin
            if (!$request->user() || $request->user()->role !== 'admin') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access'
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'status' => 'required|in:pending,confirmed,preparing,ready,in_transit,delivered,cancelled',
                'notes' => 'nullable|string|max:500'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $order = Order::findOrFail($id);

            // Update order status
            $order->update(['status' => $request->status]);

            // Create status history entry
            OrderStatusHistory::create([
                'order_id' => $order->id,
                'status' => $request->status,
                'notes' => $request->notes ?? "Status updated to {$request->status}",
                'changed_by' => $request->user()->id
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Order status updated successfully',
                'data' => $order->load(['orderItems.flower', 'statusHistory'])
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update order status',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update payment status (Admin only)
     */
    public function updatePaymentStatus(Request $request, $id)
    {
        try {
            // Verificar que el usuario sea admin
            if (!$request->user() || $request->user()->role !== 'admin') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access'
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'payment_status' => 'required|in:pending,paid,failed,refunded',
                'notes' => 'nullable|string|max:500'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $order = Order::findOrFail($id);

            // Por ahora guardamos en notas, luego implementaremos payment_status en la BD
            $order->update([
                'notes' => ($order->notes ?? '') . "\nPayment status: {$request->payment_status}"
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Payment status updated successfully',
                'data' => $order->load(['orderItems.flower', 'statusHistory'])
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update payment status',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get order status by order number (public endpoint for payment success page)
     */
    public function getOrderStatus($orderNumber)
    {
        try {
            Log::info('Getting order status', [
                'order_number' => $orderNumber
            ]);

            // First try to find a real order
            $order = Order::where('order_number', $orderNumber)
                ->with(['payment', 'orderItems.flower'])
                ->first();

            if ($order) {
                // Get payment status
                $payment = $order->payment()->first();
                $paymentStatus = $payment ? $payment->payment_status : 'pending';

                // Format order items for email
                $items = $order->orderItems->map(function ($item) {
                    return [
                        'name' => $item->flower->name ?? 'Producto',
                        'quantity' => $item->quantity,
                        'price' => $item->price
                    ];
                });

                $orderData = [
                    'order_number' => $order->order_number,
                    'total' => $order->total,
                    'status' => $order->status,
                    'payment_status' => $paymentStatus,
                    'created_at' => $order->created_at->toISOString(),
                    'customer_info' => $order->customer_info,
                    'shipping_type' => $order->shipping_type,
                    'shipping_address' => $order->shipping_address,
                    'items' => $items
                ];

                return response()->json([
                    'success' => true,
                    'data' => $orderData
                ]);
            }

            // If no real order found, check for draft order
            $draftOrder = DraftOrder::where('draft_number', $orderNumber)->first();

            if ($draftOrder) {
                // Check if draft was converted to a real order
                if ($draftOrder->converted_to_order_id) {
                    $realOrder = $draftOrder->convertedOrder()->with(['payment', 'orderItems.flower'])->first();
                    if ($realOrder) {
                        $payment = $realOrder->payment()->first();
                        $paymentStatus = $payment ? $payment->payment_status : 'pending';

                        // Format order items for email
                        $items = $realOrder->orderItems->map(function ($item) {
                            return [
                                'name' => $item->flower->name ?? 'Producto',
                                'quantity' => $item->quantity,
                                'price' => $item->price
                            ];
                        });

                        $orderData = [
                            'order_number' => $realOrder->order_number,
                            'total' => $realOrder->total,
                            'status' => $realOrder->status,
                            'payment_status' => $paymentStatus,
                            'created_at' => $realOrder->created_at->toISOString(),
                            'customer_info' => $realOrder->customer_info,
                            'shipping_type' => $realOrder->shipping_type,
                            'shipping_address' => $realOrder->shipping_address,
                            'items' => $items
                        ];

                        return response()->json([
                            'success' => true,
                            'data' => $orderData
                        ]);
                    }
                }

                // Draft order exists but payment not completed yet
                return response()->json([
                    'success' => false,
                    'message' => 'Order payment pending or not completed'
                ], 402); // Payment required
            }

            return response()->json([
                'success' => false,
                'message' => 'Order not found'
            ], 404);

        } catch (\Exception $e) {
            Log::error('Failed to get order status', [
                'order_number' => $orderNumber,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to get order status'
            ], 500);
        }
    }

    /**
     * Confirmar pago exitoso desde el frontend
     */
    public function confirmPayment(Request $request)
    {
        \Log::info('PAYMENT_CONFIRM: Starting confirmPayment function', [
            'request_method' => $request->method(),
            'request_uri' => $request->getRequestUri(),
            'order_number' => $request->order_number ?? 'not_provided',
            'transaction_id' => $request->transaction_id ?? 'not_provided',
            'has_izipay_data' => $request->has('izipay_data'),
            'user_agent' => $request->header('User-Agent'),
            'ip_address' => $request->ip()
        ]);

        try {
            $validator = Validator::make($request->all(), [
                'order_number' => 'required|string',
                'transaction_id' => 'required|string',
                'izipay_data' => 'required|array'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 400);
            }

            Log::info('Confirming payment from frontend', [
                'order_number' => $request->order_number,
                'transaction_id' => $request->transaction_id,
                'izipay_data_structure' => [
                    'has_clientAnswer' => isset($request->izipay_data['clientAnswer']),
                    'has_rawClientAnswer' => isset($request->izipay_data['rawClientAnswer']),
                    'has_hash' => isset($request->izipay_data['hash'])
                ]
            ]);

            // VALIDACIÓN DE FIRMA SEGÚN EJEMPLO OFICIAL DE IZIPAY
            $izipayData = $request->izipay_data;
            $rawClientAnswer = $izipayData['rawClientAnswer'] ?? null;
            $receivedHash = $izipayData['hash'] ?? null;

            if (!$rawClientAnswer || !$receivedHash) {
                Log::error('Missing required iZiPay data', [
                    'has_rawClientAnswer' => !!$rawClientAnswer,
                    'has_hash' => !!$receivedHash
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'Datos de pago incompletos'
                ], 400);
            }

            // CORRECCIÓN: Para datos desde frontend usar HMAC SHA256 Key (no PASSWORD)
            // Según ejemplo oficial: Frontend = HMAC SHA256, IPN = PASSWORD
            $environment = config('services.izipay.environment', 'production');
            $hmacKey = $environment === 'test'
                ? config('services.izipay.test_hmac_key')
                : config('services.izipay.prod_hmac_key');

            Log::info('Using correct HMAC key for frontend validation', [
                'environment' => $environment,
                'hmac_key_length' => strlen($hmacKey),
                'hmac_key_preview' => substr($hmacKey, 0, 10) . '...'
            ]);

            if (!$this->checkHash($rawClientAnswer, $receivedHash, $hmacKey)) {
                Log::error('Invalid payment signature', [
                    'order_number' => $request->order_number,
                    'calculated_hash' => hash_hmac('sha256', str_replace('\/', '/', $rawClientAnswer), $hmacKey),
                    'received_hash' => $receivedHash,
                    'environment' => $environment,
                    'validation_type' => 'frontend_hmac_sha256'
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'Firma de pago inválida'
                ], 400);
            }

            Log::info('Payment signature validated successfully with HMAC SHA256', [
                'order_number' => $request->order_number,
                'validation_type' => 'frontend_hmac_sha256'
            ]);

            // First try to find a draft order (new flow)
            $draftOrder = DraftOrder::where('draft_number', $request->order_number)->first();

            if ($draftOrder) {
                Log::info('Found draft order, converting to real order after payment confirmation', [
                    'draft_id' => $draftOrder->id,
                    'draft_number' => $draftOrder->draft_number
                ]);

                // Verificar datos de Izipay
                $izipayData = $request->izipay_data;
                $orderStatus = $izipayData['clientAnswer']['orderStatus'] ?? '';
                $transactionStatus = $izipayData['clientAnswer']['transactions'][0]['status'] ?? '';

                Log::info('Payment status verification', [
                    'draft_number' => $draftOrder->draft_number,
                    'order_status' => $orderStatus,
                    'transaction_status' => $transactionStatus,
                    'client_answer' => $izipayData['clientAnswer'] ?? 'missing'
                ]);

                // Solo procesar si el pago es exitoso
                // Aceptar varios estados que indican pago exitoso
                $successStates = ['PAID', 'AUTHORISED', 'CAPTURED'];
                $isOrderSuccessful = in_array($orderStatus, $successStates);
                $isTransactionSuccessful = in_array($transactionStatus, $successStates);

                if ($isOrderSuccessful && $isTransactionSuccessful) {

                    \DB::beginTransaction();
                    try {
                        // Convert draft to real order (this also reduces stock)
                        $realOrder = $draftOrder->convertToOrder();

                        // Create payment record
                        $paymentData = [
                            'order_id' => $realOrder->id,
                            'payment_method' => 'izipay',
                            'payment_status' => 'completed',
                            'status' => 'completed',
                            'amount' => $realOrder->total,
                            'currency' => 'PEN',
                            'transaction_id' => $request->transaction_id,
                            'payment_details' => $izipayData,
                            'paid_at' => now()
                        ];

                        // Note: session_token field is not available in current database schema
                        // Will be added in future migration

                        $payment = Payment::create($paymentData);

                        \DB::commit();

                        Log::info('Payment confirmed and order created successfully', [
                            'draft_id' => $draftOrder->id,
                            'order_id' => $realOrder->id,
                            'payment_id' => $payment->id,
                            'transaction_id' => $request->transaction_id
                        ]);

                        // Limpiar cache de draft payment
                        cache()->forget("draft_payment_{$draftOrder->draft_number}");

                        return response()->json([
                            'success' => true,
                            'message' => 'Payment confirmed and order created successfully',
                            'data' => [
                                'order_number' => $realOrder->order_number, // Retornar ORD-XXXX
                                'draft_number' => $draftOrder->draft_number, // También retornar el draft para referencia
                                'status' => $realOrder->status,
                                'payment_status' => $payment->payment_status
                            ]
                        ]);

                    } catch (\Exception $e) {
                        \DB::rollBack();
                        Log::error('Failed to convert draft to order', [
                            'draft_id' => $draftOrder->id,
                            'error' => $e->getMessage()
                        ]);
                        throw $e;
                    }

                } else {
                    Log::warning('Payment status not successful', [
                        'draft_number' => $draftOrder->draft_number,
                        'expected_order_status' => 'PAID|AUTHORISED|CAPTURED',
                        'actual_order_status' => $orderStatus,
                        'expected_transaction_status' => 'PAID|AUTHORISED|CAPTURED',
                        'actual_transaction_status' => $transactionStatus,
                        'full_client_answer' => $izipayData['clientAnswer'] ?? 'missing'
                    ]);

                    return response()->json([
                        'success' => false,
                        'message' => 'Payment not successful',
                        'data' => [
                            'order_status' => $orderStatus,
                            'transaction_status' => $transactionStatus,
                            'debug_info' => 'Payment status verification failed - status not in success states'
                        ]
                    ], 400);
                }
            }

            // Fallback: Try to find existing order (old flow - for backwards compatibility)
            \Log::info('PAYMENT: Looking for existing order', [
                'order_number' => $request->order_number,
                'flow' => 'fallback_existing_order'
            ]);

            $order = Order::where('order_number', $request->order_number)->first();
            if (!$order) {
                \Log::warning('PAYMENT: Order not found in fallback flow', [
                    'order_number' => $request->order_number
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'Order or draft not found'
                ], 404);
            }

            \Log::info('PAYMENT: Found existing order', [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'status' => $order->status
            ]);

            // Buscar el pago asociado
            $payment = $order->payment()->first();
            if (!$payment) {
                \Log::warning('PAYMENT: Payment not found for existing order', [
                    'order_id' => $order->id,
                    'order_number' => $order->order_number
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'Payment not found'
                ], 404);
            }

            \Log::info('PAYMENT: Found payment for existing order', [
                'payment_id' => $payment->id,
                'order_id' => $order->id,
                'payment_status' => $payment->status,
                'transaction_id' => $payment->transaction_id
            ]);

            // Verificar datos de Izipay
            $izipayData = $request->izipay_data;
            $orderStatus = $izipayData['clientAnswer']['orderStatus'] ?? '';
            $transactionStatus = $izipayData['clientAnswer']['transactions'][0]['status'] ?? '';

            \Log::info('PAYMENT: Verifying iZiPay data for existing order', [
                'order_number' => $order->order_number,
                'order_status' => $orderStatus,
                'transaction_status' => $transactionStatus,
                'has_izipay_data' => !empty($izipayData),
                'flow' => 'existing_order'
            ]);

            // Solo procesar si el pago es exitoso
            $successStates = ['PAID', 'AUTHORISED', 'CAPTURED'];
            if (in_array($orderStatus, $successStates) && in_array($transactionStatus, $successStates)) {
                \Log::info('PAYMENT: Payment verified as successful for existing order', [
                    'order_number' => $order->order_number,
                    'order_status' => $orderStatus,
                    'transaction_status' => $transactionStatus
                ]);

                // Actualizar el pago con datos seguros
                $updateData = [
                    'payment_status' => 'completed',
                    'status' => 'completed',
                    'paid_at' => now(),
                    'payment_details' => $izipayData,
                    'transaction_id' => $request->transaction_id
                ];

                // Note: session_token field is not available in current database schema
                // Will be added in future migration

                $payment->update($updateData);

                // Actualizar la orden
                $order->update([
                    'status' => 'confirmed'
                ]);

                // Crear historial de estado
                OrderStatusHistory::create([
                    'order_id' => $order->id,
                    'status' => 'confirmed',
                    'notes' => 'Payment confirmed from Izipay',
                    'changed_by' => 'system'
                ]);

                Log::info('Payment confirmed successfully (old flow)', [
                    'order_id' => $order->id,
                    'payment_id' => $payment->id,
                    'transaction_id' => $request->transaction_id
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Payment confirmed successfully',
                    'data' => [
                        'order_number' => $order->order_number,
                        'status' => $order->status,
                        'payment_status' => $payment->payment_status
                    ]
                ]);
            } else {
                \Log::warning('PAYMENT: Payment not successful for existing order', [
                    'order_number' => $order->order_number,
                    'order_status' => $orderStatus,
                    'transaction_status' => $transactionStatus,
                    'debug_info' => 'Payment status verification failed - status not in success states',
                    'flow' => 'existing_order'
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Payment not successful',
                    'data' => [
                        'order_status' => $orderStatus,
                        'transaction_status' => $transactionStatus,
                        'debug_info' => 'Payment status verification failed - status not in success states'
                    ]
                ], 400);
            }

        } catch (\Exception $e) {
            Log::error('Failed to confirm payment', [
                'error' => $e->getMessage(),
                'order_number' => $request->order_number ?? 'unknown'
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to confirm payment'
            ], 500);
        }
    }

    /**
     * Delete a specific order (Admin only)
     */
    public function destroy(Request $request, $id)
    {
        try {
            // Find the order
            $order = Order::find($id);

            if (!$order) {
                return response()->json([
                    'success' => false,
                    'message' => 'Order not found'
                ], 404);
            }

            // Log the deletion for audit purposes
            Log::info('Admin deleting order', [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'admin_user' => $request->user()->id ?? 'unknown'
            ]);

            // Store order data for response before deletion
            $orderData = [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'total' => $order->total,
                'status' => $order->status
            ];

            // Delete related payment records first (if any)
            $order->payment()->delete();

            // Delete order status history
            $order->statusHistory()->delete();

            // Delete order items
            $order->orderItems()->delete();

            // Finally delete the order
            $order->delete();

            return response()->json([
                'success' => true,
                'message' => 'Order deleted successfully',
                'data' => $orderData
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to delete order', [
                'order_id' => $id,
                'error' => $e->getMessage(),
                'admin_user' => $request->user()->id ?? 'unknown'
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete order'
            ], 500);
        }
    }

    /**
     * Delete all orders (Admin only - for testing/maintenance)
     */
    public function destroyAll(Request $request)
    {
        try {
            // Get count for logging
            $totalOrders = Order::count();

            if ($totalOrders === 0) {
                return response()->json([
                    'success' => true,
                    'message' => 'No orders to delete',
                    'data' => ['deleted_count' => 0]
                ]);
            }

            // Log the bulk deletion for audit purposes
            Log::warning('Admin deleting ALL orders', [
                'total_orders' => $totalOrders,
                'admin_user' => $request->user()->id ?? 'unknown',
                'timestamp' => now()
            ]);

            \DB::beginTransaction();

            try {
                // Delete all related data first
                \DB::table('payments')->delete();
                \DB::table('order_status_histories')->delete();
                \DB::table('order_items')->delete();
                \DB::table('orders')->delete();

                \DB::commit();

                Log::info('Successfully deleted all orders', [
                    'deleted_count' => $totalOrders,
                    'admin_user' => $request->user()->id ?? 'unknown'
                ]);

                return response()->json([
                    'success' => true,
                    'message' => "Successfully deleted {$totalOrders} orders",
                    'data' => ['deleted_count' => $totalOrders]
                ]);

            } catch (\Exception $e) {
                \DB::rollBack();
                throw $e;
            }

        } catch (\Exception $e) {
            Log::error('Failed to delete all orders', [
                'error' => $e->getMessage(),
                'admin_user' => $request->user()->id ?? 'unknown'
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete all orders'
            ], 500);
        }
    }

    /**
     * Delete multiple selected orders (Admin only)
     */
    public function destroyMultiple(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'order_ids' => 'required|array|min:1',
                'order_ids.*' => 'integer|exists:orders,id'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 400);
            }

            $orderIds = $request->order_ids;

            // Get orders for logging
            $orders = Order::whereIn('id', $orderIds)->get();

            if ($orders->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No valid orders found'
                ], 404);
            }

            // Log the multiple deletion for audit purposes
            Log::info('Admin deleting multiple orders', [
                'order_ids' => $orderIds,
                'count' => count($orderIds),
                'admin_user' => $request->user()->id ?? 'unknown'
            ]);

            \DB::beginTransaction();

            try {
                // Delete related data first
                \DB::table('payments')->whereIn('order_id', $orderIds)->delete();
                \DB::table('order_status_histories')->whereIn('order_id', $orderIds)->delete();
                \DB::table('order_items')->whereIn('order_id', $orderIds)->delete();
                \DB::table('orders')->whereIn('id', $orderIds)->delete();

                \DB::commit();

                $deletedCount = count($orderIds);

                Log::info('Successfully deleted multiple orders', [
                    'deleted_count' => $deletedCount,
                    'admin_user' => $request->user()->id ?? 'unknown'
                ]);

                return response()->json([
                    'success' => true,
                    'message' => "Successfully deleted {$deletedCount} orders",
                    'data' => ['deleted_count' => $deletedCount]
                ]);

            } catch (\Exception $e) {
                \DB::rollBack();
                throw $e;
            }

        } catch (\Exception $e) {
            Log::error('Failed to delete multiple orders', [
                'error' => $e->getMessage(),
                'order_ids' => $request->order_ids ?? [],
                'admin_user' => $request->user()->id ?? 'unknown'
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete selected orders'
            ], 500);
        }
    }

    /**
     * Validar firma de pago según ejemplo oficial de iZiPay
     */
    private function checkHash(string $rawClientAnswer, string $receivedHash, string $hmacKey): bool
    {
        // Limpiar el rawClientAnswer como indica el ejemplo oficial
        $cleanAnswer = str_replace('\/', '/', $rawClientAnswer);

        // Calcular hash con HMAC SHA256
        $calculatedHash = hash_hmac('sha256', $cleanAnswer, $hmacKey);

        Log::info('Hash verification details', [
            'calculated_hash' => $calculatedHash,
            'received_hash' => $receivedHash,
            'raw_answer_length' => strlen($rawClientAnswer),
            'clean_answer_length' => strlen($cleanAnswer),
            'hmac_key_length' => strlen($hmacKey)
        ]);

        return hash_equals($calculatedHash, $receivedHash);
    }
}
