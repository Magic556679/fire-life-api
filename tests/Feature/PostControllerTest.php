<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PostControllerTest extends TestCase
{
    use RefreshDatabase;

    private string $cdnUrl = 'https://cdn.firelifedev.com';

    protected function setUp(): void
    {
        parent::setUp();
        config(['filesystems.disks.r2.url' => $this->cdnUrl]);
        Storage::fake('r2');
    }

    private function asUser(): static
    {
        return $this->actingAs(User::factory()->create(), 'sanctum');
    }

    // --- store ---

    public function test_store_creates_post_with_og_image(): void
    {
        $response = $this->asUser()->postJson('/api/posts', [
            'title' => 'Test Post',
            'slug' => 'test-post',
            'content' => '<p>Hello</p>',
            'og_image' => "{$this->cdnUrl}/images/og.jpg",
        ]);

        $response->assertStatus(201)->assertJsonPath('success', true);
        $this->assertDatabaseHas('posts', [
            'slug' => 'test-post',
            'og_image' => "{$this->cdnUrl}/images/og.jpg",
        ]);
    }

    public function test_store_creates_post_without_og_image(): void
    {
        $response = $this->asUser()->postJson('/api/posts', [
            'title' => 'Test Post',
            'slug' => 'test-post',
            'content' => '<p>Hello</p>',
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('posts', ['slug' => 'test-post', 'og_image' => null]);
    }

    public function test_store_rejects_invalid_og_image_url(): void
    {
        $response = $this->asUser()->postJson('/api/posts', [
            'title' => 'Test Post',
            'slug' => 'test-post',
            'content' => '<p>Hello</p>',
            'og_image' => 'not-a-url',
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['og_image']);
    }

    public function test_store_requires_auth(): void
    {
        $response = $this->postJson('/api/posts', [
            'title' => 'Test Post',
            'slug' => 'test-post',
            'content' => '<p>Hello</p>',
        ]);

        $response->assertStatus(401);
    }

    // --- index ---

    public function test_index_returns_posts_with_default_per_page(): void
    {
        Post::factory()->count(15)->create();

        $response = $this->getJson('/api/posts');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.per_page', 10)
            ->assertJsonCount(10, 'data.data');
    }

    public function test_index_respects_per_page_param(): void
    {
        Post::factory()->count(15)->create();

        $response = $this->getJson('/api/posts?per_page=5');

        $response->assertStatus(200)
            ->assertJsonPath('data.per_page', 5)
            ->assertJsonCount(5, 'data.data');
    }

    public function test_index_caps_per_page_at_100(): void
    {
        $response = $this->getJson('/api/posts?per_page=200');

        $response->assertStatus(200)->assertJsonPath('data.per_page', 100);
    }

    public function test_index_returns_latest_first(): void
    {
        Post::factory()->create(['title' => 'Older', 'created_at' => now()->subMinute()]);
        Post::factory()->create(['title' => 'Newer', 'created_at' => now()]);

        $response = $this->getJson('/api/posts');

        $response->assertStatus(200);
        $this->assertEquals('Newer', $response->json('data.data.0.title'));
    }

    // --- update ---

    public function test_update_saves_og_image(): void
    {
        $post = Post::factory()->create();

        $response = $this->asUser()->patchJson("/api/posts/{$post->id}", [
            'og_image' => "{$this->cdnUrl}/images/new-og.jpg",
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('posts', [
            'id' => $post->id,
            'og_image' => "{$this->cdnUrl}/images/new-og.jpg",
        ]);
    }

    public function test_update_deletes_removed_content_images_from_r2(): void
    {
        $path = 'posts/old-image.jpg';
        Storage::disk('r2')->put($path, 'fake-image');

        $post = Post::factory()->create([
            'content' => "<img src=\"{$this->cdnUrl}/{$path}\" />",
        ]);

        $this->asUser()->patchJson("/api/posts/{$post->id}", [
            'content' => '<p>No images now</p>',
        ])->assertStatus(200);

        Storage::disk('r2')->assertMissing($path);
    }

    public function test_update_keeps_images_still_present_in_content(): void
    {
        $path = 'posts/kept-image.jpg';
        Storage::disk('r2')->put($path, 'fake-image');

        $post = Post::factory()->create([
            'content' => "<img src=\"{$this->cdnUrl}/{$path}\" />",
        ]);

        $this->asUser()->patchJson("/api/posts/{$post->id}", [
            'content' => "<img src=\"{$this->cdnUrl}/{$path}\" /><p>Updated</p>",
        ])->assertStatus(200);

        Storage::disk('r2')->assertExists($path);
    }

    // --- destroy ---

    public function test_destroy_deletes_og_image_from_r2(): void
    {
        $path = 'posts/og.jpg';
        Storage::disk('r2')->put($path, 'fake-image');

        $post = Post::factory()->create([
            'og_image' => "{$this->cdnUrl}/{$path}",
            'content' => '<p>No images</p>',
        ]);

        $this->asUser()->deleteJson("/api/posts/{$post->id}")->assertStatus(200);

        Storage::disk('r2')->assertMissing($path);
        $this->assertDatabaseMissing('posts', ['id' => $post->id]);
    }

    public function test_destroy_deletes_content_images_from_r2(): void
    {
        $path = 'posts/content-image.jpg';
        Storage::disk('r2')->put($path, 'fake-image');

        $post = Post::factory()->create([
            'content' => "<img src=\"{$this->cdnUrl}/{$path}\" />",
        ]);

        $this->asUser()->deleteJson("/api/posts/{$post->id}")->assertStatus(200);

        Storage::disk('r2')->assertMissing($path);
    }

    public function test_destroy_requires_auth(): void
    {
        $post = Post::factory()->create();

        $this->deleteJson("/api/posts/{$post->id}")->assertStatus(401);
    }
}
