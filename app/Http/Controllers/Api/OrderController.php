<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Flower;
use App\Models\Complement;
use App\Models\Breakfast;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class OrderController extends Controller
{
    /**
     * Get user orders
     */
    public function index(Request $request)
    {
        $user = $request->user();
        
        $orders = Order::with(['orderItems', 'payment'])
            ->where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        return response()->json([
            'success' => true,
            'data' => $orders->items(),
            'meta' => [
                'current_page' => $orders->currentPage(),
                'last_page' => $orders->lastPage(),
                'per_page' => $orders->perPage(),
                'total' => $orders->total(),
            ]
        ]);
    }

    /**
     * Create new order
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'items' => 'required|array|min:1',
            'items.*.type' => 'required|in:flower,complement,breakfast',
            'items.*.id' => 'required|integer',
            'items.*.quantity' => 'required|integer|min:1',
            'shipping_type' => 'required|in:pickup,delivery',
            'customer_name' => 'required|string|max:255',
            'customer_email' => 'required|email',
            'customer_phone' => 'required|string|max:20',
            'shipping_address' => 'required_if:shipping_type,delivery|array',
            'delivery_date' => 'nullable|date|after:today',
            'delivery_time_slot' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();

            // Calculate totals
            $subtotal = 0;
            $itemsData = [];

            foreach ($request->items as $item) {
                $model = null;
                $itemType = $item['type'];
                
                switch ($itemType) {
                    case 'flower':
                        $model = Flower::find($item['id']);
                        break;
                    case 'complement':
                        $model = Complement::find($item['id']);
                        break;
                    case 'breakfast':
                        $model = Breakfast::find($item['id']);
                        break;
                }

                if (!$model) {
                    throw new \Exception("Item not found: {$itemType} #{$item['id']}");
                }

                $itemTotal = $model->price * $item['quantity'];
                $subtotal += $itemTotal;

                $itemsData[] = [
                    'type' => $itemType,
                    'model' => $model,
                    'quantity' => $item['quantity'],
                    'price' => $model->price,
                    'total' => $itemTotal,
                    'options' => $item['options'] ?? null,
                    'notes' => $item['notes'] ?? null,
                ];
            }

            // Get shipping cost
            $shippingCost = 0;
            if ($request->shipping_type === 'delivery' && isset($request->shipping_address['district'])) {
                $district = \App\Models\DeliveryDistrict::where('name', $request->shipping_address['district'])->first();
                $shippingCost = $district ? $district->shipping_cost : 15;
            }

            $total = $subtotal + $shippingCost;

            // Create order
            $order = Order::create([
                'user_id' => $request->user()?->id,
                'status' => Order::STATUS_PENDING,
                'payment_status' => Order::PAYMENT_PENDING,
                'subtotal' => $subtotal,
                'shipping_cost' => $shippingCost,
                'total' => $total,
                'shipping_type' => $request->shipping_type,
                'shipping_address' => $request->shipping_address,
                'customer_name' => $request->customer_name,
                'customer_email' => $request->customer_email,
                'customer_phone' => $request->customer_phone,
                'customer_info' => [
                    'document_type' => $request->document_type ?? 'DNI',
                    'document_number' => $request->document_number,
                ],
                'notes' => $request->notes,
                'delivery_date' => $request->delivery_date,
                'delivery_time_slot' => $request->delivery_time_slot,
                'recipient_name' => $request->recipient_name,
                'recipient_phone' => $request->recipient_phone,
            ]);

            // Create order items
            foreach ($itemsData as $itemData) {
                $orderItem = new OrderItem([
                    'item_type' => $itemData['type'],
                    'name' => $itemData['model']->name,
                    'quantity' => $itemData['quantity'],
                    'price' => $itemData['price'],
                    'total' => $itemData['total'],
                    'options' => $itemData['options'],
                    'notes' => $itemData['notes'],
                ]);

                // Set the appropriate foreign key
                switch ($itemData['type']) {
                    case 'flower':
                        $orderItem->flower_id = $itemData['model']->id;
                        break;
                    case 'complement':
                        $orderItem->complement_id = $itemData['model']->id;
                        break;
                    case 'breakfast':
                        $orderItem->breakfast_id = $itemData['model']->id;
                        break;
                }

                $order->orderItems()->save($orderItem);
            }

            // Create initial status history
            $order->statusHistory()->create([
                'old_status' => null,
                'new_status' => Order::STATUS_PENDING,
                'notes' => 'Pedido creado'
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Pedido creado exitosamente',
                'data' => $order->load('orderItems')
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'Error al crear el pedido',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get order details
     */
    public function show($orderNumber)
    {
        $order = Order::with(['orderItems', 'payment', 'statusHistory'])
            ->where('order_number', $orderNumber)
            ->firstOrFail();

        return response()->json([
            'success' => true,
            'data' => $order
        ]);
    }

    /**
     * Cancel order
     */
    public function cancel(Request $request, $orderNumber)
    {
        $order = Order::where('order_number', $orderNumber)->firstOrFail();

        if (!$order->canBeCancelled()) {
            return response()->json([
                'success' => false,
                'message' => 'Este pedido no puede ser cancelado'
            ], 400);
        }

        $order->updateStatus(Order::STATUS_CANCELLED, $request->reason ?? 'Cancelado por el cliente');

        return response()->json([
            'success' => true,
            'message' => 'Pedido cancelado exitosamente'
        ]);
    }
}
