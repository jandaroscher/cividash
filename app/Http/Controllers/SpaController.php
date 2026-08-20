<?php

namespace App\Http\Controllers;

use App\Services\MetaTagService;
use Illuminate\Http\Request;

class SpaController extends Controller
{
    public function index(Request $request, MetaTagService $metaTagService)
    {
        $meta = $metaTagService->resolve($request->path(), $request->getHost());
        $tenant = $request->attributes->get('resolved_tenant');

        return view('app', [
            'meta' => $meta,
            'tenant' => $tenant
                ? ['slug' => $tenant->slug, 'name' => $tenant->name]
                : ['slug' => 'default', 'name' => null],
        ]);
    }
}
