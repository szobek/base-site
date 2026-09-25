<?php

namespace App\Services;

use App\Models\Media;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class MediaService
{
    public function __construct(private readonly MediaLimits $limits) {}

    public function store(UploadedFile $file, User $user, string $collection, ?string $alt = null): Media
    {
        $bytes = (int) ($file->getSize() ?: 0);
        $this->limits->ensureCanStore($user, $collection, $bytes);

        $disk = (string) config('media.disk');
        $uuid = (string) Str::uuid();
        $extension = $file->guessExtension() ?: $file->extension();
        $filename = $uuid.($extension ? '.'.$extension : '');

        $path = $file->storeAs("media/{$user->id}/{$collection}", $filename, $disk);

        try {
            return DB::transaction(function () use ($file, $user, $collection, $alt, $disk, $uuid, $path, $bytes): Media {
                User::query()->whereKey($user->id)->lockForUpdate()->first();
                $this->limits->ensureCanStore($user->fresh(), $collection, $bytes);

                $media = $user->uploads()->create([
                    'uuid' => $uuid,
                    'collection' => $collection,
                    'disk' => $disk,
                    'path' => $path,
                    'filename' => $file->getClientOriginalName(),
                    'mime_type' => $file->getMimeType() ?: 'application/octet-stream',
                    'size' => $file->getSize() ?: 0,
                    'alt' => $alt,
                ]);

                if ($collection === 'avatar') {
                    $user->attachMedia($media, 'avatar');
                    $this->deleteOtherAvatars($user, $media);
                }

                return $media->refresh();
            });
        } catch (\Throwable $exception) {
            Storage::disk($disk)->delete($path);

            throw $exception;
        }
    }

    public function delete(Media $media): void
    {
        $media->delete();
    }

    private function deleteOtherAvatars(User $user, Media $current): void
    {
        $user->media()
            ->where('collection', 'avatar')
            ->whereKeyNot($current->id)
            ->get()
            ->each(fn (Media $media) => $this->delete($media));
    }
}
