<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Complement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;

class ComplementController extends Controller
{
    /**
     * Display a listing of complements
     */
    public function index(Request $request)
    {
        try {
            $query = Complement::with([]);

            // Filter by type
            if ($request->has('type')) {
                $query->byType($request->type);
            }

            // Filter by featured
            if ($request->has('featured') && $request->featured) {
                $query->featured();
            }

            // Filter by on sale
            if ($request->has('on_sale') && $request->on_sale) {
                $query->onSale();
            }

            // Filter by in stock
            if ($request->has('in_stock') && $request->in_stock) {
                $query->inStock();
            }

            // Filter by active status
            if ($request->has('active')) {
                if ($request->active) {
                    $query->active();
                } else {
                    $query->where('is_active', false);
                }
            }

            // Search by name or description
            if ($request->has('search')) {
                $search = $request->search;
                $query->where(function($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('description', 'like', "%{$search}%");
                });
            }

            // Price range filter
            if ($request->has('min_price')) {
                $query->where('price', '>=', $request->min_price);
            }
            if ($request->has('max_price')) {
                $query->where('price', '<=', $request->max_price);
            }

            // Color filter
            if ($request->has('color')) {
                $query->where('color', 'like', "%{$request->color}%");
            }

            // Size filter (for peluches and globos)
            if ($request->has('size')) {
                $query->where('size', 'like', "%{$request->size}%");
            }

            // Brand filter (for chocolates)
            if ($request->has('brand')) {
                $query->where('brand', 'like', "%{$request->brand}%");
            }

            // Sorting
            $sortBy = $request->get('sort_by', 'sort_order');
            $sortDirection = $request->get('sort_direction', 'asc');

            if ($sortBy === 'price') {
                $query->orderBy('price', $sortDirection);
            } elseif ($sortBy === 'name') {
                $query->orderBy('name', $sortDirection);
            } elseif ($sortBy === 'rating') {
                $query->orderBy('rating', $sortDirection);
            } elseif ($sortBy === 'created_at') {
                $query->orderBy('created_at', $sortDirection);
            } else {
                $query->ordered();
            }

            // Pagination
            $perPage = $request->get('per_page', 12);
            $complements = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => $complements
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch complements',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display complements by type
     */
    public function byType($type, Request $request)
    {
        try {
            $validTypes = ['globos', 'peluches', 'chocolates'];

            if (!in_array($type, $validTypes)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid complement type'
                ], 400);
            }

            $query = Complement::byType($type);

            // Apply filters similar to index method
            if ($request->has('featured') && $request->featured) {
                $query->featured();
            }

            if ($request->has('on_sale') && $request->on_sale) {
                $query->onSale();
            }

            if ($request->has('in_stock') && $request->in_stock) {
                $query->inStock();
            }

            if ($request->has('search')) {
                $search = $request->search;
                $query->where(function($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('description', 'like', "%{$search}%");
                });
            }

            // Sorting
            $sortBy = $request->get('sort_by', 'sort_order');
            $sortDirection = $request->get('sort_direction', 'asc');

            if ($sortBy === 'price') {
                $query->orderBy('price', $sortDirection);
            } elseif ($sortBy === 'name') {
                $query->orderBy('name', $sortDirection);
            } else {
                $query->ordered();
            }

            $perPage = $request->get('per_page', 12);
            $complements = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => [
                    'type' => $type,
                    'complements' => $complements
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch complements by type',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified complement
     */
    public function show($id)
    {
        try {
            $complement = Complement::findOrFail($id);

            // Increment views
            $complement->increment('views');

            return response()->json([
                'success' => true,
                'data' => $complement
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Complement not found'
            ], 404);
        }
    }

    /**
     * Get featured complements
     */
    public function featured(Request $request)
    {
        try {
            $limit = $request->get('limit', 8);

            $complements = Complement::featured()
                ->active()
                ->inStock()
                ->ordered()
                ->limit($limit)
                ->get();

            return response()->json([
                'success' => true,
                'data' => $complements
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch featured complements',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get complements on sale
     */
    public function onSale(Request $request)
    {
        try {
            $limit = $request->get('limit', 8);

            $complements = Complement::onSale()
                ->active()
                ->inStock()
                ->ordered()
                ->limit($limit)
                ->get();

            return response()->json([
                'success' => true,
                'data' => $complements
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch complements on sale',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get complement types for filters
     */
    public function getTypes()
    {
        try {
            $types = [
                [
                    'id' => 'globos',
                    'name' => 'Globos',
                    'description' => 'Globos decorativos para ocasiones especiales'
                ],
                [
                    'id' => 'peluches',
                    'name' => 'Peluches',
                    'description' => 'Peluches tiernos y adorables'
                ],
                [
                    'id' => 'chocolates',
                    'name' => 'Chocolates',
                    'description' => 'Deliciosos chocolates gourmet'
                ]
            ];

            return response()->json([
                'success' => true,
                'data' => $types
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch complement types',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // Admin methods (crear, actualizar, eliminar)
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'description' => 'required|string',
            'price' => 'required|numeric|min:0',
            'type' => 'required|in:globos,peluches,chocolates',
            'stock' => 'required|integer|min:0',
            'images' => 'required|array|min:1',
            'images.*' => 'string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation errors',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $complement = Complement::create($request->all());

            return response()->json([
                'success' => true,
                'data' => $complement,
                'message' => 'Complement created successfully'
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create complement',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function update(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'description' => 'sometimes|string',
            'price' => 'sometimes|numeric|min:0',
            'type' => 'sometimes|in:globos,peluches,chocolates',
            'stock' => 'sometimes|integer|min:0',
            'images' => 'sometimes|array|min:1',
            'images.*' => 'string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation errors',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $complement = Complement::findOrFail($id);
            $complement->update($request->all());

            return response()->json([
                'success' => true,
                'data' => $complement,
                'message' => 'Complement updated successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update complement',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function destroy($id)
    {
        try {
            $complement = Complement::findOrFail($id);
            $complement->delete();

            return response()->json([
                'success' => true,
                'message' => 'Complement deleted successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete complement',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
