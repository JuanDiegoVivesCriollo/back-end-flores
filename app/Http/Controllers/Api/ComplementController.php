<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Complement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class ComplementController extends Controller
{
    /**
     * Get all complements
     */
    public function index(Request $request)
    {
        $query = Complement::active();

        // Filter by type
        if ($request->has('type') && $request->type) {
            $query->byType($request->type);
        }

        // Search
        if ($request->has('search') && $request->search) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }

        $complements = $query->orderBy('sort_order', 'asc')->get();

        return response()->json([
            'success' => true,
            'data' => $complements
        ]);
    }

    /**
     * Get complement types
     */
    public function getTypes()
    {
        $types = [
            ['value' => 'chocolates', 'label' => 'Chocolates'],
            ['value' => 'peluches', 'label' => 'Peluches'],
            ['value' => 'globos', 'label' => 'Globos'],
            ['value' => 'tarjetas', 'label' => 'Tarjetas'],
            ['value' => 'decoraciones', 'label' => 'Decoraciones'],
        ];

        return response()->json([
            'success' => true,
            'data' => $types
        ]);
    }

    /**
     * Get featured complements
     */
    public function featured()
    {
        $complements = Cache::remember('featured_complements', 300, function () {
            return Complement::active()
                ->featured()
                ->orderBy('sort_order', 'asc')
                ->limit(8)
                ->get();
        });

        return response()->json([
            'success' => true,
            'data' => $complements
        ]);
    }

    /**
     * Get single complement
     */
    public function show($id)
    {
        $complement = Complement::findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $complement
        ]);
    }
}
