<?php

namespace App\Traits;

use App\Models\Media;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait HasMedia
{
    public function media(): MorphMany
    {
        return $this->morphMany(Media::class, 'mediable');
    }

    public function attachMedia(Media $media, ?string $collection = null): Media
    {
        if ($collection !== null) {
            $media->collection = $collection;
        }

        $media->mediable()->associate($this);
        $media->save();

        return $media;
    }
}
