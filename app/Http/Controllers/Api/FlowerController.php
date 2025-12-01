<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Flower;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class FlowerController extends Controller
{
    /**
     * Get all flowers with filters
     */
    public function index(Request $request)
    {
        $query = Flower::with(['category', 'flowerTypes'])->active();

        // Filter by category
        if ($request->has('category_id') && $request->category_id) {
            $query->where('category_id', $request->category_id);
        }

        // Filter by color
        if ($request->has('color') && $request->color && $request->color !== 'Todos') {
            $query->where('color', $request->color);
        }

        // Filter by occasion
        if ($request->has('occasion') && $request->occasion && $request->occasion !== 'Todas') {
            $query->where('occasion', $request->occasion);
        }

        // Filter by price range
        if ($request->has('min_price')) {
            $query->where('price', '>=', $request->min_price);
        }
        if ($request->has('max_price')) {
            $query->where('price', '<=', $request->max_price);
        }

        // Search by name
        if ($request->has('search') && $request->search) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'like', '%' . $request->search . '%')
                  ->orWhere('description', 'like', '%' . $request->search . '%');
            });
        }

        // Filter featured
        if ($request->has('featured') && $request->featured) {
            $query->featured();
        }

        // Filter on sale
        if ($request->has('on_sale') && $request->on_sale) {
            $query->onSale();
        }

        // Sorting
        $sortBy = $request->get('sort_by', 'popular');
        switch ($sortBy) {
            case 'price-asc':
                $query->orderBy('price', 'asc');
                break;
            case 'price-desc':
                $query->orderBy('price', 'desc');
                break;
            case 'rating':
                $query->orderBy('rating', 'desc');
                break;
            case 'newest':
                $query->orderBy('created_at', 'desc');
                break;
            case 'popular':
            default:
                $query->orderBy('views', 'desc')->orderBy('rating', 'desc');
                break;
        }

        // Pagination
        $perPage = min($request->get('per_page', 20), 100);
        $flowers = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $flowers->items(),
            'meta' => [
                'current_page' => $flowers->currentPage(),
                'last_page' => $flowers->lastPage(),
                'per_page' => $flowers->perPage(),
                'total' => $flowers->total(),
            ]
        ]);
    }

    /**
     * Get featured flowers
     */
    public function featured()
    {
        $flowers = Cache::remember('featured_flowers', 300, function () {
            return Flower::with(['category', 'flowerTypes'])
                ->active()
                ->featured()
                ->orderBy('sort_order', 'asc')
                ->limit(12)
                ->get();
        });

        return response()->json([
            'success' => true,
            'data' => $flowers
        ]);
    }

    /**
     * Get flowers on sale
     */
    public function onSale()
    {
        $flowers = Cache::remember('flowers_on_sale', 300, function () {
            return Flower::with(['category', 'flowerTypes'])
                ->active()
                ->onSale()
                ->orderBy('discount_percentage', 'desc')
                ->limit(20)
                ->get();
        });

        return response()->json([
            'success' => true,
            'data' => $flowers
        ]);
    }

    /**
     * Get single flower
     */
    public function show($id)
    {
        $flower = Flower::with(['category', 'flowerTypes'])->findOrFail($id);
        
        // Increment views
        $flower->incrementViews();

        return response()->json([
            'success' => true,
            'data' => $flower
        ]);
    }

    /**
     * Get flowers by category
     */
    public function byCategory($categoryId)
    {
        $flowers = Flower::with(['category', 'flowerTypes'])
            ->active()
            ->where('category_id', $categoryId)
            ->orderBy('sort_order', 'asc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $flowers
        ]);
    }

    /**
     * Get available colors
     */
    public function getColors()
    {
        $colors = Flower::active()
            ->distinct()
            ->pluck('color')
            ->filter()
            ->values();

        return response()->json([
            'success' => true,
            'data' => $colors
        ]);
    }

    /**
     * Get available occasions
     */
    public function getOccasions()
    {
        $occasions = Flower::active()
            ->distinct()
            ->pluck('occasion')
            ->filter()
            ->values();

        return response()->json([
            'success' => true,
            'data' => $occasions
        ]);
    }

    /**
     * Search flowers
     */
    public function search(Request $request)
    {
        $query = $request->get('q', '');
        
        if (strlen($query) < 2) {
            return response()->json([
                'success' => true,
                'data' => []
            ]);
        }

        $flowers = Flower::with(['category', 'flowerTypes'])
            ->active()
            ->where(function ($q) use ($query) {
                $q->where('name', 'like', '%' . $query . '%')
                  ->orWhere('description', 'like', '%' . $query . '%')
                  ->orWhere('color', 'like', '%' . $query . '%');
            })
            ->limit(10)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $flowers
        ]);
    }

    /**
     * Get all flower types
     */
    public function getFlowerTypes()
    {
        $types = \App\Models\FlowerType::active()
            ->orderBy('sort_order', 'asc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $types
        ]);
    }
}
