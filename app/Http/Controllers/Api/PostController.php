<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Post;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class PostController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'slug' => ['required', 'string', 'max:255', Rule::unique('posts', 'slug')],
            'metaDescription' => 'nullable|string|max:500',
            'content' => 'required|string',
            'og_image' => 'nullable|url|max:2048',
        ]);

        $post = Post::create([
            'title' => $validated['title'],
            'slug' => $validated['slug'],
            'meta_description' => $validated['metaDescription'] ?? null,
            'content' => $validated['content'],
            'og_image' => $validated['og_image'] ?? null,
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

    public function index(Request $request)
    {
        $perPage = min((int) $request->input('per_page', 10), 100);
        $posts = Post::latest()->paginate($perPage);

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
            'meta_description' => 'nullable|string|max:500',
            'content' => 'sometimes|required|string',
            'og_image' => 'nullable|url|max:2048',
        ]);

        if (isset($validated['content'])) {
            $removedUrls = array_diff(
                $this->extractContentImageUrls($post->content),
                $this->extractContentImageUrls($validated['content'])
            );
            $this->deleteR2Images($removedUrls, $post->id);
        }

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

        $allImages = array_merge(
            $post->og_image ? [$post->og_image] : [],
            $this->extractContentImageUrls($post->content)
        );
        $this->deleteR2Images($allImages, $post->id);

        $post->delete();

        return response()->json([
            'success' => true,
            'message' => '文章刪除成功',
        ], 200);
    }

    private function extractContentImageUrls(string $html): array
    {
        preg_match_all('/<img[^>]+src="([^"]+)"/', $html, $matches);
        $cdnUrl = config('filesystems.disks.r2.url');
        return array_values(array_filter($matches[1], fn($url) => str_starts_with($url, $cdnUrl)));
    }

    private function deleteR2Images(array $urls, int $postId): void
    {
        $cdnUrl = config('filesystems.disks.r2.url');
        foreach ($urls as $url) {
            try {
                $relativePath = str_replace($cdnUrl . '/', '', $url);
                if (Storage::disk('r2')->exists($relativePath)) {
                    Storage::disk('r2')->delete($relativePath);
                }
            } catch (\Exception $e) {
                Log::error('R2 圖片刪除失敗', [
                    'post_id' => $postId,
                    'url' => $url,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
