<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\User;
use App\Services\MediaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PostTest extends TestCase
{
    use RefreshDatabase;

    public function test_author_can_create_a_post_with_formatting_and_an_image(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $media = app(MediaService::class)->store(
            UploadedFile::fake()->image('borito.jpg'),
            $user,
            'images',
            'Borító',
        );

        $response = $this->postJson('/api/posts', [
            'title' => 'Első hír',
            'body' => '<p>Szöveg <strong>kiemelve</strong>.</p>'
                .'<img src="'.$media->url().'" alt="Borító" onerror="alert(1)">'
                .'<a href="javascript:alert(1)">rossz</a>'
                .'<a href="https://example.com">jó</a>'
                .'<script>alert(1)</script>'
                .'<div>bekezdés</div>',
            'status' => 'published',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.slug', 'elso-hir')
            ->assertJsonPath('data.status', 'published');

        $post = Post::query()->firstOrFail();
        $this->assertStringContainsString('<strong>kiemelve</strong>', $post->body);
        $this->assertStringContainsString('<img', $post->body);
        $this->assertStringContainsString('https://example.com', $post->body);
        $this->assertStringContainsString('<p>bekezdés</p>', $post->body);
        $this->assertStringNotContainsString('script', strtolower($post->body));
        $this->assertStringNotContainsString('javascript:', strtolower($post->body));
        $this->assertStringNotContainsString('onerror', strtolower($post->body));
        $this->assertTrue($post->media()->whereKey($media->id)->exists());
        $this->assertNotNull($post->published_at);
    }

    public function test_auto_excerpt_keeps_a_space_around_an_image(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/posts', [
            'title' => 'Kép körül',
            'body' => '<p>text előtte</p><img src="/storage/kep.jpg" alt="sajtos"><p>text utána</p>',
            'status' => 'draft',
        ])->assertCreated()
            ->assertJsonPath('data.excerpt', 'text előtte text utána');
    }

    public function test_image_keeps_only_size_and_alignment_classes(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $media = app(MediaService::class)->store(
            UploadedFile::fake()->image('kep.jpg'),
            $user,
            'images',
            'Kép',
        );

        $this->postJson('/api/posts', [
            'title' => 'Kép a hírben',
            'body' => '<img src="'.$media->url().'" alt="Kép" class="size-50 align-right evil" style="position:fixed" onerror="alert(1)">',
            'status' => 'published',
        ])->assertCreated();

        $body = strtolower(Post::query()->firstOrFail()->body);
        $this->assertStringContainsString('size-50', $body);
        $this->assertStringContainsString('align-right', $body);
        $this->assertStringNotContainsString('evil', $body);
        $this->assertStringNotContainsString('style', $body);
        $this->assertStringNotContainsString('onerror', $body);
    }

    public function test_script_only_body_is_rejected(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/posts', [
            'title' => 'Üres',
            'body' => '<script>alert(1)</script>',
            'status' => 'draft',
        ])->assertUnprocessable()->assertJsonValidationErrors('body');
    }

    public function test_guest_sees_only_published_posts(): void
    {
        $published = Post::factory()->create(['title' => 'Nyilvános']);
        Post::factory()->draft()->create(['title' => 'Vázlat']);

        $this->getJson('/api/posts')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.slug', $published->slug)
            ->assertJsonMissingPath('data.0.body');

        $this->getJson('/api/posts/'.$published->slug)
            ->assertOk()
            ->assertJsonPath('data.body', $published->body);
    }

    public function test_published_filter_hides_drafts_from_an_admin(): void
    {
        $published = Post::factory()->create(['title' => 'Kint']);
        Post::factory()->draft()->create();

        Sanctum::actingAs(User::factory()->admin()->create());

        $this->getJson('/api/posts?published=1')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.slug', $published->slug);
    }

    public function test_draft_is_hidden_from_others_and_visible_to_the_author(): void
    {
        $post = Post::factory()->draft()->create();
        $other = User::factory()->create();

        $this->getJson('/api/posts/'.$post->slug)->assertNotFound();

        Sanctum::actingAs($other);
        $this->getJson('/api/posts/'.$post->slug)->assertNotFound();
        $this->putJson('/api/posts/'.$post->slug, $this->payload())->assertForbidden();

        Sanctum::actingAs($post->user);
        $this->getJson('/api/posts')
            ->assertOk()
            ->assertJsonFragment(['slug' => $post->slug]);
        $this->getJson('/api/posts/'.$post->slug)->assertOk();
    }

    public function test_author_can_update_and_delete_without_changing_the_slug(): void
    {
        $post = Post::factory()->create([
            'title' => 'Régi cím',
            'slug' => 'regi-cim',
        ]);
        Sanctum::actingAs($post->user);

        $this->putJson('/api/posts/regi-cim', [
            'title' => 'Új cím',
            'body' => '<p>Friss szöveg</p>',
            'status' => 'published',
        ])->assertOk()
            ->assertJsonPath('data.slug', 'regi-cim')
            ->assertJsonPath('data.title', 'Új cím');

        $this->deleteJson('/api/posts/regi-cim')->assertNoContent();
        $this->assertDatabaseMissing('posts', ['id' => $post->id]);
    }

    public function test_admin_can_update_someone_elses_post(): void
    {
        $post = Post::factory()->create();
        Sanctum::actingAs(User::factory()->admin()->create());

        $this->putJson('/api/posts/'.$post->slug, [
            'title' => 'Admin javítás',
            'body' => '<p>Javítva</p>',
            'status' => 'draft',
        ])->assertOk()->assertJsonPath('data.title', 'Admin javítás');
    }

    public function test_duplicate_titles_get_unique_slugs(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/posts', $this->payload('Azonos cím'))->assertCreated()
            ->assertJsonPath('data.slug', 'azonos-cim');
        $this->postJson('/api/posts', $this->payload('Azonos cím'))->assertCreated()
            ->assertJsonPath('data.slug', 'azonos-cim-2');
    }

    public function test_guest_cannot_create_a_post(): void
    {
        $this->postJson('/api/posts', $this->payload())->assertUnauthorized();
    }

    /**
     * @return array{title: string, body: string, status: string}
     */
    private function payload(string $title = 'Hír'): array
    {
        return [
            'title' => $title,
            'body' => '<p>Szöveg</p>',
            'status' => 'published',
        ];
    }
}
