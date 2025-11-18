<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class CategoryController extends Controller
{
    /**
     * Display a listing of categories
     */
    public function index(Request $request)
    {
        try {
            $query = Category::active()->ordered();

            // Include flower count if requested
            if ($request->get('include_flower_count', false)) {
                $query->withCount(['flowers' => function($q) {
                    $q->active()->inStock();
                }]);
            }

            // Include flowers if requested
            if ($request->get('include_flowers', false)) {
                $query->with(['activeFlowers' => function($q) {
                    $q->inStock()->ordered()->limit(4);
                }]);
            }

            $categories = $query->get();

            return response()->json([
                'success' => true,
                'data' => $categories
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch categories',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified category
     */
    public function show($id, Request $request)
    {
        try {
            $query = Category::active();

            // Include flowers if requested
            if ($request->get('include_flowers', false)) {
                $query->with(['activeFlowers' => function($q) use ($request) {
                    $q->inStock()->ordered();

                    if ($request->has('limit')) {
                        $q->limit($request->get('limit', 12));
                    }
                }]);
            }

            $category = $query->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $category
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Category not found'
            ], 404);
        }
    }

    /**
     * Store a newly created category (Admin only)
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:categories,name',
            'description' => 'nullable|string',
            'image_url' => 'nullable|string',
            'is_active' => 'boolean',
            'sort_order' => 'nullable|integer|min:0'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation errors',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $data = $validator->validated();

            // Set default sort order if not provided
            if (!isset($data['sort_order'])) {
                $data['sort_order'] = Category::max('sort_order') + 1;
            }

            $category = Category::create($data);

            return response()->json([
                'success' => true,
                'message' => 'Category created successfully',
                'data' => $category
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create category',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update the specified category (Admin only)
     */
    public function update(Request $request, $id)
    {
        $category = Category::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:categories,name,' . $category->id,
            'description' => 'nullable|string',
            'image_url' => 'nullable|string',
            'is_active' => 'boolean',
            'sort_order' => 'nullable|integer|min:0'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation errors',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $category->update($validator->validated());

            return response()->json([
                'success' => true,
                'message' => 'Category updated successfully',
                'data' => $category
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update category',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified category (Admin only)
     */
    public function destroy($id)
    {
        try {
            $category = Category::findOrFail($id);

            // Check if category has flowers
            if ($category->flowers()->count() > 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete category with existing flowers'
                ], 400);
            }

            $category->delete();

            return response()->json([
                'success' => true,
                'message' => 'Category deleted successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete category',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get categories with flower statistics
     */
    public function statistics()
    {
        try {
            $categories = Category::active()
                ->withCount([
                    'flowers' => function($q) {
                        $q->active();
                    },
                    'flowers as in_stock_flowers_count' => function($q) {
                        $q->active()->inStock();
                    },
                    'flowers as featured_flowers_count' => function($q) {
                        $q->active()->featured();
                    },
                    'flowers as sale_flowers_count' => function($q) {
                        $q->active()->onSale();
                    }
                ])
                ->ordered()
                ->get();

            return response()->json([
                'success' => true,
                'data' => $categories
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch category statistics',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
