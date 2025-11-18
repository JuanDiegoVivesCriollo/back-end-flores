<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DeliveryDistrict;
use Illuminate\Http\Request;

class DeliveryController extends Controller
{
    /**
     * Get all active delivery districts
     */
    public function getDistricts()
    {
        try {
            $districts = DeliveryDistrict::active()
                                        ->orderBy('zone')
                                        ->orderBy('name')
                                        ->get();

            return response()->json([
                'success' => true,
                'data' => $districts
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener distritos',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get districts grouped by zone
     */
    public function getDistrictsByZone()
    {
        try {
            $districts = DeliveryDistrict::getByZone();

            return response()->json([
                'success' => true,
                'data' => $districts
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener distritos por zona',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get shipping cost for a specific district
     */
    public function getShippingCost(Request $request)
    {
        $request->validate([
            'district' => 'required|string|max:100'
        ]);

        try {
            $district = $request->district;
            $shippingCost = DeliveryDistrict::getShippingCost($district);
            $isAvailable = DeliveryDistrict::isAvailable($district);

            return response()->json([
                'success' => true,
                'data' => [
                    'district' => $district,
                    'shipping_cost' => $shippingCost,
                    'is_available' => $isAvailable,
                    'formatted_cost' => 'S/. ' . number_format($shippingCost, 2)
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al calcular costo de envío',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Check if delivery is available for district
     */
    public function checkAvailability(Request $request)
    {
        $request->validate([
            'district' => 'required|string|max:100'
        ]);

        try {
            $district = $request->district;
            $isAvailable = DeliveryDistrict::isAvailable($district);
            $shippingCost = $isAvailable ? DeliveryDistrict::getShippingCost($district) : 0;

            return response()->json([
                'success' => true,
                'data' => [
                    'district' => $district,
                    'is_available' => $isAvailable,
                    'shipping_cost' => $shippingCost,
                    'message' => $isAvailable
                        ? 'Entrega disponible'
                        : 'Distrito no disponible para entrega'
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al verificar disponibilidad',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
