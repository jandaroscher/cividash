<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Class BackgroundPageResource
 *
 * Transforms a BackgroundPage model into a JSON-friendly array.
 */
class BackgroundPageResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  Request  $request
     * @return array<string,mixed>
     */
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'content' => $this->content,
            'position' => $this->position,
        ];
    }
}
