<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\User;
use App\Models\Flower;
use App\Models\Category;
use App\Models\OrderItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class DashboardController extends Controller
{
    /**
     * Get dashboard overview statistics
     */
    public function overview(Request $request)
    {
        try {
            $today = Carbon::today();
            $thisMonth = Carbon::now()->startOfMonth();
            $lastMonth = Carbon::now()->subMonth()->startOfMonth();
            $lastMonthEnd = Carbon::now()->subMonth()->endOfMonth();

            // Basic counts
            $totalOrders = Order::count();
            $totalCustomers = User::where('role', 'user')->count();
            $totalFlowers = Flower::count();
            $activeFlowers = Flower::active()->count();

            // Today's stats
            $todayOrders = Order::whereDate('created_at', $today)->count();
            $todayRevenue = Order::whereDate('created_at', $today)
                ->where('status', 'delivered')
                ->sum('total');

            // This month stats
            $monthOrders = Order::where('created_at', '>=', $thisMonth)->count();
            $monthRevenue = Order::where('created_at', '>=', $thisMonth)
                ->where('status', 'delivered')
                ->sum('total');

            // Last month stats for comparison
            $lastMonthOrders = Order::whereBetween('created_at', [$lastMonth, $lastMonthEnd])->count();
            $lastMonthRevenue = Order::whereBetween('created_at', [$lastMonth, $lastMonthEnd])
                ->where('status', 'delivered')
                ->sum('total');

            // Order status breakdown
            $ordersByStatus = Order::select('status', DB::raw('count(*) as count'))
                ->groupBy('status')
                ->pluck('count', 'status');

            // Recent orders
            $recentOrders = Order::with(['user', 'orderItems.flower'])
                ->orderBy('created_at', 'desc')
                ->limit(10)
                ->get();

            // Low stock flowers
            $lowStockFlowers = Flower::active()
                ->where('stock_quantity', '<=', 5)
                ->orderBy('stock_quantity', 'asc')
                ->limit(10)
                ->get();

            // Best selling flowers (this month)
            $bestSellers = OrderItem::select('flower_id', DB::raw('SUM(quantity) as total_sold'))
                ->whereHas('order', function($q) use ($thisMonth) {
                    $q->where('created_at', '>=', $thisMonth)
                      ->where('status', 'delivered');
                })
                ->with('flower')
                ->groupBy('flower_id')
                ->orderBy('total_sold', 'desc')
                ->limit(5)
                ->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'overview' => [
                        'total_orders' => $totalOrders,
                        'total_customers' => $totalCustomers,
                        'total_flowers' => $totalFlowers,
                        'active_flowers' => $activeFlowers,
                        'today_orders' => $todayOrders,
                        'today_revenue' => round($todayRevenue, 2),
                        'month_orders' => $monthOrders,
                        'month_revenue' => round($monthRevenue, 2),
                        'last_month_orders' => $lastMonthOrders,
                        'last_month_revenue' => round($lastMonthRevenue, 2),
                        'orders_growth' => $lastMonthOrders > 0 ? round((($monthOrders - $lastMonthOrders) / $lastMonthOrders) * 100, 1) : 0,
                        'revenue_growth' => $lastMonthRevenue > 0 ? round((($monthRevenue - $lastMonthRevenue) / $lastMonthRevenue) * 100, 1) : 0,
                    ],
                    'orders_by_status' => $ordersByStatus,
                    'recent_orders' => $recentOrders,
                    'low_stock_flowers' => $lowStockFlowers,
                    'best_sellers' => $bestSellers
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch dashboard data',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get sales analytics
     */
    public function salesAnalytics(Request $request)
    {
        try {
            $period = $request->get('period', '7days'); // 7days, 30days, 3months, 1year

            $startDate = match($period) {
                '7days' => Carbon::now()->subDays(6)->startOfDay(),
                '30days' => Carbon::now()->subDays(29)->startOfDay(),
                '3months' => Carbon::now()->subMonths(3)->startOfMonth(),
                '1year' => Carbon::now()->subYear()->startOfMonth(),
                default => Carbon::now()->subDays(6)->startOfDay()
            };

            // Daily sales data
            $salesData = Order::select(
                    DB::raw('DATE(created_at) as date'),
                    DB::raw('COUNT(*) as orders'),
                    DB::raw('SUM(CASE WHEN status = "delivered" THEN total ELSE 0 END) as revenue'),
                    DB::raw('SUM(CASE WHEN status = "delivered" THEN 1 ELSE 0 END) as paid_orders')
                )
                ->where('created_at', '>=', $startDate)
                ->groupBy(DB::raw('DATE(created_at)'))
                ->orderBy('date')
                ->get();

            // Category sales breakdown
            $categorySales = DB::table('order_items')
                ->join('orders', 'order_items.order_id', '=', 'orders.id')
                ->join('flowers', 'order_items.flower_id', '=', 'flowers.id')
                ->join('categories', 'flowers.category_id', '=', 'categories.id')
                ->where('orders.created_at', '>=', $startDate)
                ->where('orders.status', 'delivered')
                ->select(
                    'categories.name as category',
                    DB::raw('SUM(order_items.quantity) as quantity_sold'),
                    DB::raw('SUM(order_items.total) as revenue')
                )
                ->groupBy('categories.id', 'categories.name')
                ->orderBy('revenue', 'desc')
                ->get();

            // Payment method breakdown
            $paymentMethods = Order::select(
                    'payment_method',
                    DB::raw('COUNT(*) as orders'),
                    DB::raw('SUM(CASE WHEN status = "delivered" THEN total ELSE 0 END) as revenue')
                )
                ->where('created_at', '>=', $startDate)
                ->groupBy('payment_method')
                ->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'period' => $period,
                    'start_date' => $startDate->format('Y-m-d'),
                    'end_date' => Carbon::now()->format('Y-m-d'),
                    'daily_sales' => $salesData,
                    'category_sales' => $categorySales,
                    'payment_methods' => $paymentMethods
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch sales analytics',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get customer analytics
     */
    public function customerAnalytics()
    {
        try {
            // New customers by month (last 6 months)
            $newCustomers = User::select(
                    DB::raw('DATE_FORMAT(created_at, "%Y-%m") as month'),
                    DB::raw('COUNT(*) as count')
                )
                ->where('role', 'user')
                ->where('created_at', '>=', Carbon::now()->subMonths(5)->startOfMonth())
                ->groupBy(DB::raw('DATE_FORMAT(created_at, "%Y-%m")'))
                ->orderBy('month')
                ->get();

            // Top customers by order value
            $topCustomers = User::select('users.*')
                ->join('orders', 'users.id', '=', 'orders.user_id')
                ->where('users.role', 'user')
                ->where('orders.status', 'delivered')
                ->selectRaw('users.*, COUNT(orders.id) as order_count, SUM(orders.total) as total_spent')
                ->groupBy('users.id')
                ->orderBy('total_spent', 'desc')
                ->limit(10)
                ->get();

            // Customer order frequency
            $orderFrequency = DB::table('orders')
                ->select(DB::raw('user_id, COUNT(*) as order_count'))
                ->where('status', 'delivered')
                ->groupBy('user_id')
                ->havingRaw('COUNT(*) > 1')
                ->get()
                ->groupBy('order_count')
                ->map(function($group) {
                    return $group->count();
                });

            return response()->json([
                'success' => true,
                'data' => [
                    'new_customers_by_month' => $newCustomers,
                    'top_customers' => $topCustomers,
                    'order_frequency' => $orderFrequency
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch customer analytics',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get inventory analytics
     */
    public function inventoryAnalytics()
    {
        try {
            // Stock levels by category
            $stockByCategory = Flower::join('categories', 'flowers.category_id', '=', 'categories.id')
                ->select(
                    'categories.name as category',
                    DB::raw('SUM(flowers.stock_quantity) as total_stock'),
                    DB::raw('COUNT(flowers.id) as flower_count'),
                    DB::raw('AVG(flowers.stock_quantity) as avg_stock')
                )
                ->where('flowers.is_active', true)
                ->groupBy('categories.id', 'categories.name')
                ->orderBy('total_stock', 'desc')
                ->get();

            // Low stock alerts
            $lowStockAlerts = Flower::active()
                ->where('stock_quantity', '<=', 5)
                ->with('category')
                ->orderBy('stock_quantity', 'asc')
                ->get();

            // Out of stock items
            $outOfStock = Flower::active()
                ->where('stock_quantity', 0)
                ->with('category')
                ->count();

            // Stock value by category
            $stockValue = Flower::join('categories', 'flowers.category_id', '=', 'categories.id')
                ->select(
                    'categories.name as category',
                    DB::raw('SUM(flowers.stock_quantity * flowers.price) as stock_value')
                )
                ->where('flowers.is_active', true)
                ->groupBy('categories.id', 'categories.name')
                ->orderBy('stock_value', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'stock_by_category' => $stockByCategory,
                    'low_stock_alerts' => $lowStockAlerts,
                    'out_of_stock_count' => $outOfStock,
                    'stock_value_by_category' => $stockValue
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch inventory analytics',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
