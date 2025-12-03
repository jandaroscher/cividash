<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\HandlungsdimensionResource;
use App\Http\Resources\HandlungsfeldResource;
use App\Http\Resources\SDGZielResource;
use App\Models\Category;
use App\Models\Handlungsdimension;
use App\Models\SDGZiel;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FilterController extends Controller
{
    /**
     * Return filter category labels and filter data based on locale.
     *
     * @param \Illuminate\Http\Request $request Request that may contain an optional `locale` query parameter.
     * @return JsonResource Filter labels and filter data for the requested locale.
     */
    public function index(Request $request): JsonResource
    {
        $locale = $request->query('locale', 'de');

        // Validate locale
        if (!in_array($locale, ['de', 'en'])) {
            $locale = 'de';
        }

        // Filter labels
        $labels = [
            'de' => [
                'dimensions' => 'Handlungsdimensionen',
                'fields' => 'Handlungsfelder',
                'sdg' => 'SDG-Ziele',
                'header' => 'Filter',
            ],
            'en' => [
                'dimensions' => 'Action Dimensions',
                'fields' => 'Action Fields',
                'sdg' => 'SDG Goals',
                'header' => 'Filter',
            ],
        ];

        // Fetch filter data from database
        $dimensionen = Handlungsdimension::orderBy('position')->get();
        $handlungsfelder = Category::orderBy('position')->get();
        $sdgZiele = SDGZiel::orderBy('number')->get();

        // Create a new request with locale query parameter for resources
        $resourceRequest = Request::create($request->url(), 'GET', ['locale' => $locale]);

        return new JsonResource([
            'labels' => $labels[$locale],
            'dimensions' => HandlungsdimensionResource::collection($dimensionen)->toArray($resourceRequest),
            'fields' => HandlungsfeldResource::collection($handlungsfelder)->toArray($resourceRequest),
            'sdg' => SDGZielResource::collection($sdgZiele)->toArray($resourceRequest),
        ]);
    }
}

