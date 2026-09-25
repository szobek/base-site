<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateSettingsRequest;
use App\Services\SiteSettings;
use Illuminate\Http\JsonResponse;

class SettingsController extends Controller
{
    public function show(SiteSettings $settings): JsonResponse
    {
        return response()->json([
            'data' => $settings->all(),
        ]);
    }

    public function update(UpdateSettingsRequest $request, SiteSettings $settings): JsonResponse
    {
        return response()->json([
            'data' => $settings->update($request->string('site_name')->toString()),
        ]);
    }
}
