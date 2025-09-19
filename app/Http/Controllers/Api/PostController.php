<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Post;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Log;

class PostController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'slug' => ['required', 'string', 'max:255', Rule::unique('posts', 'slug')],
            'metaDescription' => 'nullable|string|max:500',
            'content' => 'required|string',
        ]);

        $post = Post::create([
            'title' => $validated['title'],
            'slug' => $validated['slug'],
            'meta_description' => $validated['metaDescription'] ?? null,
            'content' => $validated['content'],
        ]);

        return response()->json([
            'success' => true,
            'message' => '文章建立成功',
            'post' => $post,
        ], 201);
    }

    public function show($id)
    {
        // findOrFail 處理找不到資料 觸發異常
        // $post = Post::where('id', $id)->first();
        $post = Post::findOrFail($id);

        return response()->json([
            'success' => true,
            'message' => '取得文章成功',
            'data' => $post
        ], 200);
    }

    public function index()
    {
        $posts = Post::paginate(10);

        return response()->json([
            'success' => true,
            'message' => '取得文章列表成功',
            'data' => $posts
        ], 200);
    }

    public function update(Request $request, $id)
    {
        $post = Post::findOrFail($id);

        $validated = $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'slug' => ['sometimes', 'required', 'string', 'max:255', Rule::unique('posts', 'slug')->ignore($post->id)],
            'metaDescription' => 'nullable|string|max:500',
            'content' => 'sometimes|required|string',
        ]);

        $post->update($validated);

        return response()->json([
            'success' => true,
            'message' => '文章更新成功',
            'data' => $post->fresh(),
        ], 200);
    }

    public function destroy($id)
    {
        $post = Post::findOrFail($id);
        $post->delete();

        return response()->json([
            'success' => true,
            'message' => '文章刪除成功',
        ], 200);
    }
}
