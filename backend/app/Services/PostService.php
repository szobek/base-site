<?php

namespace App\Services;

use App\Enums\PostStatus;
use App\Models\Media;
use App\Models\Post;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PostService
{
    public function __construct(private readonly HtmlSanitizer $html) {}

    /**
     * @param  array{title: string, excerpt?: string|null, body: string, status: PostStatus|string}  $data
     */
    public function create(User $author, array $data): Post
    {
        $status = $this->status($data['status']);
        $body = $this->cleanBody($data['body']);

        $post = $author->posts()->create([
            'title' => $data['title'],
            'slug' => $this->uniqueSlug($data['title']),
            'excerpt' => $this->excerpt($data['excerpt'] ?? null, $body),
            'body' => $body,
            'status' => $status,
            'published_at' => $status === PostStatus::Published ? now() : null,
        ]);

        $this->syncInlineImages($post, $author);

        return $post->refresh()->load('user');
    }

    /**
     * @param  array{title: string, excerpt?: string|null, body: string, status: PostStatus|string}  $data
     */
    public function update(Post $post, User $editor, array $data): Post
    {
        $status = $this->status($data['status']);
        $body = $this->cleanBody($data['body']);

        $post->fill([
            'title' => $data['title'],
            'excerpt' => $this->excerpt($data['excerpt'] ?? null, $body),
            'body' => $body,
            'status' => $status,
        ]);

        if ($status === PostStatus::Published && $post->published_at === null) {
            $post->published_at = now();
        }

        $post->save();
        $this->syncInlineImages($post, $editor);

        return $post->refresh()->load('user');
    }

    private function status(PostStatus|string $status): PostStatus
    {
        return $status instanceof PostStatus ? $status : PostStatus::from($status);
    }

    private function cleanBody(string $body): string
    {
        $clean = $this->html->clean($body);
        $text = trim(html_entity_decode(strip_tags($clean)));

        if ($text === '' && ! str_contains($clean, '<img')) {
            throw ValidationException::withMessages([
                'body' => 'A bejegyzés szövege üres, vagy csak nem engedett formázást tartalmaz.',
            ]);
        }

        return $clean;
    }

    private function excerpt(?string $excerpt, string $body): ?string
    {
        $excerpt = trim((string) $excerpt);

        if ($excerpt !== '') {
            return $excerpt;
        }

        $text = $this->plainText($body);

        if ($text === '') {
            return null;
        }

        return Str::limit($text, 180);
    }

    private function plainText(string $html): string
    {
        $withBreaks = preg_replace(
            '/<(\/?(?:p|div|h2|h3|li|blockquote|br|img)\b[^>]*)>/i',
            ' ',
            $html,
        ) ?? $html;
        $text = html_entity_decode(strip_tags($withBreaks), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim(preg_replace('/\s+/u', ' ', $text) ?? '');
    }

    private function uniqueSlug(string $title): string
    {
        $base = Str::slug($title);

        if ($base === '' || $base === 'new') {
            $base = 'hir';
        }

        $slug = $base;
        $suffix = 2;

        while (Post::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$suffix;
            $suffix++;
        }

        return $slug;
    }

    private function syncInlineImages(Post $post, User $editor): void
    {
        preg_match_all('/<img\b[^>]*\bsrc="([^"]+)"/i', $post->body, $matches);
        $ids = [];

        foreach ($matches[1] ?? [] as $src) {
            $path = $this->pathFromSrc(html_entity_decode($src));

            if ($path === null) {
                continue;
            }

            $media = Media::query()->where('path', $path)->first();

            if (! $media || ! $this->canAttach($media, $post, $editor)) {
                continue;
            }

            $post->attachMedia($media);
            $ids[] = $media->id;
        }

        $detach = $post->media();

        if ($ids !== []) {
            $detach->whereNotIn('id', $ids);
        }

        $detach->update([
            'mediable_type' => null,
            'mediable_id' => null,
        ]);
    }

    private function canAttach(Media $media, Post $post, User $editor): bool
    {
        return $editor->isAdmin()
            || $media->user_id === $editor->id
            || $media->user_id === $post->user_id;
    }

    private function pathFromSrc(string $src): ?string
    {
        $path = (string) (parse_url($src, PHP_URL_PATH) ?? $src);
        $marker = '/storage/';
        $position = strpos($path, $marker);

        if ($position === false) {
            return null;
        }

        return ltrim(substr($path, $position + strlen($marker)), '/');
    }
}
