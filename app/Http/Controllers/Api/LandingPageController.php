<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LandingPageContent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class LandingPageController extends Controller
{
    /**
     * Obtener todo el contenido de la landing page
     */
    public function index()
    {
        try {
            $content = LandingPageContent::active()
                ->orderBy('section')
                ->orderBy('key')
                ->get()
                ->groupBy('section');

            return response()->json([
                'success' => true,
                'data' => $content
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener contenido',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener contenido por sección
     */
    public function getBySection($section)
    {
        try {
            $content = LandingPageContent::bySection($section)
                ->active()
                ->get()
                ->keyBy('key');

            return response()->json([
                'success' => true,
                'data' => $content
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener contenido de la sección',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Actualizar contenido (solo para admin)
     */
    public function update(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'section' => 'required|string|max:255',
                'key' => 'required|string|max:255',
                'value' => 'required|string',
                'type' => 'string|in:text,html,image,json',
                'description' => 'nullable|string'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Datos de validación incorrectos',
                    'errors' => $validator->errors()
                ], 422);
            }

            $content = LandingPageContent::setContent(
                $request->section,
                $request->key,
                $request->value,
                $request->type ?? 'text',
                $request->description
            );

            return response()->json([
                'success' => true,
                'message' => 'Contenido actualizado exitosamente',
                'data' => $content
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar contenido',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Actualizar múltiples contenidos de una vez
     */
    public function updateBulk(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'contents' => 'required|array',
                'contents.*.section' => 'required|string|max:255',
                'contents.*.key' => 'required|string|max:255',
                'contents.*.value' => 'required|string',
                'contents.*.type' => 'string|in:text,html,image,json',
                'contents.*.description' => 'nullable|string'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Datos de validación incorrectos',
                    'errors' => $validator->errors()
                ], 422);
            }

            $updated = [];
            foreach ($request->contents as $contentData) {
                $content = LandingPageContent::setContent(
                    $contentData['section'],
                    $contentData['key'],
                    $contentData['value'],
                    $contentData['type'] ?? 'text',
                    $contentData['description'] ?? null
                );
                $updated[] = $content;
            }

            return response()->json([
                'success' => true,
                'message' => 'Contenidos actualizados exitosamente',
                'data' => $updated
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar contenidos',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
