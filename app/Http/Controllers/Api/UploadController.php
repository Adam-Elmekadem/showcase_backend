<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class UploadController extends Controller
{
    /**
     * Sign a direct-to-Cloudinary upload so the API secret never reaches
     * the browser. The client uploads the file straight to Cloudinary
     * using this signature, timestamp, and api_key.
     */
    public function signCloudinaryUpload(Request $request)
    {
        $timestamp = time();
        $folder = 'showcase-covers';

        $paramsToSign = [
            'folder' => $folder,
            'timestamp' => $timestamp,
        ];
        ksort($paramsToSign);

        $toSign = collect($paramsToSign)
            ->map(fn ($value, $key) => "{$key}={$value}")
            ->implode('&');

        $signature = sha1($toSign.config('services.cloudinary.api_secret'));

        return response()->json([
            'signature' => $signature,
            'timestamp' => $timestamp,
            'folder' => $folder,
            'api_key' => config('services.cloudinary.api_key'),
            'cloud_name' => config('services.cloudinary.cloud_name'),
        ]);
    }
}
