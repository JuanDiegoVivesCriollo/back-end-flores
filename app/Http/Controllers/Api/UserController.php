<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class UserController extends Controller
{
    /**
     * Obtener todos los usuarios (solo para admin)
     */
    public function index(Request $request)
    {
        try {
            \Log::info('UserController@index - Starting request', [
                'request_params' => $request->all(),
                'authenticated_user' => auth()->user() ? auth()->user()->toArray() : null
            ]);

            $query = User::query();

            // Filtros
            if ($request->filled('role')) {
                $query->where('role', $request->role);
            }

            if ($request->filled('is_active')) {
                $query->where('is_active', $request->boolean('is_active'));
            }

            if ($request->filled('search')) {
                $search = $request->search;
                $query->where(function($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%")
                      ->orWhere('phone', 'like', "%{$search}%");
                });
            }

            // Ordenamiento
            $sortBy = $request->get('sort_by', 'created_at');
            $sortDirection = $request->get('sort_direction', 'desc');
            $query->orderBy($sortBy, $sortDirection);

            // Paginación
            $perPage = $request->get('per_page', 15);
            $users = $query->paginate($perPage);

            \Log::info('UserController@index - Users found', [
                'users_count' => $users->count(),
                'total' => $users->total()
            ]);

            // Agregar estadísticas a cada usuario (simplificado para evitar problemas)
            $users->getCollection()->transform(function ($user) {
                try {
                    $user->orders_count = $user->orders()->count();
                    $user->total_spent = $user->orders()
                        ->where('status', 'completed')
                        ->sum('total');
                    $user->last_order_date = $user->orders()
                        ->orderBy('created_at', 'desc')
                        ->value('created_at');
                } catch (\Exception $e) {
                    \Log::warning('Error calculating user stats', [
                        'user_id' => $user->id,
                        'error' => $e->getMessage()
                    ]);
                    // Set default values if there's an error
                    $user->orders_count = 0;
                    $user->total_spent = 0;
                    $user->last_order_date = null;
                }

                return $user;
            });

            \Log::info('UserController@index - Response prepared successfully');

            return response()->json([
                'success' => true,
                'data' => $users
            ]);
        } catch (\Exception $e) {
            \Log::error('UserController@index - Error occurred', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al obtener usuarios',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener un usuario específico con sus pedidos
     */
    public function show($id)
    {
        try {
            $user = User::with(['orders' => function($query) {
                $query->orderBy('created_at', 'desc')->limit(10);
            }])->findOrFail($id);

            // Estadísticas del usuario
            $stats = [
                'total_orders' => $user->orders()->count(),
                'completed_orders' => $user->orders()->where('status', 'completed')->count(),
                'pending_orders' => $user->orders()->where('status', 'pending')->count(),
                'cancelled_orders' => $user->orders()->where('status', 'cancelled')->count(),
                'total_spent' => $user->orders()->where('status', 'completed')->sum('total'),
                'average_order_value' => $user->orders()->where('status', 'completed')->avg('total'),
                'first_order_date' => $user->orders()->orderBy('created_at', 'asc')->value('created_at'),
                'last_order_date' => $user->orders()->orderBy('created_at', 'desc')->value('created_at'),
            ];

            $user->stats = $stats;

            return response()->json([
                'success' => true,
                'data' => $user
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Usuario no encontrado',
                'error' => $e->getMessage()
            ], 404);
        }
    }

    /**
     * Obtener pedidos de un usuario específico
     */
    public function getUserOrders($id, Request $request)
    {
        try {
            $user = User::findOrFail($id);

            $query = $user->orders();

            // Filtros
            if ($request->filled('status')) {
                $query->where('status', $request->status);
            }

            if ($request->filled('payment_status')) {
                $query->where('payment_status', $request->payment_status);
            }

            // Ordenamiento
            $sortBy = $request->get('sort_by', 'created_at');
            $sortDirection = $request->get('sort_direction', 'desc');
            $query->orderBy($sortBy, $sortDirection);

            // Paginación
            $perPage = $request->get('per_page', 10);
            $orders = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => $orders
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener pedidos del usuario',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Actualizar estado de un usuario (activar/desactivar)
     */
    public function updateStatus($id, Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'is_active' => 'required|boolean',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Datos de validación incorrectos',
                    'errors' => $validator->errors()
                ], 422);
            }

            $user = User::findOrFail($id);

            // No permitir desactivar al usuario actual si es admin
            if (auth()->id() === $user->id && !$request->is_active) {
                return response()->json([
                    'success' => false,
                    'message' => 'No puedes desactivar tu propia cuenta'
                ], 403);
            }

            $user->update([
                'is_active' => $request->is_active
            ]);

            return response()->json([
                'success' => true,
                'message' => $request->is_active ? 'Usuario activado' : 'Usuario desactivado',
                'data' => $user
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar estado del usuario',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener estadísticas generales de usuarios
     */
    public function statistics()
    {
        try {
            $stats = [
                'total_users' => User::count(),
                'active_users' => User::where('is_active', true)->count(),
                'inactive_users' => User::where('is_active', false)->count(),
                'admins' => User::where('role', 'admin')->count(),
                'customers' => User::where('role', 'user')->count(),
                'users_with_orders' => User::has('orders')->count(),
                'users_without_orders' => User::doesntHave('orders')->count(),
                'new_users_this_month' => User::whereMonth('created_at', now()->month)
                    ->whereYear('created_at', now()->year)
                    ->count(),
                'total_customer_spent' => User::where('role', 'user')
                    ->whereHas('orders', function($query) {
                        $query->where('status', 'completed');
                    })
                    ->withSum(['orders as total_spent' => function($query) {
                        $query->where('status', 'completed');
                    }], 'total')
                    ->sum('total_spent'),
            ];

            return response()->json([
                'success' => true,
                'data' => $stats
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener estadísticas',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
