<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class CategoryController extends Controller
{
    /**
     * Get all categories
     */
    public function index()
    {
        $categories = Cache::remember('categories_active', 600, function () {
            return Category::active()
                ->ordered()
                ->withCount(['flowers' => function ($query) {
                    $query->where('is_active', true);
                }])
                ->get();
        });

        return response()->json([
            'success' => true,
            'data' => $categories
        ]);
    }

    /**
     * Get single category
     */
    public function show($id)
    {
        $category = Category::with(['flowers' => function ($query) {
            $query->active()->orderBy('sort_order', 'asc');
        }])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $category
        ]);
    }

    /**
     * Get category statistics
     */
    public function statistics($id)
    {
        $category = Category::findOrFail($id);
        
        $stats = [
            'total_flowers' => $category->flowers()->count(),
            'active_flowers' => $category->flowers()->active()->count(),
            'on_sale_flowers' => $category->flowers()->active()->onSale()->count(),
            'featured_flowers' => $category->flowers()->active()->featured()->count(),
            'price_range' => [
                'min' => $category->flowers()->active()->min('price'),
                'max' => $category->flowers()->active()->max('price'),
            ],
            'average_rating' => $category->flowers()->active()->avg('rating'),
        ];

        return response()->json([
            'success' => true,
            'data' => $stats
        ]);
    }
}
