<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Flower;
use App\Models\Breakfast;
use App\Models\Complement;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AdminController extends Controller
{
    // =====================================================
    // USERS MANAGEMENT
    // =====================================================

    /**
     * Get all users (optionally filtered by role)
     */
    public function getUsers(Request $request)
    {
        $query = User::query();

        // Filter by role if provided
        if ($request->has('role')) {
            $query->where('role', $request->role);
        }

        $users = $query->orderBy('created_at', 'desc')->get();

        return response()->json([
            'success' => true,
            'data' => $users
        ]);
    }

    /**
     * Get a single user
     */
    public function getUser($id)
    {
        $user = User::find($id);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Usuario no encontrado'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $user
        ]);
    }

    /**
     * Get user's orders
     */
    public function getUserOrders($id)
    {
        $user = User::find($id);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Usuario no encontrado'
            ], 404);
        }

        $orders = Order::where('user_id', $id)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $orders
        ]);
    }

    /**
     * Toggle user active status
     */
    public function toggleUserStatus($id, Request $request)
    {
        $user = User::find($id);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Usuario no encontrado'
            ], 404);
        }

        $user->is_active = $request->is_active ?? !$user->is_active;
        $user->save();

        return response()->json([
            'success' => true,
            'data' => $user
        ]);
    }

    // =====================================================
    // FLOWERS MANAGEMENT
    // =====================================================

    /**
     * Create a new flower
     */
    public function createFlower(Request $request)
    {
        \Log::info('createFlower called', [
            'all_data' => $request->all(),
            'has_file' => $request->hasFile('image'),
            'files' => $request->allFiles(),
            'content_type' => $request->header('Content-Type'),
        ]);

        $request->validate([
            'name' => 'required|string|max:255',
            'price' => 'required|numeric|min:0',
        ]);

        $data = $request->all();
        $data['slug'] = Str::slug($data['name']) . '-' . Str::random(5);
        $data['sku'] = 'FL-' . strtoupper(Str::random(8));

        // Extract flower_types before creating
        $flowerTypeIds = null;
        if (isset($data['flower_types'])) {
            $flowerTypeIds = $data['flower_types'];
            unset($data['flower_types']);
        }

        // Handle image upload
        if ($request->hasFile('image')) {
            \Log::info('Image file detected for flower');
            $image = $request->file('image');
            $imageName = time() . '_' . Str::slug($data['name']) . '.' . $image->getClientOriginalExtension();
            $stored = $image->storeAs('public/flowers', $imageName);
            \Log::info('Image stored', ['path' => $stored, 'imageName' => $imageName]);
            $data['images'] = ['flowers/' . $imageName];
        } else {
            \Log::info('No image file detected for flower');
        }

        $flower = Flower::create($data);
        \Log::info('Flower created', ['id' => $flower->id, 'images' => $flower->images]);

        // Attach flower types if provided
        if ($flowerTypeIds !== null) {
            if (is_string($flowerTypeIds)) {
                $flowerTypeIds = array_filter(explode(',', $flowerTypeIds));
            }
            $flower->flowerTypes()->attach($flowerTypeIds);
        }

        // Reload with relationships
        $flower->load(['category', 'flowerTypes']);

        return response()->json([
            'success' => true,
            'message' => 'Ramo creado exitosamente',
            'data' => $flower
        ], 201);
    }

    /**
     * Update a flower
     */
    public function updateFlower($id, Request $request)
    {
        $flower = Flower::find($id);

        if (!$flower) {
            return response()->json([
                'success' => false,
                'message' => 'Ramo no encontrado'
            ], 404);
        }

        $data = $request->all();

        // Handle image upload
        if ($request->hasFile('image')) {
            // Delete old image if exists
            if ($flower->images && count($flower->images) > 0) {
                Storage::delete('public/' . $flower->images[0]);
            }
            
            $image = $request->file('image');
            $imageName = time() . '_' . Str::slug($data['name'] ?? $flower->name) . '.' . $image->getClientOriginalExtension();
            $image->storeAs('public/flowers', $imageName);
            $data['images'] = ['flowers/' . $imageName];
        }

        // Remove flower_types from data before update (handled separately)
        $flowerTypeIds = null;
        if (isset($data['flower_types'])) {
            $flowerTypeIds = $data['flower_types'];
            unset($data['flower_types']);
        }

        $flower->update($data);

        // Sync flower types if provided
        if ($flowerTypeIds !== null) {
            // Accept array of IDs or comma-separated string
            if (is_string($flowerTypeIds)) {
                $flowerTypeIds = array_filter(explode(',', $flowerTypeIds));
            }
            $flower->flowerTypes()->sync($flowerTypeIds);
        }

        // Reload with relationships
        $flower->load(['category', 'flowerTypes']);

        return response()->json([
            'success' => true,
            'message' => 'Ramo actualizado exitosamente',
            'data' => $flower
        ]);
    }

    /**
     * Delete a flower
     */
    public function deleteFlower($id)
    {
        $flower = Flower::find($id);

        if (!$flower) {
            return response()->json([
                'success' => false,
                'message' => 'Ramo no encontrado'
            ], 404);
        }

        // Delete images
        if ($flower->images && count($flower->images) > 0) {
            foreach ($flower->images as $image) {
                Storage::delete('public/' . $image);
            }
        }

        $flower->delete();

        return response()->json([
            'success' => true,
            'message' => 'Ramo eliminado exitosamente'
        ]);
    }

    // =====================================================
    // BREAKFASTS MANAGEMENT
    // =====================================================

    /**
     * Create a new breakfast
     */
    public function createBreakfast(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'price' => 'required|numeric|min:0',
        ]);

        $data = $request->all();
        $data['slug'] = Str::slug($data['name']) . '-' . Str::random(5);
        $data['sku'] = 'BR-' . strtoupper(Str::random(8));

        // Handle image upload
        if ($request->hasFile('image')) {
            $image = $request->file('image');
            $imageName = time() . '_' . Str::slug($data['name']) . '.' . $image->getClientOriginalExtension();
            $image->storeAs('public/breakfasts', $imageName);
            $data['images'] = ['breakfasts/' . $imageName];
        }

        $breakfast = Breakfast::create($data);

        return response()->json([
            'success' => true,
            'message' => 'Desayuno creado exitosamente',
            'data' => $breakfast
        ], 201);
    }

    /**
     * Update a breakfast
     */
    public function updateBreakfast($id, Request $request)
    {
        $breakfast = Breakfast::find($id);

        if (!$breakfast) {
            return response()->json([
                'success' => false,
                'message' => 'Desayuno no encontrado'
            ], 404);
        }

        $data = $request->all();

        // Handle image upload
        if ($request->hasFile('image')) {
            if ($breakfast->images && count($breakfast->images) > 0) {
                Storage::delete('public/' . $breakfast->images[0]);
            }
            
            $image = $request->file('image');
            $imageName = time() . '_' . Str::slug($data['name'] ?? $breakfast->name) . '.' . $image->getClientOriginalExtension();
            $image->storeAs('public/breakfasts', $imageName);
            $data['images'] = ['breakfasts/' . $imageName];
        }

        $breakfast->update($data);

        return response()->json([
            'success' => true,
            'message' => 'Desayuno actualizado exitosamente',
            'data' => $breakfast
        ]);
    }

    /**
     * Delete a breakfast
     */
    public function deleteBreakfast($id)
    {
        $breakfast = Breakfast::find($id);

        if (!$breakfast) {
            return response()->json([
                'success' => false,
                'message' => 'Desayuno no encontrado'
            ], 404);
        }

        if ($breakfast->images && count($breakfast->images) > 0) {
            foreach ($breakfast->images as $image) {
                Storage::delete('public/' . $image);
            }
        }

        $breakfast->delete();

        return response()->json([
            'success' => true,
            'message' => 'Desayuno eliminado exitosamente'
        ]);
    }

    // =====================================================
    // COMPLEMENTS MANAGEMENT
    // =====================================================

    /**
     * Create a new complement
     */
    public function createComplement(Request $request)
    {
        \Log::info('createComplement called', [
            'all_data' => $request->all(),
            'has_file' => $request->hasFile('image'),
            'files' => $request->allFiles(),
            'content_type' => $request->header('Content-Type'),
        ]);

        $request->validate([
            'name' => 'required|string|max:255',
            'price' => 'required|numeric|min:0',
        ]);

        $data = $request->all();
        $data['slug'] = Str::slug($data['name']) . '-' . Str::random(5);
        $data['sku'] = 'CP-' . strtoupper(Str::random(8));

        // Handle image upload
        if ($request->hasFile('image')) {
            \Log::info('Image file detected for complement');
            $image = $request->file('image');
            $imageName = time() . '_' . Str::slug($data['name']) . '.' . $image->getClientOriginalExtension();
            $stored = $image->storeAs('public/complements', $imageName);
            \Log::info('Image stored', ['path' => $stored, 'imageName' => $imageName]);
            $data['images'] = ['complements/' . $imageName];
        } else {
            \Log::info('No image file detected for complement');
        }

        $complement = Complement::create($data);
        \Log::info('Complement created', ['id' => $complement->id, 'images' => $complement->images]);

        return response()->json([
            'success' => true,
            'message' => 'Complemento creado exitosamente',
            'data' => $complement
        ], 201);
    }

    /**
     * Update a complement
     */
    public function updateComplement($id, Request $request)
    {
        $complement = Complement::find($id);

        if (!$complement) {
            return response()->json([
                'success' => false,
                'message' => 'Complemento no encontrado'
            ], 404);
        }

        $data = $request->all();

        // Handle image upload
        if ($request->hasFile('image')) {
            if ($complement->images && count($complement->images) > 0) {
                Storage::delete('public/' . $complement->images[0]);
            }
            
            $image = $request->file('image');
            $imageName = time() . '_' . Str::slug($data['name'] ?? $complement->name) . '.' . $image->getClientOriginalExtension();
            $image->storeAs('public/complements', $imageName);
            $data['images'] = ['complements/' . $imageName];
        }

        $complement->update($data);

        return response()->json([
            'success' => true,
            'message' => 'Complemento actualizado exitosamente',
            'data' => $complement
        ]);
    }

    /**
     * Delete a complement
     */
    public function deleteComplement($id)
    {
        $complement = Complement::find($id);

        if (!$complement) {
            return response()->json([
                'success' => false,
                'message' => 'Complemento no encontrado'
            ], 404);
        }

        if ($complement->images && count($complement->images) > 0) {
            foreach ($complement->images as $image) {
                Storage::delete('public/' . $image);
            }
        }

        $complement->delete();

        return response()->json([
            'success' => true,
            'message' => 'Complemento eliminado exitosamente'
        ]);
    }

    // =====================================================
    // ORDERS MANAGEMENT
    // =====================================================

    /**
     * Get all orders
     */
    public function getOrders(Request $request)
    {
        $query = Order::with(['user', 'items']);

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Filter by payment status
        if ($request->has('payment_status')) {
            $query->where('payment_status', $request->payment_status);
        }

        $orders = $query->orderBy('created_at', 'desc')->get();

        return response()->json([
            'success' => true,
            'data' => $orders
        ]);
    }

    /**
     * Get a single order
     */
    public function getOrder($id)
    {
        $order = Order::with(['user', 'items'])->find($id);

        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'Pedido no encontrado'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $order
        ]);
    }

    /**
     * Update order status
     */
    public function updateOrderStatus($id, Request $request)
    {
        $order = Order::find($id);

        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'Pedido no encontrado'
            ], 404);
        }

        $request->validate([
            'status' => 'sometimes|string|in:pending,processing,shipped,delivered,cancelled',
            'payment_status' => 'sometimes|string|in:pending,paid,failed,refunded',
        ]);

        if ($request->has('status')) {
            $order->status = $request->status;
        }

        if ($request->has('payment_status')) {
            $order->payment_status = $request->payment_status;
        }

        $order->save();

        return response()->json([
            'success' => true,
            'message' => 'Estado del pedido actualizado',
            'data' => $order
        ]);
    }

    // =====================================================
    // DASHBOARD STATISTICS
    // =====================================================

    /**
     * Get dashboard statistics
     */
    public function getDashboardStats()
    {
        $totalUsers = User::where('role', 'user')->count();
        $totalOrders = Order::count();
        $totalSales = Order::where('payment_status', 'paid')->sum('total');
        $totalProducts = Flower::count() + Breakfast::count() + Complement::count();

        // Recent orders
        $recentOrders = Order::with('user')
            ->orderBy('created_at', 'desc')
            ->take(5)
            ->get();

        // Orders by status
        $ordersByStatus = Order::selectRaw('status, count(*) as count')
            ->groupBy('status')
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'total_customers' => $totalUsers,
                'total_orders' => $totalOrders,
                'total_sales' => $totalSales,
                'total_products' => $totalProducts,
                'recent_orders' => $recentOrders,
                'orders_by_status' => $ordersByStatus,
            ]
        ]);
    }
}
