<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Flower;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class FlowerController extends Controller
{
    /**
     * Display a listing of flowers
     */
    public function index(Request $request)
    {
        try {
            $query = Flower::with(['category', 'categories']); // Cargar tanto la categoría individual como múltiples

            // Filter by category
            if ($request->has('category_id')) {
                $query->where('category_id', $request->category_id);
            }

            // Filter by featured
            if ($request->has('featured') && $request->featured) {
                $query->where('is_featured', true);
            }

            // Filter by on sale
            if ($request->has('on_sale') && $request->on_sale) {
                $query->where('is_on_sale', true);
            }

            // Filter by in stock
            if ($request->has('in_stock') && $request->in_stock) {
                $query->where('stock', '>', 0);
            }

            // Filter by active status
            if ($request->has('active')) {
                if ($request->active) {
                    $query->where('is_active', true);
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

            // Occasion filter
            if ($request->has('occasion')) {
                $query->where('occasion', 'like', "%{$request->occasion}%");
            }

            // Metadata filter for condolencias subfiltros
            if ($request->has('tipo_condolencia')) {
                $query->whereJsonContains('metadata->tipo_condolencia', $request->tipo_condolencia);
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
                $query->orderBy('sort_order', 'asc');
            }

            // Pagination
            $perPage = $request->get('per_page', 12);
            $flowers = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => $flowers->items(),
                'pagination' => [
                    'current_page' => $flowers->currentPage(),
                    'last_page' => $flowers->lastPage(),
                    'per_page' => $flowers->perPage(),
                    'total' => $flowers->total(),
                    'from' => $flowers->firstItem(),
                    'to' => $flowers->lastItem(),
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch flowers',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified flower
     */
    public function show($id)
    {
        try {
            $flower = Flower::findOrFail($id);

            // Increment views
            $flower->increment('views');

            return response()->json([
                'success' => true,
                'data' => $flower
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Flower not found',
                'error' => $e->getMessage()
            ], 404);
        }
    }

    /**
     * Get featured flowers
     */
    public function featured(Request $request)
    {
        try {
            $limit = $request->get('limit', 8);

            $flowers = Flower::where('is_featured', true)
                ->where('is_active', true)
                ->orderBy('sort_order', 'asc')
                ->limit($limit)
                ->get();

            return response()->json([
                'success' => true,
                'data' => $flowers
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch featured flowers',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get flowers on sale
     */
    public function onSale(Request $request)
    {
        try {
            $limit = $request->get('limit', 8);

            $flowers = Flower::where('is_on_sale', true)
                ->where('is_active', true)
                ->orderBy('sort_order', 'asc')
                ->limit($limit)
                ->get();

            return response()->json([
                'success' => true,
                'data' => $flowers
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch flowers on sale',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get flower categories for filters
     */
    public function getCategories()
    {
        try {
            $categories = [
                [
                    'id' => 1,
                    'name' => 'Rosas',
                    'description' => 'Hermosas rosas para toda ocasión'
                ],
                [
                    'id' => 2,
                    'name' => 'Tulipanes',
                    'description' => 'Tulipanes frescos y coloridos'
                ],
                [
                    'id' => 3,
                    'name' => 'Girasoles',
                    'description' => 'Girasoles brillantes y alegres'
                ],
                [
                    'id' => 4,
                    'name' => 'Orquídeas',
                    'description' => 'Orquídeas elegantes y exóticas'
                ],
                [
                    'id' => 5,
                    'name' => 'Lirios',
                    'description' => 'Lirios fragantes y sofisticados'
                ],
                [
                    'id' => 6,
                    'name' => 'Claveles',
                    'description' => 'Claveles duraderos y vibrantes'
                ],
                [
                    'id' => 7,
                    'name' => 'Gerberas',
                    'description' => 'Gerberas coloridas y alegres'
                ],
                [
                    'id' => 8,
                    'name' => 'Lilies',
                    'description' => 'Lilies elegantes y aromáticos'
                ],
                [
                    'id' => 9,
                    'name' => 'Mixtas',
                    'description' => 'Arreglos florales mixtos'
                ]
            ];

            return response()->json([
                'success' => true,
                'data' => $categories
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch flower categories',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // Admin methods (crear, actualizar, eliminar)
    public function store(Request $request)
    {
        \Log::info('🌸 FLOWER STORE ATTEMPT:', $request->all());

        try {
            // Validación básica y robusta
            $rules = [
                'name' => 'required|string|max:255',
                'description' => 'required|string',
                'price' => 'required|numeric|min:0',
                'category_ids' => 'required|array|min:1', // Cambiar a array de categorías
                'category_ids.*' => 'integer|exists:categories,id', // Validar cada categoría
                'color' => 'required|string|max:255',
                'occasion' => 'required|string|max:255',
                'stock' => 'required|integer|min:0',
                'images' => 'required|array|min:1'
            ];

            $validator = Validator::make($request->all(), $rules);

            if ($validator->fails()) {
                \Log::warning('🌸 VALIDATION FAILED:', $validator->errors()->toArray());
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Preparar datos para inserción
            $data = [
                'name' => $request->name,
                'description' => $request->description,
                'short_description' => $request->short_description ?? $request->description,
                'price' => floatval($request->price),
                'original_price' => floatval($request->original_price ?? $request->price),
                'discount_percentage' => intval($request->discount_percentage ?? 0),
                'category_id' => intval($request->category_ids[0]), // Mantener por compatibilidad (primera categoría)
                'color' => $request->color,
                'occasion' => $request->occasion,
                'stock' => intval($request->stock),
                'images' => json_encode($request->images),
                'slug' => $this->generateUniqueSlug($request->name),
                'sku' => $this->generateUniqueSku(),
                'rating' => 5.0,
                'reviews_count' => 0,
                'is_featured' => boolval($request->is_featured ?? false),
                'is_on_sale' => boolval($request->is_on_sale ?? true),
                'is_active' => true,
                'views' => 0,
                'sort_order' => 1,
                'metadata' => json_encode($request->metadata ?? [])
            ];

            \Log::info('🌸 DATA PREPARED:', $data);

            // Crear la flor
            $flower = Flower::create($data);

            // Asociar múltiples categorías
            $flower->categories()->attach($request->category_ids);

            // Cargar las relaciones para la respuesta
            $flower->load(['category', 'categories']);

            \Log::info('🌸 FLOWER CREATED:', ['id' => $flower->id, 'name' => $flower->name, 'categories' => $flower->categories->pluck('name')]);

            return response()->json([
                'success' => true,
                'data' => $flower,
                'message' => 'Flower created successfully'
            ], 201);

        } catch (\Exception $e) {
            \Log::error('🌸 STORE ERROR:', [
                'message' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Internal server error: ' . $e->getMessage(),
                'error_details' => [
                    'line' => $e->getLine(),
                    'file' => basename($e->getFile())
                ]
            ], 500);
        }
    }

    public function update(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'description' => 'sometimes|string',
            'price' => 'sometimes|numeric|min:0',
            'category_ids' => 'sometimes|array|min:1', // Cambiar a array de categorías
            'category_ids.*' => 'integer|exists:categories,id', // Validar cada categoría
            'color' => 'sometimes|string|max:255',
            'occasion' => 'sometimes|string|max:255',
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
            $flower = Flower::findOrFail($id);

            // Actualizar datos básicos
            $updateData = $request->except(['category_ids']);

            // Si se envían categorías, actualizar también category_id por compatibilidad
            if ($request->has('category_ids')) {
                $updateData['category_id'] = $request->category_ids[0];
            }

            $flower->update($updateData);

            // Actualizar múltiples categorías si se enviaron
            if ($request->has('category_ids')) {
                $flower->categories()->sync($request->category_ids);
            }

            // Cargar las relaciones para la respuesta
            $flower->load(['category', 'categories']);

            return response()->json([
                'success' => true,
                'data' => $flower,
                'message' => 'Flower updated successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update flower',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function destroy($id)
    {
        try {
            \Log::info("🗑️ Attempting to delete flower with ID: {$id}");

            // Verificar que la flor existe usando withTrashed para incluir las ya eliminadas
            $flower = Flower::withTrashed()->find($id);

            if (!$flower) {
                \Log::warning("Flower {$id} not found in database");
                return response()->json([
                    'success' => false,
                    'message' => 'Flower not found'
                ], 404);
            }

            // Si ya está eliminada (soft deleted), enviar mensaje específico
            if ($flower->trashed()) {
                \Log::info("Flower {$id} is already deleted");
                return response()->json([
                    'success' => true,
                    'message' => 'Flower was already deleted'
                ]);
            }

            \Log::info("🖼️ Processing images for flower: {$flower->name}");

            // Eliminar imágenes físicas primero
            if (!empty($flower->images)) {
                $images = is_string($flower->images) ? json_decode($flower->images, true) : $flower->images;
                if (is_array($images)) {
                    foreach ($images as $imageUrl) {
                        if (!empty($imageUrl)) {
                            // Convertir URL a path del storage
                            $imagePath = str_replace('/storage/', '', $imageUrl);
                            if (Storage::disk('public')->exists($imagePath)) {
                                Storage::disk('public')->delete($imagePath);
                                \Log::info("✅ Deleted image: {$imagePath}");
                            } else {
                                \Log::info("⚠️ Image not found on disk: {$imagePath}");
                            }
                        }
                    }
                }
            }

            // Usar soft delete
            $deleted = $flower->delete();

            if ($deleted) {
                \Log::info("✅ Flower {$id} ({$flower->name}) deleted successfully");
                return response()->json([
                    'success' => true,
                    'message' => 'Flower deleted successfully'
                ]);
            } else {
                \Log::error("❌ Failed to delete flower {$id} - delete() returned false");
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to delete flower - database operation failed'
                ], 500);
            }

        } catch (\Exception $e) {
            \Log::error("❌ Error deleting flower {$id}: " . $e->getMessage());
            \Log::error("Stack trace: " . $e->getTraceAsString());

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete flower',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Generate unique slug for flower
     */
    private function generateUniqueSlug($name)
    {
        $slug = \Illuminate\Support\Str::slug($name);
        $originalSlug = $slug;
        $counter = 1;

        // Solo verificar flores que NO están soft-deleted
        while (Flower::whereNull('deleted_at')->where('slug', $slug)->exists()) {
            $slug = $originalSlug . '-' . $counter;
            $counter++;
        }

        return $slug;
    }

    /**
     * Generate unique SKU for flower
     */
    private function generateUniqueSku()
    {
        do {
            $sku = 'FL-' . time() . '-' . rand(100, 999);
        } while (Flower::whereNull('deleted_at')->where('sku', $sku)->exists());

        return $sku;
    }
}
