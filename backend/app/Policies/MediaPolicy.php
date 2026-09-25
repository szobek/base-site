<?php

namespace App\Policies;

use App\Models\Media;
use App\Models\User;

class MediaPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Media $media): bool
    {
        return $this->owns($user, $media);
    }

    public function update(User $user, Media $media): bool
    {
        return $this->owns($user, $media);
    }

    public function delete(User $user, Media $media): bool
    {
        return $this->owns($user, $media);
    }

    private function owns(User $user, Media $media): bool
    {
        return $user->isAdmin() || $media->user_id === $user->id;
    }
}
