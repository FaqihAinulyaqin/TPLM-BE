<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class AttachmentController extends Controller
{
    public function download($filename)
    {
        try {
            Log::info('Download attempt: ' . $filename);
            
            $path = storage_path('app/public/attachments/' . $filename);
            Log::info('Full path: ' . $path);
            
            if (!file_exists($path)) {
                Log::error('File not found at: ' . $path);
                return response()->json(['message' => 'File not found'], 404);
            }
            
            Log::info('File exists, downloading: ' . $path);
            return response()->file($path);
            
        } catch (\Exception $e) {
            Log::error('Download error: ' . $e->getMessage());
            return response()->json(['message' => 'Download error: ' . $e->getMessage()], 500);
        }
    }
}
