<?php

namespace App\Services;

use App\Models\Media;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class MediaLimits
{
    /** @var array<string, string> */
    private const LABELS = [
        'image/jpeg' => 'JPEG',
        'image/png' => 'PNG',
        'image/webp' => 'WebP',
        'image/gif' => 'GIF',
        'application/pdf' => 'PDF',
        'text/plain' => 'TXT',
    ];

    /**
     * @return array{mimes: list<string>, mime_labels: list<string>, max_kilobytes: int, max_files: int|null}|null
     */
    public function find(string $name): ?array
    {
        $collection = config("media.collections.{$name}");

        if (! is_array($collection) || ! is_array($collection['mimes'] ?? null)) {
            return null;
        }

        $globalKilobytes = (int) config('media.max_kilobytes');
        $kilobytes = min((int) ($collection['max_kilobytes'] ?? $globalKilobytes), $globalKilobytes);

        return [
            'mimes' => array_values($collection['mimes']),
            'mime_labels' => array_map(
                fn (string $mime) => self::LABELS[$mime] ?? $mime,
                array_values($collection['mimes']),
            ),
            'max_kilobytes' => $kilobytes,
            'max_files' => array_key_exists('max_files', $collection) ? (int) $collection['max_files'] : null,
        ];
    }

    /**
     * @return array{mimes: list<string>, mime_labels: list<string>, max_kilobytes: int, max_files: int|null}
     */
    public function collection(string $name): array
    {
        $rules = $this->find($name);

        if ($rules === null) {
            throw ValidationException::withMessages([
                'collection' => 'Ismeretlen média gyűjtemény.',
            ]);
        }

        return $rules;
    }

    /**
     * @return array{max_files: int, used_files: int, collections: array<string, array<string, mixed>>}
     */
    public function forUser(User $user): array
    {
        $counts = $user->uploads()
            ->selectRaw('collection, COUNT(*) as aggregate')
            ->groupBy('collection')
            ->pluck('aggregate', 'collection');

        $collections = [];

        foreach (array_keys(config('media.collections')) as $name) {
            $collections[$name] = [
                ...$this->collection($name),
                'used_files' => (int) ($counts[$name] ?? 0),
            ];
        }

        return [
            'max_files' => (int) config('media.max_files'),
            'used_files' => (int) $counts->sum(),
            'collections' => $collections,
        ];
    }

    public function ensureCanStore(User $user, string $collection, int $bytes): void
    {
        $rules = $this->collection($collection);
        $this->ensureSize($bytes, $rules['max_kilobytes']);

        if ($this->replacesAvatar($user, $collection)) {
            return;
        }

        $this->ensureCollectionCount($user, $collection, $rules['max_files']);
        $this->ensureTotalCount($user);
    }

    public function ensureCanMove(Media $media, string $collection): void
    {
        if ($media->collection === $collection) {
            return;
        }

        $rules = $this->collection($collection);

        if (! in_array($media->mime_type, $rules['mimes'], true)) {
            throw ValidationException::withMessages([
                'collection' => 'Ez a fájltípus nem tehető át ebbe a gyűjteménybe. Engedett: '.implode(', ', $rules['mime_labels']).'.',
            ]);
        }

        $this->ensureSize($media->size, $rules['max_kilobytes'], 'collection');
        $this->ensureCollectionCount($media->user, $collection, $rules['max_files'], 'collection');
    }

    public function sizeLabel(int $kilobytes): string
    {
        if ($kilobytes >= 1024 && $kilobytes % 1024 === 0) {
            return ($kilobytes / 1024).' MB';
        }

        if ($kilobytes > 1024) {
            return number_format($kilobytes / 1024, 1, ',', '').' MB';
        }

        return $kilobytes.' KB';
    }

    private function replacesAvatar(User $user, string $collection): bool
    {
        return $collection === 'avatar'
            && $user->uploads()->where('collection', 'avatar')->exists();
    }

    private function ensureSize(int $bytes, int $maxKilobytes, string $field = 'file'): void
    {
        if ($bytes > $maxKilobytes * 1024) {
            throw ValidationException::withMessages([
                $field => 'A fájl legfeljebb '.$this->sizeLabel($maxKilobytes).' lehet.',
            ]);
        }
    }

    private function ensureCollectionCount(User $user, string $collection, ?int $maxFiles, string $field = 'file'): void
    {
        if ($maxFiles === null) {
            return;
        }

        $used = $user->uploads()->where('collection', $collection)->count();

        if ($used >= $maxFiles) {
            throw ValidationException::withMessages([
                $field => "Ebben a gyűjteményben legfeljebb {$maxFiles} fájl lehet.",
            ]);
        }
    }

    private function ensureTotalCount(User $user): void
    {
        $maxFiles = (int) config('media.max_files');

        if ($user->uploads()->count() >= $maxFiles) {
            throw ValidationException::withMessages([
                'file' => "Legfeljebb {$maxFiles} fájlt tölthetsz fel.",
            ]);
        }
    }
}
