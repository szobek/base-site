<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ContactMessageResource;
use App\Models\ContactMessage;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class ContactMessageController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return ContactMessageResource::collection(
            ContactMessage::query()->latest()->limit(100)->get()
        );
    }

    public function read(ContactMessage $message): ContactMessageResource
    {
        if ($message->read_at === null) {
            $message->read_at = now();
            $message->save();
        }

        return new ContactMessageResource($message);
    }

    public function destroy(ContactMessage $message): Response
    {
        $message->delete();

        return response()->noContent();
    }
}
