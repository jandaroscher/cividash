<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\MetaTagService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OgMetaController extends Controller
{
    public function show(Request $request, MetaTagService $service): JsonResponse
    {
        $request->validate([
            'path' => 'required|string|max:500',
        ]);

        $meta = $service->resolve(
            $request->query('path'),
            $request->getHost()
        );

        return response()->json($meta->toArray())
            ->setPrivate()
            ->setMaxAge(300);
    }
}
