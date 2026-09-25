<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Post\SavePostRequest;
use App\Http\Resources\PostResource;
use App\Models\Post;
use App\Services\PostService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class PostController extends Controller
{
    public function __construct(private readonly PostService $posts) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Post::class);

        $user = $request->user();

        $posts = Post::query()
            ->with('user')
            ->when(
                $user?->isAdmin(),
                fn ($query) => $query,
                fn ($query) => $user
                    ? $query->where(fn ($inner) => $inner->published()->orWhere('user_id', $user->id))
                    : $query->published(),
            )
            ->latest()
            ->paginate(12);

        return PostResource::collection($posts);
    }

    public function show(Request $request, Post $post): PostResource
    {
        $user = $request->user();

        if (! $post->isPublished() && ! ($user && $user->can('view', $post))) {
            abort(404);
        }

        return new PostResource($post->load('user'));
    }

    public function store(SavePostRequest $request): JsonResponse
    {
        $this->authorize('create', Post::class);

        $post = $this->posts->create($request->user(), $request->validated());

        return (new PostResource($post))
            ->additional(['message' => 'Bejegyzés mentve.'])
            ->response()
            ->setStatusCode(201);
    }

    public function update(SavePostRequest $request, Post $post): PostResource
    {
        $this->authorize('update', $post);

        $post = $this->posts->update($post, $request->user(), $request->validated());

        return (new PostResource($post))->additional([
            'message' => 'Bejegyzés mentve.',
        ]);
    }

    public function destroy(Request $request, Post $post): Response
    {
        $this->authorize('delete', $post);

        $post->delete();

        return response()->noContent();
    }
}
