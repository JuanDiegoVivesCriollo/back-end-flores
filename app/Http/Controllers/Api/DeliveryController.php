<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DeliveryDistrict;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class DeliveryController extends Controller
{
    /**
     * Get all delivery districts
     */
    public function getDistricts()
    {
        $districts = Cache::remember('delivery_districts', 3600, function () {
            return DeliveryDistrict::active()
                ->ordered()
                ->get(['id', 'name', 'zone', 'shipping_cost', 'estimated_time']);
        });

        return response()->json([
            'success' => true,
            'data' => $districts
        ]);
    }

    /**
     * Calculate shipping cost
     */
    public function calculateShipping(Request $request)
    {
        $request->validate([
            'district' => 'required|string',
        ]);

        $district = DeliveryDistrict::where('name', $request->district)
            ->orWhere('slug', $request->district)
            ->first();

        if (!$district) {
            // Default shipping cost for unknown districts
            return response()->json([
                'success' => true,
                'data' => [
                    'district' => $request->district,
                    'shipping_cost' => 20.00,
                    'estimated_time' => '2-3 horas',
                    'note' => 'Distrito fuera de zona de cobertura regular'
                ]
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'district' => $district->name,
                'zone' => $district->zone,
                'shipping_cost' => $district->shipping_cost,
                'estimated_time' => $district->estimated_time,
            ]
        ]);
    }

    /**
     * Check if district has free shipping
     */
    public function checkFreeShipping(Request $request)
    {
        $request->validate([
            'district' => 'required|string',
            'total' => 'required|numeric|min:0',
        ]);

        $district = DeliveryDistrict::where('name', $request->district)->first();
        
        // Free shipping for orders over S/150 in main zones
        $freeShippingMinimum = 150;
        $eligibleZones = ['zona_1', 'zona_2'];
        
        $hasFreeShipping = $request->total >= $freeShippingMinimum && 
                          $district && 
                          in_array($district->zone, $eligibleZones);

        return response()->json([
            'success' => true,
            'data' => [
                'has_free_shipping' => $hasFreeShipping,
                'minimum_for_free_shipping' => $freeShippingMinimum,
                'current_total' => $request->total,
                'amount_needed' => max(0, $freeShippingMinimum - $request->total),
            ]
        ]);
    }
}
