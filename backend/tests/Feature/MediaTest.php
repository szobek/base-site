<?php

namespace Tests\Feature;

use App\Models\Media;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MediaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
    }

    public function test_user_can_upload_and_list_own_media(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $upload = $this->post('/api/media', [
            'file' => UploadedFile::fake()->image('photo.jpg'),
            'collection' => 'images',
            'alt' => 'Borító',
        ], ['Accept' => 'application/json']);

        $upload->assertCreated()
            ->assertJsonPath('data.collection', 'images')
            ->assertJsonPath('data.alt', 'Borító')
            ->assertJsonPath('data.is_image', true);

        $path = Media::query()->firstOrFail()->path;
        Storage::disk('public')->assertExists($path);

        $this->getJson('/api/media?collection=images')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.filename', 'photo.jpg');
    }

    public function test_user_cannot_see_someone_elses_media(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        Sanctum::actingAs($owner);

        $this->post('/api/media', [
            'file' => UploadedFile::fake()->image('photo.jpg'),
            'collection' => 'images',
        ], ['Accept' => 'application/json'])->assertCreated();

        Sanctum::actingAs($other);

        $this->getJson('/api/media')->assertOk()->assertJsonCount(0, 'data');
        $this->deleteJson('/api/media/'.Media::query()->firstOrFail()->id)->assertForbidden();
    }

    public function test_upload_rejects_disallowed_mime_type(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->post('/api/media', [
            'file' => UploadedFile::fake()->create('notes.txt', 10, 'text/plain'),
            'collection' => 'images',
        ], ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('file');
    }

    public function test_user_can_delete_own_media(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->post('/api/media', [
            'file' => UploadedFile::fake()->image('photo.jpg'),
            'collection' => 'images',
        ], ['Accept' => 'application/json'])->assertCreated();

        $media = Media::query()->firstOrFail();

        $this->deleteJson('/api/media/'.$media->id)->assertNoContent();

        $this->assertDatabaseMissing('media', ['id' => $media->id]);
        Storage::disk('public')->assertMissing($media->path);
    }

    public function test_avatar_upload_replaces_the_previous_avatar(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->post('/api/media', [
            'file' => UploadedFile::fake()->image('first.jpg'),
            'collection' => 'avatar',
        ], ['Accept' => 'application/json'])->assertCreated();

        $this->post('/api/media', [
            'file' => UploadedFile::fake()->image('second.jpg'),
            'collection' => 'avatar',
        ], ['Accept' => 'application/json'])->assertCreated();

        $avatars = $user->media()->where('collection', 'avatar')->get();

        $this->assertCount(1, $avatars);
        $this->assertSame('second.jpg', $avatars->first()->filename);
        $this->assertSame($user->id, $avatars->first()->mediable_id);
        $this->assertSame(1, Media::query()->count());
    }

    public function test_upload_rejects_a_file_over_the_collection_size_limit(): void
    {
        config(['media.collections.images.max_kilobytes' => 100]);
        Sanctum::actingAs(User::factory()->create());

        $this->post('/api/media', [
            'file' => UploadedFile::fake()->image('big.jpg')->size(250),
            'collection' => 'images',
        ], ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('file');
    }

    public function test_upload_rejects_a_file_past_the_collection_count(): void
    {
        config(['media.collections.images.max_files' => 1]);
        Sanctum::actingAs(User::factory()->create());

        $this->post('/api/media', [
            'file' => UploadedFile::fake()->image('one.jpg'),
            'collection' => 'images',
        ], ['Accept' => 'application/json'])->assertCreated();

        $this->post('/api/media', [
            'file' => UploadedFile::fake()->image('two.jpg'),
            'collection' => 'images',
        ], ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('file');

        $this->assertSame(1, Media::query()->count());
    }

    public function test_upload_rejects_a_file_past_the_total_count(): void
    {
        config([
            'media.max_files' => 1,
            'media.collections.images.max_files' => 10,
            'media.collections.documents.max_files' => 10,
        ]);
        Sanctum::actingAs(User::factory()->create());

        $this->post('/api/media', [
            'file' => UploadedFile::fake()->image('one.jpg'),
            'collection' => 'images',
        ], ['Accept' => 'application/json'])->assertCreated();

        $this->post('/api/media', [
            'file' => UploadedFile::fake()->create('notes.txt', 10, 'text/plain'),
            'collection' => 'documents',
        ], ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('file');
    }

    public function test_limits_endpoint_reports_usage(): void
    {
        config(['media.collections.images.max_files' => 5]);
        Sanctum::actingAs(User::factory()->create());

        $this->post('/api/media', [
            'file' => UploadedFile::fake()->image('one.jpg'),
            'collection' => 'images',
        ], ['Accept' => 'application/json'])->assertCreated();

        $this->getJson('/api/media/limits')
            ->assertOk()
            ->assertJsonPath('used_files', 1)
            ->assertJsonPath('collections.images.used_files', 1)
            ->assertJsonPath('collections.images.max_files', 5)
            ->assertJsonPath('collections.avatar.max_files', 1)
            ->assertJsonStructure([
                'max_files',
                'collections' => [
                    'images' => ['mimes', 'mime_labels', 'max_kilobytes', 'max_files', 'used_files'],
                ],
            ]);
    }

    public function test_file_cannot_move_into_a_full_collection(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->post('/api/media', [
            'file' => UploadedFile::fake()->image('avatar.jpg'),
            'collection' => 'avatar',
        ], ['Accept' => 'application/json'])->assertCreated();

        $image = $this->post('/api/media', [
            'file' => UploadedFile::fake()->image('photo.jpg'),
            'collection' => 'images',
        ], ['Accept' => 'application/json'])->assertCreated()->json('data.id');

        $this->patchJson('/api/media/'.$image, ['collection' => 'avatar'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('collection');
    }

    public function test_guest_cannot_upload(): void
    {
        $this->post('/api/media', [
            'file' => UploadedFile::fake()->image('photo.jpg'),
        ], ['Accept' => 'application/json'])->assertUnauthorized();
    }
}
