<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class UploadController extends Controller
{
    public function store(Request $request)
    {
        // 最大 5MB
        $request->validate([
            'image' => 'required|image|max:5120',
        ]);

        try {
            // store 第一個參數 建立資料夾 存放在裡面
            $path = $request->file('image')->store('', 'r2');

            $url = config('filesystems.disks.r2.url') . '/' . $path;

            return response()->json([
                'success' => true,
                'message' => 'Upload successful',
                'path' => $path,
                'url' => $url,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Upload failed: ' . $e->getMessage()
            ], 500);
        }
    }
}
