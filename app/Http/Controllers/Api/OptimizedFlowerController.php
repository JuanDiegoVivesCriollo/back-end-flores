<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Flower;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;

class OptimizedFlowerController extends Controller
{
    /**
     * Helper para obtener la URL base según el entorno
     */
    private function getBaseUrl()
    {
        static $cachedBaseUrl = null;

        if ($cachedBaseUrl !== null) {
            return $cachedBaseUrl;
        }

        // Detectar si estamos en desarrollo local
        $request = request();
        if ($request) {
            $host = $request->getHost();
            $port = $request->getPort();

            // Si es localhost o 127.0.0.1, usar localhost
            if (in_array($host, ['localhost', '127.0.0.1']) || str_contains($host, 'localhost')) {
                $scheme = $request->getScheme();
                return $cachedBaseUrl = $scheme . '://' . $host . ($port != 80 && $port != 443 ? ':' . $port : '');
            }
        }

        // Forzar localhost para desarrollo mientras no esté en producción real
        if (config('app.env') !== 'production' && config('app.debug') === true) {
            return $cachedBaseUrl = 'http://localhost:8000';
        }

        // En producción, usar floresydetalleslima.com
        if (config('app.env') === 'production') {
            return $cachedBaseUrl = 'https://floresydetalleslima.com';
        }

        return $cachedBaseUrl = config('app.url');
    }

    /**
     * Procesar URLs de imágenes de forma eficiente
     */
    private function processImageUrls($flowers)
    {
        $baseUrl = $this->getBaseUrl();

        return $flowers->transform(function ($flower) use ($baseUrl) {
            if ($flower->images && is_array($flower->images) && !empty($flower->images)) {
                // Determinar si estamos en producción
                $isProduction = config('app.env') === 'production' || str_contains($baseUrl, 'floresydetalleslima.com');

                if ($isProduction) {
                    // En producción, usar /api/public/
                    $imageName = basename($flower->images[0]);
                    $cleanImageUrl = str_replace(' ', '%20', $imageName);
                    $flower->first_image = $baseUrl . '/api/public/storage/img/flores/' . $cleanImageUrl;

                    $flower->image_urls = array_map(function($img) use ($baseUrl) {
                        $imageName = basename($img);
                        $cleanImg = str_replace(' ', '%20', $imageName);
                        return $baseUrl . '/api/public/storage/img/flores/' . $cleanImg;
                    }, $flower->images);
                } else {
                    // En desarrollo, usar rutas normales
                    $imageName = basename($flower->images[0]);
                    $cleanImageUrl = str_replace(' ', '%20', $imageName);
                    $flower->first_image = $baseUrl . '/storage/img/flores/' . $cleanImageUrl;

                    $flower->image_urls = array_map(function($img) use ($baseUrl) {
                        $imageName = basename($img);
                        $cleanImg = str_replace(' ', '%20', $imageName);
                        return $baseUrl . '/storage/img/flores/' . $cleanImg;
                    }, $flower->images);
                }
            } else {
                // NO FORZAR PLACEHOLDER
                $flower->first_image = null;
                $flower->image_urls = [];
            }

            return $flower;
        });
    }

