<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class ImageController extends Controller
{
    /**
     * Upload an image file - ENHANCED VERSION
     */
    public function upload(Request $request)
    {
        // Log inicial detallado para debugging
        \Log::info('📸 IMAGE UPLOAD REQUEST RECEIVED:', [
            'has_file' => $request->hasFile('image'),
            'folder' => $request->get('folder'),
            'all_input' => $request->all(),
            'files' => $request->allFiles(),
            'content_type' => $request->header('Content-Type'),
            'user_id' => $request->user()->id ?? 'No user'
        ]);

        $validator = Validator::make($request->all(), [
            'image' => 'required|image|mimes:jpeg,png,jpg,gif,webp|max:5120', // 5MB max
            'folder' => 'nullable|string|max:50'
        ]);

        if ($validator->fails()) {
            \Log::error('📸 IMAGE UPLOAD VALIDATION FAILED:', [
                'errors' => $validator->errors(),
                'request_data' => $request->all()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $image = $request->file('image');
            $folder = $request->get('folder', 'uploads');

            \Log::info('📸 PROCESSING IMAGE UPLOAD:', [
                'original_name' => $image->getClientOriginalName(),
                'size' => $image->getSize(),
                'mime_type' => $image->getMimeType(),
                'folder' => $folder,
                'temp_path' => $image->getPathname(),
                'is_valid' => $image->isValid()
            ]);

            // Verificar que el directorio existe
            $targetDir = storage_path("app/public/img/{$folder}");
            if (!is_dir($targetDir)) {
                \Log::info('📸 CREATING TARGET DIRECTORY:', ['dir' => $targetDir]);
                mkdir($targetDir, 0755, true);
            }

            // Generate unique filename
            $filename = time() . '_' . Str::random(10) . '.' . $image->getClientOriginalExtension();

            \Log::info('📸 STORING IMAGE:', [
                'filename' => $filename,
                'target_dir' => $targetDir,
                'full_path' => $targetDir . '/' . $filename
            ]);

            // Store in public disk under specified folder
            $path = $image->storeAs("img/{$folder}", $filename, 'public');

            // Verificar que el archivo se guardó correctamente
            $fullPath = storage_path("app/public/{$path}");
            $fileExists = file_exists($fullPath);
            $fileSize = $fileExists ? filesize($fullPath) : 0;

            // Generate public URL
            $url = '/storage/' . $path;

            \Log::info('📸 IMAGE UPLOAD COMPLETED:', [
                'path' => $path,
                'url' => $url,
                'filename' => $filename,
                'full_path' => $fullPath,
                'file_exists' => $fileExists,
                'file_size' => $fileSize,
                'original_size' => $image->getSize()
            ]);

            if (!$fileExists) {
                throw new \Exception("File was not saved successfully to: {$fullPath}");
            }

            return response()->json([
                'success' => true,
                'message' => 'Image uploaded successfully',
                'data' => [
                    'url' => $url,
                    'path' => $path,
                    'filename' => $filename,
                    'size' => $fileSize,
                    'mime_type' => $image->getMimeType(),
                    'full_path' => $fullPath,
                    'file_exists' => $fileExists
                ]
            ]);

        } catch (\Exception $e) {
            \Log::error('📸 IMAGE UPLOAD FAILED:', [
                'error' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile(),
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->all()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to upload image',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete an uploaded image
     */
    public function delete(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'path' => 'required|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $path = $request->get('path');

            // Remove /storage/ prefix if present
            if (Str::startsWith($path, '/storage/')) {
                $path = Str::after($path, '/storage/');
            }

            if (Storage::disk('public')->exists($path)) {
                Storage::disk('public')->delete($path);

                return response()->json([
                    'success' => true,
                    'message' => 'Image deleted successfully'
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Image not found'
                ], 404);
            }

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete image',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get image info
     */
    public function info(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'path' => 'required|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $path = $request->get('path');

            // Remove /storage/ prefix if present
            if (Str::startsWith($path, '/storage/')) {
                $path = Str::after($path, '/storage/');
            }

            if (Storage::disk('public')->exists($path)) {
                $size = Storage::disk('public')->size($path);
                $lastModified = Storage::disk('public')->lastModified($path);
                $mimeType = Storage::disk('public')->mimeType($path);

                return response()->json([
                    'success' => true,
                    'data' => [
                        'path' => $path,
                        'size' => $size,
                        'mime_type' => $mimeType,
                        'last_modified' => date('Y-m-d H:i:s', $lastModified),
                        'url' => '/storage/' . $path
                    ]
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Image not found'
                ], 404);
            }

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get image info',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
