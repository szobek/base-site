<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Media\StoreMediaRequest;
use App\Http\Requests\Media\UpdateMediaRequest;
use App\Http\Resources\MediaResource;
use App\Models\Media;
use App\Services\MediaLimits;
use App\Services\MediaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class MediaController extends Controller
{
    public function __construct(
        private readonly MediaService $media,
        private readonly MediaLimits $limits,
    ) {}

    public function limits(Request $request): JsonResponse
    {
        return response()->json($this->limits->forUser($request->user()));
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Media::class);

        $media = $request->user()
            ->uploads()
            ->when($request->filled('collection'), function ($query) use ($request) {
                $query->where('collection', $request->string('collection')->toString());
            })
            ->latest()
            ->paginate(24);

        return MediaResource::collection($media);
    }

    public function store(StoreMediaRequest $request): JsonResponse
    {
        $media = $this->media->store(
            $request->file('file'),
            $request->user(),
            $request->string('collection')->toString() ?: 'default',
            $request->filled('alt') ? $request->string('alt')->toString() : null,
        );

        return (new MediaResource($media))
            ->additional(['message' => 'Fájl feltöltve.'])
            ->response()
            ->setStatusCode(201);
    }

    public function show(Request $request, Media $media): MediaResource
    {
        $this->authorize('view', $media);

        return new MediaResource($media);
    }

    public function update(UpdateMediaRequest $request, Media $media): MediaResource
    {
        $this->authorize('update', $media);

        $collection = $request->validated('collection');

        if (is_string($collection)) {
            $this->limits->ensureCanMove($media, $collection);
        }

        $media->fill($request->safe()->only(['alt', 'collection']));
        $media->save();

        return new MediaResource($media);
    }

    public function destroy(Request $request, Media $media): Response
    {
        $this->authorize('delete', $media);

        $this->media->delete($media);

        return response()->noContent();
    }
}