    /**
     * Display a listing of flowers con caché
     */
    public function index(Request $request)
    {
        try {
            $page = $request->get('page', 1);
            $perPage = $request->get('per_page', 12);
            $categoryId = $request->get('category_id');
            $search = $request->get('search');
            $sortBy = $request->get('sort_by', 'sort_order');

            // Generar clave de caché
            $cacheKey = "flowers_list_" . md5($page . '_' . $perPage . '_' . $categoryId . '_' . $search . '_' . $sortBy);

            // Cache deshabilitado temporalmente para debugging
            // Si necesitas cache, usa: Cache::remember($cacheKey, 30, function() { ... })
            // En lugar de 5 minutos (300 segundos), usar 30 segundos máximo
            $cachedResult = null; // Cache deshabilitado
            if ($cachedResult && !$request->has('refresh')) {
                return response()->json([
                    'success' => true,
                    'data' => $cachedResult,
                    'from_cache' => true
                ]);
            }

            // Optimización: usar select específico para reducir datos transferidos
            $query = Flower::select([
                'id', 'name', 'slug', 'description', 'short_description',
                'price', 'original_price', 'discount_percentage',
                'category_id', 'color', 'occasion', 'images',
                'rating', 'reviews_count', 'stock', 'is_featured',
                'is_on_sale', 'is_active', 'views', 'sort_order'
            ])
            ->where('is_active', true) // ✅ AGREGADO FILTRO FALTANTE
            ->with(['category:id,name,slug']);

            // Filter by category
            if ($categoryId) {
                $query->where('category_id', $categoryId);
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

            // Search by name or description
            if ($search) {
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

            // Occasion filter - simple LIKE para todas las ocasiones
            if ($request->has('occasion')) {
                $query->where('occasion', 'like', "%{$request->occasion}%");
            }

            // Metadata filter for condolencias subfiltros
            if ($request->has('tipo_condolencia')) {
                $query->whereJsonContains('metadata->tipo_condolencia', $request->tipo_condolencia);
            }

            // Sorting
            $sortDirection = $request->get('sort_direction', 'asc');

            if ($sortBy === 'price') {
                $query->orderBy('price', $sortDirection);
            } elseif ($sortBy === 'name') {
                $query->orderBy('name', $sortDirection);
            } elseif ($sortBy === 'created_at') {
                $query->orderBy('created_at', $sortDirection);
            } else {
                $query->orderBy('sort_order', 'asc')->orderBy('id', 'asc');
            }

            // Pagination
            $flowers = $query->paginate($perPage);

            // Procesar URLs de imágenes
            $this->processImageUrls($flowers->getCollection());

            // Cache deshabilitado temporalmente para debugging
            // Cache::put($cacheKey, $flowers, 300);

            return response()->json([
                'success' => true,
                'data' => $flowers,
                'from_cache' => false
            ])->header('Cache-Control', 'no-cache, no-store, must-revalidate')
              ->header('Pragma', 'no-cache')
              ->header('Expires', '0');

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch flowers',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get featured flowers con caché
     */
    public function featured(Request $request)
    {
        try {
            $limit = $request->get('limit', 8);
            $cacheKey = "featured_flowers_{$limit}";

            // Intentar obtener de caché (10 minutos)
            $cachedResult = Cache::get($cacheKey);
            if ($cachedResult && !$request->has('refresh')) {
                return response()->json([
                    'success' => true,
                    'data' => $cachedResult,
                    'from_cache' => true
                ]);
            }

            $flowers = Flower::select([
                'id', 'name', 'slug', 'price', 'original_price', 'discount_percentage',
                'category_id', 'color', 'images', 'rating', 'reviews_count',
                'stock', 'is_featured', 'is_on_sale', 'is_active'
            ])
            ->with(['category:id,name,slug'])
            ->where('is_active', true)
            ->where('is_featured', true)
            ->where('stock', '>', 0)
            ->orderBy('sort_order', 'asc')
            ->limit($limit)
            ->get();

            // Procesar URLs de imágenes
            $this->processImageUrls($flowers);

            // Cache deshabilitado temporalmente para debugging
            // Cache::put($cacheKey, $flowers, 600);

            return response()->json([
                'success' => true,
                'data' => $flowers,
                'from_cache' => false
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
     * Get flowers on sale con caché
     */
    public function onSale(Request $request)
    {
        try {
            $limit = $request->get('limit', 8);
            $cacheKey = "sale_flowers_{$limit}";

            // Intentar obtener de caché (5 minutos para ofertas)
            $cachedResult = Cache::get($cacheKey);
            if ($cachedResult && !$request->has('refresh')) {
                return response()->json([
                    'success' => true,
                    'data' => $cachedResult,
                    'from_cache' => true
                ]);
            }

            $flowers = Flower::select([
                'id', 'name', 'slug', 'price', 'original_price', 'discount_percentage',
                'category_id', 'color', 'images', 'rating', 'reviews_count',
                'stock', 'is_featured', 'is_on_sale', 'is_active'
            ])
            ->with(['category:id,name,slug'])
            ->where('is_active', true)
            ->where('is_on_sale', true)
            ->where('stock', '>', 0)
            ->orderBy('sort_order', 'asc')
            ->limit($limit)
            ->get();

            // Procesar URLs de imágenes
            $this->processImageUrls($flowers);

            // Cache deshabilitado temporalmente para debugging
            // Cache::put($cacheKey, $flowers, 300);

            return response()->json([
                'success' => true,
                'data' => $flowers,
                'from_cache' => false
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch sale flowers',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Show a specific flower
     */
    public function show($id)
    {
        try {
            $flower = Flower::with('category')->active()->findOrFail($id);

            // Ensure image URLs are appended
            $flower->append(['first_image', 'image_urls']);

            return response()->json([
                'success' => true,
                'data' => $flower
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Flower not found'
            ], 404);
        }
    }

    /**
     * Get flowers by category
     */
    public function byCategory($categoryId, Request $request)
    {
        try {
            $category = Category::active()->findOrFail($categoryId);

            $query = $category->activeFlowers()->with('category')->select([
                'id', 'name', 'slug', 'price', 'original_price', 'discount_percentage',
                'category_id', 'color', 'images', 'rating', 'reviews_count',
                'stock', 'is_featured', 'is_on_sale', 'is_active'
            ]);

            // Sorting
            $sortBy = $request->get('sort_by', 'sort_order');
            $sortDirection = $request->get('sort_direction', 'asc');

            if ($sortBy === 'price') {
                $query->orderBy('price', $sortDirection);
            } elseif ($sortBy === 'name') {
                $query->orderBy('name', $sortDirection);
            } else {
                $query->orderBy('sort_order', 'asc');
            }

            // Pagination
            $perPage = $request->get('per_page', 12);
            $flowers = $query->paginate($perPage);

            // Append image URLs to each flower
            $flowers->getCollection()->transform(function ($flower) {
                $flower->append(['first_image', 'image_urls']);
                return $flower;
            });

            return response()->json([
                'success' => true,
                'data' => [
                    'category' => $category,
                    'flowers' => $flowers
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Category not found or no flowers available'
            ], 404);
        }
    }

    /**
     * Limpiar caché manualmente
     */
    public function clearCache()
    {
        Cache::forget('flowers_index');
        Cache::forget('flowers_featured');
        Cache::forget('flowers_on_sale');

        return response()->json([
            'success' => true,
            'message' => 'Cache cleared successfully'
        ]);
    }
}
