<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Contact\StoreContactRequest;
use App\Models\ContactMessage;
use Illuminate\Http\JsonResponse;

class ContactController extends Controller
{
    public function store(StoreContactRequest $request): JsonResponse
    {
        ContactMessage::query()->create($request->safe()->only(['name', 'email', 'message']));

        return response()->json([
            'message' => 'Köszönjük, az üzenetet megkaptuk.',
        ], 201);
    }
}
