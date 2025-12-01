<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Breakfast;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class BreakfastController extends Controller
{
    /**
     * Get all breakfasts
     */
    public function index(Request $request)
    {
        $query = Breakfast::active();

        // Search
        if ($request->has('search') && $request->search) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'like', '%' . $request->search . '%')
                  ->orWhere('description', 'like', '%' . $request->search . '%');
            });
        }

        // Price range
        if ($request->has('min_price')) {
            $query->where('price', '>=', $request->min_price);
        }
        if ($request->has('max_price')) {
            $query->where('price', '<=', $request->max_price);
        }

        // Sort
        $sortBy = $request->get('sort_by', 'sort_order');
        $sortDir = $request->get('sort_dir', 'asc');
        $query->orderBy($sortBy, $sortDir);

        $breakfasts = $query->get();

        return response()->json([
            'success' => true,
            'data' => $breakfasts
        ]);
    }

    /**
     * Get featured breakfasts
     */
    public function featured()
    {
        $breakfasts = Cache::remember('featured_breakfasts', 300, function () {
            return Breakfast::active()
                ->featured()
                ->orderBy('sort_order', 'asc')
                ->limit(6)
                ->get();
        });

        return response()->json([
            'success' => true,
            'data' => $breakfasts
        ]);
    }

    /**
     * Get single breakfast
     */
    public function show($id)
    {
        $breakfast = Breakfast::findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $breakfast
        ]);
    }
}
