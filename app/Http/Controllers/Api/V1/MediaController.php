<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class MediaController extends Controller
{
    /**
     * POST /api/v1/media/upload
     * Upload image/video to public storage. Returns public URL.
     */
    public function upload(Request $request): JsonResponse
    {
        $request->validate([
            'file' => [
                'required',
                'file',
                'max:51200',
                'mimes:jpg,jpeg,png,webp,gif,mp4,webm,mov',
            ],
        ]);

        $file = $request->file('file');
        $directory = public_path('storage/uploads');

        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $extension = strtolower($file->getClientOriginalExtension() ?: $file->extension());
        $name = Str::uuid().($extension ? '.'.$extension : '');
        $file->move($directory, $name);

        $path = 'uploads/'.$name;
        $url = '/storage/'.$path;

        return response()->json([
            'url'  => $url,
            'path' => $path,
        ], 201);
    }
}
